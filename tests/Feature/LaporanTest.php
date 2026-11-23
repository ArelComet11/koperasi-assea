<?php

namespace Tests\Feature;

use App\Services\LaporanService;
use App\Services\PembayaranService;
use App\Services\PengajuanService;
use App\Services\PenyaluranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
    }

    private function transaksiSelesai(): void
    {
        $this->terima(10000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $this->actingAs($u);
        $p = app(PengajuanService::class)->ajukan($a, ['kapal_id' => $k->id, 'surat_rekomendasi_id' => $s->id, 'liter_diminta' => 500, 'tanggal_rencana' => today()->toDateString()]);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai');
        app(PenyaluranService::class)->catat($p, 500, $admin);
    }

    public function test_angka_laporan_sesuai_transaksi(): void
    {
        $this->transaksiSelesai();
        $this->actingAs($this->admin());

        $keu = app(LaporanService::class)->bangun('keuangan');
        $this->assertSame('Rp3.400.000', $keu['ringkasan']['Total pembayaran diterima']);
        $this->assertSame('Rp3.400.000', $keu['ringkasan']['Total tagihan']);
        $this->assertSame(1, $keu['ringkasan']['Transaksi lunas']);

        $sal = app(LaporanService::class)->bangun('penyaluran');
        $this->assertSame(500.0, $sal['ringkasan']['Total liter disalurkan']);

        $stok = collect(app(LaporanService::class)->bangun('persediaan')['rows'])->keyBy(0);
        $this->assertSame(9500.0, $stok['Stok fisik akhir'][1]);
        $this->assertFalse($stok->has('Stok dialokasikan (akhir)'));
    }

    public function test_export_excel_dan_cetak(): void
    {
        $this->transaksiSelesai();

        $res = $this->actingAs($this->admin())->get('/laporan/export?jenis=transaksi');
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));

        $this->actingAs($this->admin())->get('/laporan/cetak?jenis=keuangan')->assertOk()->assertSee('Laporan Keuangan');
    }

    public function test_finance_hanya_boleh_laporan_keuangan_dan_transaksi(): void
    {
        $fin = $this->finance();
        $this->assertSame(['transaksi', 'keuangan'], array_keys(LaporanService::jenisUntuk($fin)));

        $this->actingAs($fin)->get('/laporan/export?jenis=anggota')->assertForbidden();
        $this->actingAs($fin)->get('/laporan/export?jenis=keuangan')->assertOk();
    }

    public function test_anggota_tidak_dapat_mengakses_laporan(): void
    {
        [, , , $u] = $this->buatAnggota();

        $this->actingAs($u)->get('/laporan/export?jenis=keuangan')->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/laporan/export?jenis=keuangan')->assertRedirect();
    }
}
