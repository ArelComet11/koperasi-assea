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

        $kap = app(LaporanService::class)->bangun('kapal');
        $this->assertSame(1, $kap['ringkasan']['Total kapal']);
        $this->assertSame(500.0, $kap['rows'][0][6]);
    }

    public function test_laporan_yang_dihapus_tidak_lagi_tersedia(): void
    {
        $admin = $this->admin();
        $this->assertSame(['kapal', 'surat', 'penyaluran', 'transaksi', 'keuangan'], array_keys(LaporanService::JENIS));

        foreach (['anggota', 'penerimaan', 'persediaan', 'stok_harian'] as $dihapus) {
            $this->actingAs($admin)->get('/laporan/export?jenis=' . $dihapus)->assertForbidden();
            $this->actingAs($admin)->get('/laporan/cetak?jenis=' . $dihapus)->assertForbidden();
            $this->actingAs($admin)->get('/admin/laporan?jenis=' . $dihapus)
                ->assertOk()
                ->assertSee('Anda tidak memiliki izin untuk membuka jenis laporan tersebut.');
        }
    }

    public function test_export_excel_dan_cetak(): void
    {
        $this->transaksiSelesai();

        $res = $this->actingAs($this->admin())->get('/laporan/export?jenis=transaksi');
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));

        $this->actingAs($this->admin())->get('/laporan/cetak?jenis=keuangan')->assertOk()->assertSee('Laporan Keuangan');

        // Verifikasi struktur visual XLSX Laporan Kapal (kop SPBUN, header hijau di baris 11, tanpa freezePane)
        $lap = app(LaporanService::class)->bangun('kapal');
        $raw = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanExport($lap), \Maatwebsite\Excel\Excel::XLSX);
        $tmp = storage_path('framework/testing/cek-laporan-kapal.xlsx');
        @mkdir(dirname($tmp), 0777, true);
        file_put_contents($tmp, $raw);
        try {
            $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
            $ws = $wb->getActiveSheet();
            $this->assertNull($ws->getFreezePane());
            $this->assertSame('LAPORAN KAPAL', $ws->getCell('A1')->getValue());
            $this->assertSame('SPBUN : KSU ASSEA LORGAN MANDIRI', $ws->getCell('A2')->getValue());
            $this->assertSame('NO SPBUN : 18.208.032', $ws->getCell('A3')->getValue());
            $this->assertSame('KODE', $ws->getCell('A11')->getValue());
            $this->assertSame('1CE836', $ws->getStyle('A11')->getFill()->getStartColor()->getRGB());
            $this->assertNotSame('1CE836', $ws->getStyle('A12')->getFill()->getStartColor()->getRGB());
        } finally {
            @unlink($tmp);
        }

        // Verifikasi struktur visual XLSX & cetak Laporan Surat Rekomendasi (format Realisasi Harian 1..31)
        $this->actingAs($this->admin())->get('/laporan/cetak?jenis=surat')
            ->assertOk()
            ->assertSee('NO LP')
            ->assertSee('REALISASI')
            ->assertSee('Total Vs Rekom');

        $lapSurat = app(LaporanService::class)->bangun('surat');
        $rawSurat = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanSuratRekomendasiExport($lapSurat), \Maatwebsite\Excel\Excel::XLSX);
        $tmpSurat = storage_path('framework/testing/cek-laporan-surat.xlsx');
        file_put_contents($tmpSurat, $rawSurat);
        try {
            $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpSurat);
            $this->assertSame(1, $wb->getSheetCount());
            $ws = $wb->getActiveSheet();
            $this->assertNull($ws->getFreezePane());
            $this->assertSame('NO LP', $ws->getCell('A1')->getValue());
            $this->assertSame('18208032', (string) $ws->getCell('C1')->getValue());
            $this->assertSame('KSU ASSEA LORGAN MANDIRI', $ws->getCell('C2')->getValue());
            $this->assertSame('Jl. Babalan Gg. Tenggiri No. 45', $ws->getCell('C3')->getValue());
            $this->assertSame('REALISASI', $ws->getCell('A4')->getValue());
            $this->assertSame('Penjualan', $ws->getCell('D6')->getValue());
            $this->assertSame('NO', $ws->getCell('A7')->getValue());
            $this->assertSame('Nama', $ws->getCell('B7')->getValue());
            $this->assertSame('Nama Kapal', $ws->getCell('C7')->getValue());
            $this->assertSame('No Rekom', $ws->getCell('D7')->getValue());
            $this->assertSame('Vol Rekom', $ws->getCell('E7')->getValue());
            $this->assertSame('Penyaluran', $ws->getCell('F7')->getValue());
            $this->assertSame(1, (int) $ws->getCell('F8')->getValue());
            $this->assertSame(31, (int) $ws->getCell('AJ8')->getValue());
            $this->assertSame('Total', $ws->getCell('AK7')->getValue());
            $this->assertSame('Total Vs Rekom', $ws->getCell('AL7')->getValue());

            // Nilai penyaluran 500 L tercatat tepat di kolom tanggal hari ini; Vol Rekom 1000 -> Total Vs Rekom -500
            $hariCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + now()->day);
            $this->assertEquals(1000.0, (float) $ws->getCell('E9')->getValue());
            $this->assertEquals(500.0, (float) $ws->getCell("{$hariCol}9")->getValue());
            $this->assertEquals(500.0, (float) $ws->getCell('AK9')->getCalculatedValue());
            $this->assertEquals(-500.0, (float) $ws->getCell('AL9')->getCalculatedValue());

            // Baris Total bawah (row 10)
            $this->assertSame('Total', $ws->getCell('A10')->getValue());
            $this->assertEquals(500.0, (float) $ws->getCell("{$hariCol}10")->getCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell('AK10')->getCalculatedValue());
            $this->assertEquals(-500.0, (float) $ws->getCell('AL10')->getCalculatedValue());
        } finally {
            @unlink($tmpSurat);
        }

        // Verifikasi multi-bulan menghasilkan 1 worksheet per bulan
        $lapMulti = app(LaporanService::class)->bangun('surat', ['dari' => '2026-01-01', 'sampai' => '2026-03-31']);
        $exportMulti = new \App\Exports\LaporanSuratRekomendasiExport($lapMulti);
        $this->assertCount(3, $exportMulti->sheets());
    }

    public function test_finance_hanya_boleh_laporan_keuangan_dan_transaksi(): void
    {
        $fin = $this->finance();
        $this->assertSame(['transaksi', 'keuangan'], array_keys(LaporanService::jenisUntuk($fin)));

        $this->actingAs($fin)->get('/laporan/export?jenis=kapal')->assertForbidden();
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
