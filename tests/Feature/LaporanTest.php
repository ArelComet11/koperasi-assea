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

        $jualHarian = app(LaporanService::class)->bangun('penjualan_harian');
        $this->assertSame('Laporan Penjualan', $jualHarian['judul']);
        $this->assertSame(10000.0, $jualHarian['ringkasan']['Total Penerimaan (L)']);
        $this->assertSame(500.0, $jualHarian['ringkasan']['Total Penjualan (L)']);

        $jual = app(LaporanService::class)->bangun('penjualan');
        $this->assertSame('Rekap Penjualan Sektor Perikanan', $jual['judul']);
        $this->assertSame(500.0, $jual['ringkasan']['Total liter terjual']);

        $stokSolar = app(LaporanService::class)->bangun('stok_solar');
        $this->assertSame('Laporan Stock Produk Solar', $stokSolar['judul']);
        $this->assertSame(10000.0, $stokSolar['ringkasan']['Total Penerimaan (L)']);
        $this->assertSame(500.0, $stokSolar['ringkasan']['Total Penjualan (L)']);
        $this->assertSame(9500.0, $stokSolar['ringkasan']['Stock Akhir (L)']);

        $kap = app(LaporanService::class)->bangun('kapal');
        $this->assertSame(1, $kap['ringkasan']['Total kapal']);
        $this->assertNotContains('Jenis', $kap['headings']);
        $this->assertContains('Nomor Rekomendasi', $kap['headings']);
        $this->assertContains('GT', $kap['headings']);
        $this->assertSame(500.0, $kap['rows'][0][6]);
    }

    public function test_laporan_yang_dihapus_tidak_lagi_tersedia(): void
    {
        $admin = $this->admin();
        $this->assertSame(['kapal', 'surat', 'penjualan_harian', 'penjualan', 'stok_solar', 'realisasi', 'keuangan'], array_keys(LaporanService::JENIS));

        foreach (['anggota', 'penerimaan', 'penyaluran', 'persediaan', 'stok_harian', 'transaksi'] as $dihapus) {
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

        $res = $this->actingAs($this->admin())->get('/laporan/export?jenis=realisasi');
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));

        $this->actingAs($this->admin())->get('/laporan/cetak?jenis=keuangan')->assertOk()->assertSee('Laporan Keuangan');
        $this->actingAs($this->admin())->get('/laporan/cetak?jenis=realisasi')
            ->assertOk()
            ->assertSee('Data Realisasi Penerimaan dan Penjualan BBM')
            ->assertSee('Saldo Akhir Pemeriksaan Fisik')
            ->assertSee('SYAFRIYANI');

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
            $this->assertEquals(500.0, (float) $ws->getCell('AK9')->getOldCalculatedValue());
            $this->assertEquals(-500.0, (float) $ws->getCell('AL9')->getOldCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell('AK9')->getCalculatedValue());
            $this->assertEquals(-500.0, (float) $ws->getCell('AL9')->getCalculatedValue());

            // Baris Total bawah (row 10)
            $this->assertSame('Total', $ws->getCell('A10')->getValue());
            $this->assertEquals(500.0, (float) $ws->getCell("{$hariCol}10")->getOldCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell('AK10')->getOldCalculatedValue());
            $this->assertEquals(-500.0, (float) $ws->getCell('AL10')->getOldCalculatedValue());
        } finally {
            @unlink($tmpSurat);
        }

        // Verifikasi multi-bulan Surat Rekomendasi menghasilkan 1 worksheet per bulan
        $lapMulti = app(LaporanService::class)->bangun('surat', ['dari' => '2026-01-01', 'sampai' => '2026-03-31']);
        $exportMulti = new \App\Exports\LaporanSuratRekomendasiExport($lapMulti);
        $this->assertCount(3, $exportMulti->sheets());

        // Verifikasi struktur visual XLSX & cetak Laporan Penjualan (Lampiran 6 — 13 kolom harian Solar/Bio Solar)
        $this->actingAs($this->admin())->get('/laporan/cetak?jenis=penjualan_harian')
            ->assertOk()
            ->assertSee('LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR')
            ->assertSee('Laporan Penjualan Produk : Solar/Bio Solar')
            ->assertSee('Nama Kapal')
            ->assertSee('Melaut')
            ->assertSee('SYAFRIYANI');

        $lapJualHarian = app(LaporanService::class)->bangun('penjualan_harian');
        $rawJualHarian = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanPenjualanHarianExport($lapJualHarian), \Maatwebsite\Excel\Excel::XLSX);
        $tmpJualHarian = storage_path('framework/testing/cek-laporan-penjualan-harian.xlsx');
        file_put_contents($tmpJualHarian, $rawJualHarian);
        try {
            $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpJualHarian);
            $this->assertSame(1, $wb->getSheetCount());
            $ws = $wb->getActiveSheet();
            $this->assertNull($ws->getFreezePane());
            $this->assertSame('LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR', $ws->getCell('A1')->getValue());
            $this->assertSame('SPBUN : KSU ASSEA LORGAN MANDIRI', $ws->getCell('A2')->getValue());
            $this->assertSame('NO SPBUN : 18.208.032', $ws->getCell('A3')->getValue());
            $this->assertStringStartsWith('PERIODE : ', (string) $ws->getCell('A4')->getValue());
            $this->assertSame('Laporan Penjualan Produk : Solar/Bio Solar', $ws->getCell('A6')->getValue());
            $this->assertSame('No', $ws->getCell('A7')->getValue());
            $this->assertSame('Tanggal', $ws->getCell('B7')->getValue());
            $this->assertSame('Keterangan', $ws->getCell('C7')->getValue());
            $this->assertSame('Nama Kapal', $ws->getCell('C8')->getValue());
            $this->assertSame('Nama Pemilik', $ws->getCell('D8')->getValue());
            $this->assertSame('GT', $ws->getCell('E8')->getValue());
            $this->assertSame('PK', $ws->getCell('F8')->getValue());
            $this->assertSame('Rekomendasi', $ws->getCell('G8')->getValue());
            $this->assertSame('Tujuan', $ws->getCell('M8')->getValue());

            // Baris tanggal hari ini memuat transaksi 500 L, penerimaan 10000 L, stok akhir 9500 L, Tujuan Melaut
            $rowHariIni = 8 + now()->day;
            $this->assertSame(now()->day, (int) $ws->getCell("A{$rowHariIni}")->getValue());
            $this->assertEquals(1000.0, (float) $ws->getCell("H{$rowHariIni}")->getValue());
            $this->assertEquals(0.0, (float) $ws->getCell("I{$rowHariIni}")->getValue());
            $this->assertEquals(10000.0, (float) $ws->getCell("J{$rowHariIni}")->getValue());
            $this->assertEquals(500.0, (float) $ws->getCell("K{$rowHariIni}")->getValue());
            $this->assertEquals(9500.0, (float) $ws->getCell("L{$rowHariIni}")->getValue());
            $this->assertSame('Melaut', $ws->getCell("M{$rowHariIni}")->getValue());

            // Blok tanda tangan kanan bawah
            $sigStart = 8 + now()->daysInMonth + 3;
            $this->assertStringStartsWith('Pangkalan Brandan, ', (string) $ws->getCell("K{$sigStart}")->getValue());
            $this->assertSame('SYAFRIYANI', $ws->getCell('K' . ($sigStart + 5))->getValue());
            $this->assertSame('KETUA', $ws->getCell('K' . ($sigStart + 6))->getValue());
        } finally {
            @unlink($tmpJualHarian);
        }

        // Verifikasi multi-bulan Laporan Penjualan menghasilkan 1 worksheet per bulan (JAN..MAR)
        $lapHarianMulti = app(LaporanService::class)->bangun('penjualan_harian', ['dari' => '2026-01-01', 'sampai' => '2026-03-31']);
        $sheetsHarian = (new \App\Exports\LaporanPenjualanHarianExport($lapHarianMulti))->sheets();
        $this->assertCount(3, $sheetsHarian);
        $this->assertSame(['JAN', 'FEB', 'MAR'], array_map(fn ($s) => $s->title(), $sheetsHarian));

        // Verifikasi struktur visual XLSX & cetak Rekap Penjualan Sektor Perikanan (Matriks 1..31 + TTD Ketua)
        $this->actingAs($this->admin())->get('/laporan/cetak?jenis=penjualan')
            ->assertOk()
            ->assertSee('REKAPITULASI LAPORAN PENJUALAN KE SEKTOR PERIKANAN')
            ->assertSee('M.SOLAR')
            ->assertSee('JUMLAH')
            ->assertSee('SYAFRIYANI');

        $lapJual = app(LaporanService::class)->bangun('penjualan');
        $rawJual = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanPenjualanExport($lapJual), \Maatwebsite\Excel\Excel::XLSX);
        $tmpJual = storage_path('framework/testing/cek-laporan-penjualan.xlsx');
        file_put_contents($tmpJual, $rawJual);
        try {
            $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpJual);
            $this->assertSame(1, $wb->getSheetCount());
            $ws = $wb->getActiveSheet();
            $this->assertNull($ws->getFreezePane());
            $this->assertSame('REKAPITULASI LAPORAN PENJUALAN KE SEKTOR PERIKANAN', $ws->getCell('A1')->getValue());
            $this->assertSame('SPBU-N KOPERASI ASSEA LORGAN MANDIRI', $ws->getCell('A2')->getValue());
            $this->assertSame('No. SPBU Kompak', $ws->getCell('A4')->getValue());
            $this->assertSame(': 18.208.032', $ws->getCell('C4')->getValue());
            $this->assertSame('Alamat', $ws->getCell('A5')->getValue());
            $this->assertSame('Bulan', $ws->getCell('AC4')->getValue());
            $this->assertSame('Tahun', $ws->getCell('AC5')->getValue());
            $this->assertSame('M.SOLAR', $ws->getCell('AG6')->getValue());
            $this->assertSame('No', $ws->getCell('A7')->getValue());
            $this->assertSame('Nama', $ws->getCell('B7')->getValue());
            $this->assertSame('Nama Kapal', $ws->getCell('C7')->getValue());
            $this->assertSame('No. Rekomendasi', $ws->getCell('D7')->getValue());
            $this->assertSame('Tanggal Penjualan', $ws->getCell('E7')->getValue());
            $this->assertSame(1, (int) $ws->getCell('E8')->getValue());
            $this->assertSame(31, (int) $ws->getCell('AI8')->getValue());
            $this->assertSame('Total', $ws->getCell('AJ7')->getValue());

            $hariJualCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + now()->day);
            $hariKosong = now()->day === 1 ? 2 : 1;
            $hariKosongCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + $hariKosong);
            $this->assertEquals(500.0, (float) $ws->getCell("{$hariJualCol}9")->getValue());
            $this->assertNull($ws->getCell("{$hariKosongCol}9")->getValue());
            // Nilai cache rumus (<v>) harus langsung terisi (bukan 0) agar viewer / Google Sheets / pratinjau langsung akurat
            $this->assertEquals(500.0, (float) $ws->getCell('AJ9')->getOldCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell('AJ9')->getCalculatedValue());
            $this->assertSame('JUMLAH', $ws->getCell('A10')->getValue());
            $this->assertEquals(500.0, (float) $ws->getCell("{$hariJualCol}10")->getOldCalculatedValue());
            $this->assertEquals(0.0, (float) $ws->getCell("{$hariKosongCol}10")->getOldCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell('AJ10')->getOldCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell('AJ10')->getCalculatedValue());

            // Blok tanda tangan kanan bawah (row 13..20)
            $this->assertStringStartsWith('Tanggal, ', (string) $ws->getCell('AC13')->getValue());
            $this->assertSame('Hormat kami,', $ws->getCell('AC14')->getValue());
            $this->assertSame('SPBU-N KOPERASI ASSEA LORGAN MANDIRI', $ws->getCell('AC15')->getValue());
            $this->assertSame('SYAFRIYANI', $ws->getCell('AC19')->getValue());
            $this->assertSame('Ketua', $ws->getCell('AC20')->getValue());
        } finally {
            @unlink($tmpJual);
        }

        // Verifikasi multi-bulan Penjualan menghasilkan 1 worksheet per bulan
        $lapJualMulti = app(LaporanService::class)->bangun('penjualan', ['dari' => '2026-01-01', 'sampai' => '2026-04-30']);
        $this->assertCount(4, (new \App\Exports\LaporanPenjualanExport($lapJualMulti))->sheets());

        // Verifikasi struktur visual XLSX & cetak Laporan Stock Produk Solar (6 kolom harian + TOTAL SUM)
        $this->actingAs($this->admin())->get('/laporan/cetak?jenis=stok_solar')
            ->assertOk()
            ->assertSee('LAPORAN STOCK PRODUK SOLAR/BIO SOLAR')
            ->assertSee('Laporan Stock Produk : Solar/Bio Solar')
            ->assertSee('STOCK AWAL')
            ->assertSee('STOCK AKHIR');

        $lapStok = app(LaporanService::class)->bangun('stok_solar');
        $rawStok = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanStokSolarExport($lapStok), \Maatwebsite\Excel\Excel::XLSX);
        $tmpStok = storage_path('framework/testing/cek-laporan-stok-solar.xlsx');
        file_put_contents($tmpStok, $rawStok);
        try {
            $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpStok);
            $this->assertSame(1, $wb->getSheetCount());
            $ws = $wb->getActiveSheet();
            $this->assertNull($ws->getFreezePane());
            $this->assertSame('LAPORAN STOCK PRODUK SOLAR/BIO SOLAR', $ws->getCell('A1')->getValue());
            $this->assertSame('SPBUN : KSU ASSEA LORGAN MANDIRI', $ws->getCell('A2')->getValue());
            $this->assertSame('NO SPBUN : 18.208.032', $ws->getCell('A3')->getValue());
            $this->assertStringStartsWith('PERIODE : ', (string) $ws->getCell('A4')->getValue());
            $this->assertSame('Laporan Stock Produk : Solar/Bio Solar', $ws->getCell('A6')->getValue());
            $this->assertSame('NO', $ws->getCell('A7')->getValue());
            $this->assertSame('TANGGAL', $ws->getCell('B7')->getValue());
            $this->assertSame('STOCK AWAL', $ws->getCell('C7')->getValue());
            $this->assertSame('PENERIMAAN', $ws->getCell('D7')->getValue());
            $this->assertSame('PENJUALAN', $ws->getCell('E7')->getValue());
            $this->assertSame('STOCK AKHIR', $ws->getCell('F7')->getValue());
            $this->assertSame('1BF950', $ws->getStyle('A7')->getFill()->getStartColor()->getRGB());

            $rowStokHariIni = 7 + now()->day;
            $this->assertSame(now()->day, (int) $ws->getCell("A{$rowStokHariIni}")->getValue());
            $this->assertEquals(0.0, (float) $ws->getCell("C{$rowStokHariIni}")->getValue());
            $this->assertEquals(10000.0, (float) $ws->getCell("D{$rowStokHariIni}")->getValue());
            $this->assertEquals(500.0, (float) $ws->getCell("E{$rowStokHariIni}")->getValue());
            $this->assertEquals(9500.0, (float) $ws->getCell("F{$rowStokHariIni}")->getValue());

            $totalRowStok = 8 + now()->daysInMonth;
            $this->assertSame('TOTAL', $ws->getCell("A{$totalRowStok}")->getValue());
            $this->assertEquals(10000.0, (float) $ws->getCell("D{$totalRowStok}")->getOldCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell("E{$totalRowStok}")->getOldCalculatedValue());
            $this->assertEquals(10000.0, (float) $ws->getCell("D{$totalRowStok}")->getCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell("E{$totalRowStok}")->getCalculatedValue());
        } finally {
            @unlink($tmpStok);
        }

        // Verifikasi multi-bulan Laporan Stock Produk Solar menghasilkan worksheet JAN..MAR
        $lapStokMulti = app(LaporanService::class)->bangun('stok_solar', ['dari' => '2026-01-01', 'sampai' => '2026-03-31']);
        $sheetsStok = (new \App\Exports\LaporanStokSolarExport($lapStokMulti))->sheets();
        $this->assertCount(3, $sheetsStok);
        $this->assertSame(['JAN', 'FEB', 'MAR'], array_map(fn ($s) => $s->title(), $sheetsStok));

        // Verifikasi struktur visual XLSX Laporan Data Realisasi Penerimaan dan Penjualan BBM (12 bulan + Total + TTD)
        $lapReal = app(LaporanService::class)->bangun('realisasi');
        $rawReal = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanRealisasiExport($lapReal), \Maatwebsite\Excel\Excel::XLSX);
        $tmpReal = storage_path('framework/testing/cek-laporan-realisasi.xlsx');
        file_put_contents($tmpReal, $rawReal);
        try {
            $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpReal);
            $ws = $wb->getActiveSheet();
            $this->assertNull($ws->getFreezePane());
            $this->assertStringContainsString('Data Realisasi Penerimaan dan Penjualan BBM', (string) $ws->getCell('A1')->getValue());
            $this->assertSame('Nomor Penyalur', $ws->getCell('A2')->getValue());
            $this->assertSame(': 18.208.032', $ws->getCell('B2')->getValue());
            $this->assertSame(': KSU ASSEA LORGAN MANDIRI', $ws->getCell('B3')->getValue());
            $this->assertSame(': Syafriyani', $ws->getCell('B5')->getValue());
            $this->assertSame('Bulan', $ws->getCell('A7')->getValue());
            $this->assertSame('Saldo Awal', $ws->getCell('B7')->getValue());
            $this->assertSame('Penerimaan', $ws->getCell('C7')->getValue());
            $this->assertSame('Penjualan', $ws->getCell('D7')->getValue());
            $this->assertSame('Saldo Akhir Administrasi', $ws->getCell('E7')->getValue());
            $this->assertSame('Saldo Akhir Pemeriksaan Fisik', $ws->getCell('F7')->getValue());

            // Bulan transaksi berjalan (row 7 + bulan ini): terima 10000, jual 500 -> saldo akhir 9500
            $bulanRow = 7 + now()->month;
            $this->assertEquals(10000.0, (float) $ws->getCell("C{$bulanRow}")->getValue());
            $this->assertEquals(500.0, (float) $ws->getCell("D{$bulanRow}")->getValue());
            $this->assertEquals(9500.0, (float) $ws->getCell("E{$bulanRow}")->getCalculatedValue());
            $this->assertEquals(9500.0, (float) $ws->getCell("F{$bulanRow}")->getCalculatedValue());

            // Baris Total (row 20) + TTD (row 23..30)
            $this->assertSame('Total', $ws->getCell('A20')->getValue());
            $this->assertEquals(10000.0, (float) $ws->getCell('C20')->getOldCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell('D20')->getOldCalculatedValue());
            $this->assertEquals(9500.0, (float) $ws->getCell('E20')->getOldCalculatedValue());
            $this->assertEquals(9500.0, (float) $ws->getCell('F20')->getOldCalculatedValue());
            $this->assertEquals(10000.0, (float) $ws->getCell('C20')->getCalculatedValue());
            $this->assertEquals(500.0, (float) $ws->getCell('D20')->getCalculatedValue());
            $this->assertEquals(9500.0, (float) $ws->getCell('E20')->getCalculatedValue());
            $this->assertEquals(9500.0, (float) $ws->getCell('F20')->getCalculatedValue());
            $this->assertSame('Tanggal, 31 Desember ' . now()->year, $ws->getCell('D23')->getValue());
            $this->assertSame('Hormat kami,', $ws->getCell('D24')->getValue());
            $this->assertSame('SPBU-N KOPERASI ASSEA LORGAN MANDIRI', $ws->getCell('D25')->getValue());
            $this->assertSame('SYAFRIYANI', $ws->getCell('D29')->getValue());
            $this->assertSame('Ketua', $ws->getCell('D30')->getValue());
        } finally {
            @unlink($tmpReal);
        }
    }

    public function test_banyak_transaksi_pada_tanggal_sama_tampil_semua_di_laporan_penjualan(): void
    {
        $this->terima(16000);
        $admin = $this->admin();

        $daftar = [
            ['A1', 'Abdul Manap', 'P. Medan 34', 200],
            ['A2', 'Abdul Rajak', 'Abdul Rajak', 350],
            ['A3', 'Adaruddin', 'KM Anugra', 300],
        ];

        foreach ($daftar as [$kode, $namaAgt, $namaKapal, $liter]) {
            [$a, $k, $s, $u] = $this->buatAnggota($kode);
            $a->update(['nama_lengkap' => $namaAgt]);
            $k->update(['nama_kapal' => $namaKapal, 'nama_pemilik' => $namaAgt, 'ukuran_gt' => 1]);
            $this->actingAs($u);
            $p = app(PengajuanService::class)->ajukan($a, [
                'kapal_id' => $k->id,
                'surat_rekomendasi_id' => $s->id,
                'liter_diminta' => $liter,
                'tanggal_rencana' => today()->toDateString(),
            ]);
            $this->actingAs($admin);
            app(PengajuanService::class)->setujui($p, $admin);
            app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai');
            app(PenyaluranService::class)->catat($p, $liter, $admin);
        }

        $lap = app(LaporanService::class)->bangun('penjualan_harian');
        $rows = $lap['bulanan'][0]['rows'];
        // Total baris = jumlah hari dalam bulan + 2 baris tambahan (karena hari ini ada 3 transaksi)
        $this->assertCount(now()->daysInMonth + 2, $rows);

        $idxAwal = now()->day - 1;
        $baris1 = $rows[$idxAwal];
        $baris2 = $rows[$idxAwal + 1];
        $baris3 = $rows[$idxAwal + 2];

        // Baris 1 memuat tanggal, stok awal 0, penerimaan 16000, penjualan 200, stok akhir 15150 (16000 - 850)
        $this->assertFalse($baris1['is_lanjutan']);
        $this->assertSame('P. Medan 34', $baris1['nama_kapal']);
        $this->assertSame('Abdul Manap', $baris1['nama_pemilik']);
        $this->assertSame(0.0, $baris1['stock_awal']);
        $this->assertSame(16000.0, $baris1['penerimaan']);
        $this->assertSame(200.0, $baris1['penjualan']);
        $this->assertSame(15150.0, $baris1['stock_akhir']);

        // Baris 2 & 3 tetap tampil lengkap pada tanggal yang sama dengan is_lanjutan=true & tanggal/stok dikosongkan
        $this->assertTrue($baris2['is_lanjutan']);
        $this->assertNull($baris2['tanggal']);
        $this->assertSame('Abdul Rajak', $baris2['nama_kapal']);
        $this->assertSame(350.0, $baris2['penjualan']);
        $this->assertNull($baris2['stock_awal']);
        $this->assertNull($baris2['penerimaan']);
        $this->assertNull($baris2['stock_akhir']);

        $this->assertTrue($baris3['is_lanjutan']);
        $this->assertNull($baris3['tanggal']);
        $this->assertSame('KM Anugra', $baris3['nama_kapal']);
        $this->assertSame(300.0, $baris3['penjualan']);

        // Verifikasi XLSX memuat semua 3 baris + arsiran abu-abu #D9D9D9 pada kolom I, J, L di baris lanjutan
        $raw = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanPenjualanHarianExport($lap), \Maatwebsite\Excel\Excel::XLSX);
        $tmp = storage_path('framework/testing/cek-multi-transaksi-harian.xlsx');
        @mkdir(dirname($tmp), 0777, true);
        file_put_contents($tmp, $raw);
        try {
            $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
            $ws = $wb->getActiveSheet();
            $r1 = 9 + $idxAwal;
            $r2 = $r1 + 1;
            $r3 = $r1 + 2;

            $this->assertSame('P. Medan 34', $ws->getCell("C{$r1}")->getValue());
            $this->assertSame('Abdul Rajak', $ws->getCell("C{$r2}")->getValue());
            $this->assertSame('KM Anugra', $ws->getCell("C{$r3}")->getValue());
            $this->assertEquals(15150.0, (float) $ws->getCell("L{$r1}")->getValue());
            $this->assertSame('D9D9D9', $ws->getStyle("I{$r2}")->getFill()->getStartColor()->getRGB());
            $this->assertSame('D9D9D9', $ws->getStyle("J{$r2}")->getFill()->getStartColor()->getRGB());
            $this->assertSame('D9D9D9', $ws->getStyle("L{$r2}")->getFill()->getStartColor()->getRGB());
            $this->assertSame('D9D9D9', $ws->getStyle("I{$r3}")->getFill()->getStartColor()->getRGB());
        } finally {
            @unlink($tmp);
        }

        // Verifikasi pratinjau web menampilkan semua 3 kapal
        $this->actingAs($admin)->get('/admin/laporan?jenis=penjualan_harian')
            ->assertOk()
            ->assertSee('P. Medan 34')
            ->assertSee('Abdul Rajak')
            ->assertSee('KM Anugra');
    }

    public function test_rekap_penjualan_hanya_memuat_kapal_yang_bertransaksi_dan_membersihkan_akhiran_nomor_rekom(): void
    {
        $this->transaksiSelesai();
        $suratAktif = \App\Models\SuratRekomendasi::query()->firstOrFail();
        $nomorAsli = $suratAktif->nomor_surat;
        $suratAktif->update(['nomor_surat' => $nomorAsli . '#01']);

        [$agt2, $kapal2, $surat2] = $this->buatAnggota('TANPA-TRX');
        $this->actingAs($this->admin());

        // Rekap Penjualan Sektor Perikanan (Lampiran 1): hanya kapal/surat yang bertransaksi pada bulan tersebut
        $lapJual = app(LaporanService::class)->bangun('penjualan');
        $rowsJual = $lapJual['bulanan'][0]['rows'];
        $this->assertCount(1, $rowsJual);
        $this->assertSame($nomorAsli, $rowsJual[0]['no_rekom']);
        $this->assertNull(collect($rowsJual)->firstWhere('no_rekom', $surat2->nomor_surat));

        // Laporan Surat Rekomendasi (Cek Rekom): tetap menampilkan surat tanpa transaksi & akhiran #01 dibersihkan
        $lapSurat = app(LaporanService::class)->bangun('surat');
        $rowsSurat = $lapSurat['bulanan'][0]['rows'];
        $this->assertCount(2, $rowsSurat);
        $this->assertNotNull(collect($rowsSurat)->firstWhere('no_rekom', $nomorAsli));
        $barisKosongSurat = collect($rowsSurat)->firstWhere('no_rekom', $surat2->nomor_surat);
        $this->assertNotNull($barisKosongSurat);
        $this->assertSame(0.0, $barisKosongSurat['total']);
        $this->assertSame(-1000.0, $barisKosongSurat['total_vs_rekom']);
    }

    public function test_finance_hanya_boleh_laporan_keuangan_dan_realisasi(): void
    {
        $fin = $this->finance();
        $this->assertSame(['realisasi', 'keuangan'], array_keys(LaporanService::jenisUntuk($fin)));

        $this->actingAs($fin)->get('/laporan/export?jenis=kapal')->assertForbidden();
        $this->actingAs($fin)->get('/laporan/export?jenis=realisasi')->assertOk();
        $this->actingAs($fin)->get('/laporan/export?jenis=keuangan')->assertOk();
    }

    public function test_anggota_tidak_dapat_mengakses_laporan(): void
    {
        [, , , $u] = $this->buatAnggota();

        $this->actingAs($u)->get('/laporan/export?jenis=keuangan')->assertForbidden();
    }

    public function test_tujuan_melaut_dari_pengajuan_tampil_di_laporan_penjualan(): void
    {
        $admin = $this->admin();
        $this->isiStok(10000);
        [$a, $k, $s, $u] = $this->buatAnggota('ANG-TUJ');

        $this->actingAs($u);
        $p = app(PengajuanService::class)->ajukan($a, [
            'kapal_id' => $k->id,
            'surat_rekomendasi_id' => $s->id,
            'liter_diminta' => 250,
            'tanggal_rencana' => today()->toDateString(),
            'tujuan' => 'Percobaan Ubah Tujuan',
        ]);
        $this->assertSame('Melaut', $p->tujuan);

        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        $p->refresh();
        app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai');
        app(PenyaluranService::class)->catat($p, 250, $admin);

        $lap = app(LaporanService::class)->bangun('penjualan_harian');
        $baris = collect($lap['bulanan'][0]['rows'])->firstWhere('nama_kapal', $k->nama_kapal);
        $this->assertNotNull($baris);
        $this->assertSame('Melaut', $baris['tujuan']);

        $this->actingAs($admin)->get('/laporan/cetak?jenis=penjualan_harian')
            ->assertOk()
            ->assertSee('Melaut')
            ->assertDontSee('Percobaan Ubah Tujuan');
    }

    public function test_semua_export_laporan_rata_tengah_dan_no_rekom_angka_tidak_jadi_notasi_ilmiah(): void
    {
        $admin = $this->admin();
        $this->isiStok(10000);
        [$a, $k, $s, $u] = $this->buatAnggota('ANG-CTR');
        $s->update(['nomor_surat' => '202393010301']);

        $this->actingAs($u);
        $p = app(PengajuanService::class)->ajukan($a, [
            'kapal_id' => $k->id,
            'surat_rekomendasi_id' => $s->id,
            'liter_diminta' => 100,
            'tanggal_rencana' => today()->toDateString(),
        ]);
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        $p->refresh();
        app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai');
        app(PenyaluranService::class)->catat($p, 100, $admin);

        $svc = app(LaporanService::class);
        $cek = [
            ['kapal', \App\Exports\LaporanExport::class, 'B12', 'D12'],
            ['keuangan', \App\Exports\LaporanExport::class, 'A12', 'C12'],
            ['surat', \App\Exports\LaporanSuratRekomendasiExport::class, 'B9', 'D9'],
            ['penjualan_harian', \App\Exports\LaporanPenjualanHarianExport::class, 'C' . (8 + now()->day), 'G' . (8 + now()->day)],
            ['penjualan', \App\Exports\LaporanPenjualanExport::class, 'B9', 'D9'],
            ['stok_solar', \App\Exports\LaporanStokSolarExport::class, 'C8', 'F8'],
            ['realisasi', \App\Exports\LaporanRealisasiExport::class, 'A8', 'F8'],
        ];

        $center = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;
        foreach ($cek as [$jenis, $exportClass, $sel1, $sel2]) {
            $lap = $svc->bangun($jenis);
            $raw = \Maatwebsite\Excel\Facades\Excel::raw(new $exportClass($lap), \Maatwebsite\Excel\Excel::XLSX);
            $tmp = storage_path("framework/testing/cek-center-{$jenis}.xlsx");
            @mkdir(dirname($tmp), 0777, true);
            file_put_contents($tmp, $raw);
            try {
                $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
                if ($jenis === 'kapal' || $jenis === 'keuangan') {
                    // Cari baris data pertama tepat di bawah header hijau
                    $headerRow = 7;
                    for ($r = 7; $r <= $ws->getHighestRow(); $r++) {
                        if ($ws->getStyle("A{$r}")->getFill()->getStartColor()->getRGB() === '1CE836') {
                            $headerRow = $r;
                            break;
                        }
                    }
                    $dataRow = $headerRow + 1;
                    $sel1 = 'B' . $dataRow;
                    $sel2 = ($jenis === 'kapal' ? 'D' : 'C') . $dataRow;
                }
                $this->assertSame($center, $ws->getStyle($sel1)->getAlignment()->getHorizontal(), "Sel {$sel1} pada {$jenis} harus rata tengah");
                $this->assertSame($center, $ws->getStyle($sel2)->getAlignment()->getHorizontal(), "Sel {$sel2} pada {$jenis} harus rata tengah");
                if (in_array($jenis, ['kapal', 'surat', 'penjualan_harian', 'penjualan'], true)) {
                    $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $ws->getCell($sel2)->getDataType(), "No rekom pada {$jenis} harus bertipe teks");
                    $this->assertSame('202393010301', (string) $ws->getCell($sel2)->getValue());
                }
            } finally {
                @unlink($tmp);
            }
        }
    }

    public function test_laporan_surat_12_bulan_tutup_buku_menampilkan_tab_per_bulan_dan_export_12_sheet(): void
    {
        $this->transaksiSelesai();
        $admin = $this->admin();
        $tahun = now()->year;

        // Pratinjau web 12 bulan (Jan–Des) memakai tab per bulan dan tidak memuat 12 tabel penuh sekaligus
        $this->actingAs($admin)
            ->get("/admin/laporan?jenis=surat&dari={$tahun}-01-01&sampai={$tahun}-12-31")
            ->assertOk()
            ->assertSee('Rentang')
            ->assertSee('12 bulan')
            ->assertSee('12 sheet');

        // Export Excel 12 bulan tetap menghasilkan 12 sheet lengkap (Januari–Desember)
        $lap12 = app(LaporanService::class)->bangun('surat', ['dari' => "{$tahun}-01-01", 'sampai' => "{$tahun}-12-31"]);
        $this->assertCount(12, $lap12['bulanan']);
        $this->assertCount(12, (new \App\Exports\LaporanSuratRekomendasiExport($lap12))->sheets());
        // Bulan yang ada transaksi memuat surat aktif; bulan lain tanpa transaksi/tanpa mulai berlaku di bulan tsb tidak membludak
        $bulanIniIdx = now()->month - 1;
        $this->assertCount(1, $lap12['bulanan'][$bulanIniIdx]['rows']);
        $bulanKosongIdx = now()->month === 1 ? 11 : (now()->month === 2 ? 11 : 1);
        $this->assertCount(0, $lap12['bulanan'][$bulanKosongIdx]['rows']);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/laporan/export?jenis=keuangan')->assertRedirect();
    }
}
