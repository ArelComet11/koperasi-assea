<?php

namespace Tests\Feature;

use App\Exceptions\BisnisException;
use App\Models\MutasiStok;
use App\Services\PasokanService;
use App\Services\PengaturanService;
use App\Services\PenerimaanService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class PasokanDanStokTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
    }

    public function test_penerimaan_menambah_stok_fisik_dan_ledger(): void
    {
        $this->terima(16000);
        $r = app(StokService::class)->ringkasan();

        $this->assertSame('16000.00', $r['fisik']);
        $this->assertSame('16000.00', $r['tersedia']);
        $this->assertSame(1, MutasiStok::query()->where('jenis', 'penerimaan')->count());
        $this->assertSame('16000.00', app(PasokanService::class)->ringkasan()['diterima']);
    }

    public function test_penerimaan_melebihi_batas_160000_ditolak_tanpa_override(): void
    {
        $this->terima(150000);

        $this->expectException(BisnisException::class);
        $this->terima(11000); // total 161.000 > 160.000
    }

    public function test_override_batas_pasokan_tercatat(): void
    {
        $this->terima(150000);
        $p = $this->terima(11000, null, true, 'Pasokan tambahan darurat disetujui pengurus');

        $this->assertTrue($p->override_pasokan);
        $this->assertDatabaseHas('aktivitas_admin', ['jenis' => 'pasokan.override']);
    }

    public function test_finance_tidak_dapat_override(): void
    {
        $this->terima(160000);
        $fin = $this->finance();
        $this->actingAs($fin);

        $this->expectException(BisnisException::class);
        app(PenerimaanService::class)->catat([
            'tanggal_penerimaan' => today()->toDateString(), 'nama_pemasok' => 'X', 'liter' => 10,
            'override_pasokan' => true, 'alasan_override' => 'Mencoba melewati batas',
        ], $fin);
    }

    public function test_batas_pasokan_dapat_diubah_lewat_pengaturan(): void
    {
        app(PengaturanService::class)->set('pasokan.batas_bulanan', '1000', $this->admin());

        $this->terima(900);
        $this->expectException(BisnisException::class);
        $this->terima(200);
    }

    public function test_saldo_awal_hanya_sekali(): void
    {
        $this->isiStok(5000);
        $admin = $this->admin();

        $this->expectException(BisnisException::class);
        DB::transaction(fn () => app(StokService::class)->saldoAwal(100, $admin));
    }

    public function test_stok_tidak_boleh_negatif(): void
    {
        $this->isiStok(100);

        $this->expectException(BisnisException::class);
        DB::transaction(fn () => app(StokService::class)->catat(\App\Enums\JenisMutasi::Koreksi, '-200', '0', null, 'uji', $this->admin()));
    }

    public function test_batal_penerimaan_ditolak_bila_stok_sudah_dialokasikan(): void
    {
        $p = $this->terima(1000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $this->actingAs($u);
        $pj = app(\App\Services\PengajuanService::class)->ajukan($a, [
            'kapal_id' => $k->id, 'surat_rekomendasi_id' => $s->id, 'liter_diminta' => 900, 'tanggal_rencana' => today()->toDateString(),
        ]);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(\App\Services\PengajuanService::class)->setujui($pj, $admin);

        $this->expectException(BisnisException::class);
        app(PenerimaanService::class)->batalkan($p, $admin, 'Salah input'); // tersedia hanya 100
    }

    public function test_batal_penerimaan_membuat_entri_pembalik(): void
    {
        $p = $this->terima(1000);
        app(PenerimaanService::class)->batalkan($p, $this->admin(), 'Salah input');

        $this->assertSame('0.00', app(StokService::class)->ringkasan()['fisik']);
        $this->assertSame(2, MutasiStok::query()->count());
        $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);
    }
}
