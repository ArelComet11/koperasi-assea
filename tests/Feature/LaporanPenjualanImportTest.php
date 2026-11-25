<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Laporan;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\PenerimaanBbm;
use App\Models\PenyaluranBbm;
use App\Models\SuratRekomendasi;
use App\Services\LaporanPenjualanImportService;
use App\Services\LaporanService;
use App\Services\StokService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class LaporanPenjualanImportTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
    }

    public function test_import_csv_lampiran_6_multi_bulan_terhubung_ke_semua_laporan_dan_idempoten(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Siapkan 1 anggota + kapal + surat dengan akhiran #01 (seperti data multi-kapal asli)
        [$agtAda, $kapalAda, $suratAda] = $this->buatAnggota('ANG-ADA');
        $agtAda->update(['nama_lengkap' => 'Junaidi']);
        $kapalAda->update(['nama_kapal' => 'KM Maulana', 'ukuran_gt' => null]);
        $suratAda->update([
            'nomor_surat' => '1503-KAB/12/12.05/PERIKANAN/JBT/V/2026#01',
            'berlaku_mulai' => '2026-05-01',
            'berlaku_sampai' => '2026-07-31',
            'kuota_bulanan_liter' => 1000,
        ]);

        // Catat 1 penerimaan di 2026-01-06 lebih dulu -> import tidak boleh menduplikasi penerimaan tanggal ini
        app(\App\Services\PenerimaanService::class)->catat([
            'tanggal_penerimaan' => '2026-01-06',
            'nama_pemasok' => 'PT Pertamina Patra Niaga',
            'liter' => 16000,
        ], $admin);

        $csv = <<<'CSV'
LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR,,,,,,,,,,,,
SPBUN : KSU ASSEA LORGAN MANDIRI,,,,,,,,,,,,
NO SPBUN : 18.208.032,,,,,,,,,,,,
PERIODE : JANUARI 2026,,,,,,,,,,,,
,,,,,,,,,,,,
Laporan Penjualan Produk : Solar/Bio Solar,,,,,,,,,,,,
No,Tanggal,Keterangan,,,,,,,,,,
,,Nama Kapal,Nama Pemilik,GT,PK,Rekomendasi,"JML LTR Yang Direkomendasikan (Liter)","STOCK AWAL (Liter)",PENERIAMAN ( Liter ),PENJUALAN ( Liter ),"STOCK AKHIR (Liter)",Tujuan
1,2026-01-05,,,,,,,,,,,
2,2026-01-06,,M. Johar,2,23,523-02/DISKAN/2026,10000,0,16000,1000,14500,Melaut
3,,,M. Yunus,2,23,523-02/DISKAN/2026,10000,,,500,,Melaut
4,2026-01-31,,,,,,,,,,,
232,,,Buyung,2,23,523-03/DPK-LKT/2025,5000,,,1000,,Melaut
,,,,,,,,,,Pangkalan Brandan, 31 Januari 2026,,
,,,,,,,,,,SYAFRIYANI,,
,,,,,,,,,,KETUA,,
LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR,,,,,,,,,,,,
SPBUN : KSU ASSEA LORGAN MANDIRI,,,,,,,,,,,,
NO SPBUN : 18.208.032,,,,,,,,,,,,
PERIODE : MEI 2026,,,,,,,,,,,,
,,,,,,,,,,,,
Laporan Penjualan Produk : Solar/Bio Solar,,,,,,,,,,,,
No,Tanggal,Keterangan,,,,,,,,,,
,,Nama Kapal,Nama Pemilik,GT,PK,Rekomendasi,"JML LTR Yang Direkomendasikan (Liter)","STOCK AWAL (Liter)",PENERIAMAN ( Liter ),PENJUALAN ( Liter ),"STOCK AKHIR (Liter)",Tujuan
1,2026-05-14,KM Maulana,Junaidi,4,,1503-KAB/12/12.05/PERIKANAN/JBT/V/2026,1000,0,16000,250,15500,Melaut
2,,KM Nurhidayah,Junaidi,6,,1503-KAB/12/12.05/PERIKANAN/JBT/V/2026,1000,,,250,,Melaut
232,,,,2,23,523-03/DPK-LKT/2025,5000,,,1000,,Melaut
CSV;

        $tmp = storage_path('framework/testing/import-lampiran6.csv');
        @mkdir(dirname($tmp), 0777, true);
        file_put_contents($tmp, $csv);

        try {
            $svc = app(LaporanPenjualanImportService::class);
            $hasil = $svc->impor($tmp, $admin, true, true, true);

            // 4 penjualan sah (Jan: M. Johar 1000 + M. Yunus 500; Mei: KM Maulana 250 + KM Nurhidayah 250), baris 232 sisa template diabaikan
            $this->assertSame(4, $hasil['penjualan']);
            $this->assertSame('2000.00', $hasil['liter_penjualan']);
            // Hanya 2026-05-14 yang penerimaan baru (2026-01-06 sudah ada -> dilewati)
            $this->assertSame(1, $hasil['penerimaan']);
            $this->assertSame('16000.00', $hasil['liter_penerimaan']);
            $this->assertSame(0, $hasil['duplikat_dilewati']);
            $this->assertSame(2, $hasil['anggota_baru']); // M. Johar & M. Yunus (Junaidi sudah ada)
            $this->assertSame(3, $hasil['kapal_baru']);   // M. Johar, M. Yunus, KM Nurhidayah (KM Maulana sudah ada)
            $this->assertSame(3, $hasil['surat_baru']);   // 2 surat 523-02 (#01) + KM Nurhidayah (#02)
            $this->assertSame([], $hasil['gagal']);

            // GT kapal lama yang tadinya kosong otomatis terisi 4.00
            $this->assertSame('4.00', (string) $kapalAda->fresh()->ukuran_gt);
            // Total penerimaan tetap 2 hari (32.000 L), stok fisik akhir 30.000 L, ledger 100% konsisten
            $this->assertSame(2, PenerimaanBbm::count());
            $this->assertSame('30000.00', app(StokService::class)->ringkasan()['fisik']);
            $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);

            // Terhubung ke Laporan Penjualan, Rekap Sektor Perikanan, dan Laporan Stock Produk Solar
            $lapHarian = app(LaporanService::class)->bangun('penjualan_harian', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']);
            $this->assertSame(500.0, $lapHarian['ringkasan']['Total Penjualan (L)']);

            $lapStok = app(LaporanService::class)->bangun('stok_solar', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']);
            $this->assertSame(16000.0, $lapStok['ringkasan']['Total Penerimaan (L)']);
            $this->assertSame(500.0, $lapStok['ringkasan']['Total Penjualan (L)']);

            // Import ulang file yang sama -> 100% idempoten (0 transaksi baru, 4 duplikat dilewati)
            $ulang = $svc->impor($tmp, $admin, true, true, true);
            $this->assertSame(0, $ulang['penjualan']);
            $this->assertSame(0, $ulang['penerimaan']);
            $this->assertSame(4, $ulang['duplikat_dilewati']);
            $this->assertSame(4, PenyaluranBbm::count());
            $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_import_xlsx_multi_sheet_dan_otorisasi_tombol_di_halaman_laporan(): void
    {
        $admin = $this->admin();
        $finance = $this->finance();

        // Buat file XLSX 2 sheet (JAN & FEB) dengan tanggal serial Excel & baris 232 sisa di FEB
        $spreadsheet = new Spreadsheet();
        $s1 = $spreadsheet->getActiveSheet();
        $s1->setTitle('JAN');
        $s1->fromArray([
            ['LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR'],
            ['SPBUN : KSU ASSEA LORGAN MANDIRI'],
            ['NO SPBUN : 18.208.032'],
            ['PERIODE : JANUARI 2026'],
            [''],
            ['Laporan Penjualan Produk : Solar/Bio Solar'],
            ['No', 'Tanggal', 'Keterangan'],
            ['', '', 'Nama Kapal', 'Nama Pemilik', 'GT', 'PK', 'Rekomendasi', "JML LTR Yang Direkomendasikan\n(Liter)", "STOCK AWAL\n(Liter)", 'PENERIAMAN ( Liter )', 'PENJUALAN ( Liter )', "STOCK AKHIR\n(Liter)", 'Tujuan'],
            [1, '2026-01-06', 'KM Samudra', 'Ahmad', 3, '', '100-KAB/I/2026', 600, 0, 16000, 300, 15700, 'Melaut'],
        ]);

        $s2 = $spreadsheet->createSheet();
        $s2->setTitle('FEB');
        $s2->fromArray([
            ['LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR'],
            ['SPBUN : KSU ASSEA LORGAN MANDIRI'],
            ['NO SPBUN : 18.208.032'],
            ['PERIODE : FEBRUARI 2026'],
            [''],
            ['Laporan Penjualan Produk : Solar/Bio Solar'],
            ['No', 'Tanggal', 'Keterangan'],
            ['', '', 'Nama Kapal', 'Nama Pemilik', 'GT', 'PK', 'Rekomendasi', 'Vol Rekom', 'STOCK AWAL', 'PENERIMAAN', 'PENJUALAN', 'STOCK AKHIR', 'Tujuan'],
            [218, '2026-02-28', 'KM Samudra', 'Ahmad', 3, '', '100-KAB/I/2026', 600, 15700, null, 200, 15500, 'Melaut'],
            [232, '', '', 'Buyung', 2, 23, '523-03/DPK-LKT/2025', 5000, null, null, 1000, null, 'Melaut'],
        ]);

        $tmp = storage_path('framework/testing/import-lampiran6.xlsx');
        @mkdir(dirname($tmp), 0777, true);
        (new Xlsx($spreadsheet))->save($tmp);

        try {
            $this->actingAs($admin);
            $hasil = app(LaporanPenjualanImportService::class)->impor($tmp, $admin);
            $this->assertSame(2, $hasil['penjualan']);
            $this->assertSame('500.00', $hasil['liter_penjualan']);
            $this->assertSame(1, $hasil['penerimaan']);
            $this->assertSame('15500.00', app(StokService::class)->ringkasan()['fisik']);
            $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);
            $this->assertSame(1, Anggota::count());
            $this->assertSame(1, Kapal::count());
            $this->assertSame(1, SuratRekomendasi::count());
        } finally {
            @unlink($tmp);
        }

        // Verifikasi tombol Import Laporan tampil untuk Admin, tersembunyi untuk Finance
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($admin)->test(Laporan::class)->assertActionVisible('importPenjualan');
        Livewire::actingAs($finance)->test(Laporan::class)->assertActionHidden('importPenjualan');
    }

    public function test_import_laporan_stock_produk_solar_update_stok_penerimaan_multi_bulan_dan_idempoten(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $csv = <<<'CSV'
LAPORAN STOCK PRODUK SOLAR/BIO SOLAR,,,,,
SPBUN : KSU ASSEA LORGAN MANDIRI,,,,,
NO SPBUN : 18.208.032,,,,,
PERIODE : JANUARI,,,,,
,,,,,
Laporan Stock Produk : Solar/Bio Solar,,,,,
NO,TANGGAL,STOCK AWAL,PENERIMAAN,PENJUALAN,STOCK AKHIR
1,2026-01-01,,,,
6,2026-01-06,0,16000,16000,0
7,2026-01-07,0,16000,8500,7500
8,2026-01-08,7500,,7500,0
29,2026-01-29,0,32000,23300,8700
31,2026-01-31,,,,
,,,64000,55300,
LAPORAN STOCK PRODUK SOLAR/BIO SOLAR,,,,,
SPBUN : KSU ASSEA LORGAN MANDIRI,,,,,
NO SPBUN : 18.208.032,,,,,
PERIODE : FEBRUARI,,,,,
,,,,,
Laporan Stock Produk : Solar/Bio Solar,,,,,
NO,TANGGAL,STOCK AWAL,PENERIMAAN,PENJUALAN,STOCK AKHIR
3,2026-02-03,0,16000,16000,0
29,,,,,
,,,16000,16000,
CSV;

        $tmp = storage_path('framework/testing/import-stok-produk.csv');
        @mkdir(dirname($tmp), 0777, true);
        file_put_contents($tmp, $csv);

        try {
            $svc = app(LaporanPenjualanImportService::class);
            // Meski toggle imporPenerimaan=false, file khusus Laporan Stock Produk tetap otomatis mengimpor penerimaan
            $hasil = $svc->impor($tmp, $admin, false, true, true);

            $this->assertSame(0, $hasil['penjualan']);
            $this->assertSame(4, $hasil['penerimaan']); // 01-06 (16k), 01-07 (16k), 01-29 (32k), 02-03 (16k); baris total diabaikan
            $this->assertSame('80000.00', $hasil['liter_penerimaan']);
            $this->assertSame(0, $hasil['penerimaan_dilewati']);
            $this->assertSame(4, PenerimaanBbm::count());
            $this->assertSame('80000.00', app(StokService::class)->ringkasan()['fisik']);
            $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);

            // Import ulang file yang sama -> seluruh tanggal dilewati, stok tidak dobel
            $ulang = $svc->impor($tmp, $admin, true, true, true);
            $this->assertSame(0, $ulang['penerimaan']);
            $this->assertSame(4, $ulang['penerimaan_dilewati']);
            $this->assertSame('80000.00', app(StokService::class)->ringkasan()['fisik']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_import_cek_rekom_matriks_multi_bulan_termasuk_baris_nol_dan_nama_januari(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Siapkan stok masuk terlebih dahulu (seperti alur setelah import Laporan Stock Produk)
        app(\App\Services\PenerimaanService::class)->catat([
            'tanggal_penerimaan' => '2026-01-06',
            'nama_pemasok' => 'PT Pertamina Patra Niaga',
            'liter' => 16000,
        ], $admin);

        $csv = <<<'CSV'
,NO LP,18208032,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NAMA,KSU ASSEA LORGAN MANDIRI,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,Januari,,Penjualan,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NO,Nama Kapal,No Rekom,Vol Rekom,Penyaluran,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Total,Total Vs Rekom
,,,,,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,,
,1,M. Johar,523-02/DISKAN/2026,10000,,,,,,1000,,1000,,,,,,,,,,,,,,,,,,,,,,,,2000,-8000
,Total,,,,0,0,0,0,0,1000,0,1000,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2000,2000
,NO LP,,18208032,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,Mei,,,Penjualan,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NO,Nama,Nama Kapal,No Rekom,Vol Rekom,Penyaluran,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Total,Total Vs Rekom
,,,,,,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,,
,100,Januari,KM Januari,1562-KAB/12/12.05/PERIKANAN/JBT/V/2026,1000,,,,,,,,,,,,,,,,,,,250,,,,,,,,,,,,,250,-750
,109,Junaidi,KM Nurhidayah,1503-KAB/12/12.05/PERIKANAN/JBT/V/2026,1000,,,,,,,,,,,,,,250,,,,,,,,,,,,,,,,250,,500,-500
,110,Junaidi,KM Nurhidayah II,1503-KAB/12/12.05/PERIKANAN/JBT/V/2026,1000,,,,,,,,,,,,,,250,,,,,,,,,,,,,,,,250,,500,-500
,41,Bahlis,Bahlis,1617-KAB/12/12.05/PERIKANAN/JBT/V/2026,494,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,0,-494
,Total,,,,,0,0,0,0,0,0,0,0,0,0,0,0,0,500,0,0,0,0,250,0,0,0,0,0,0,0,0,0,0,500,0,1250,1250
CSV;

        $tmp = storage_path('framework/testing/import-cek-rekom.csv');
        @mkdir(dirname($tmp), 0777, true);
        file_put_contents($tmp, $csv);

        try {
            $svc = app(LaporanPenjualanImportService::class);
            $hasil = $svc->impor($tmp, $admin, false, true, true);

            // 7 transaksi sah: M. Johar (2: Jan 6 & 8 = 2000), Januari (1: Mei 19 = 250), Junaidi 2 kapal x 2 hari (Mei 14 & 30 = 1000)
            $this->assertSame(7, $hasil['penjualan']);
            $this->assertSame('3250.00', $hasil['liter_penjualan']);
            $this->assertSame(4, $hasil['anggota_baru']); // M. Johar, Januari, Junaidi, Bahlis
            $this->assertSame(5, $hasil['kapal_baru']);   // M. Johar, KM Januari, KM Nurhidayah, KM Nurhidayah II, Bahlis
            $this->assertSame(5, $hasil['surat_baru']);   // termasuk Bahlis (0 penjualan) & 2 kapal Junaidi (#01)
            $this->assertSame([], $hasil['gagal']);

            // Pastikan nelayan bernama 'Januari' tercatat di bulan Mei (2026-05-19), bukan Januari
            $slrJanuari = PenyaluranBbm::query()
                ->whereHas('pengajuan.kapal', fn ($q) => $q->where('nama_kapal', 'KM Januari'))
                ->first();
            $this->assertNotNull($slrJanuari);
            $this->assertSame('2026-05-19', $slrJanuari->tanggal_penyaluran->toDateString());

            // Tampil di Laporan Kapal (termasuk Bahlis yang penjualannya 0) & Laporan Surat Rekomendasi
            $lapKapal = app(LaporanService::class)->bangun('kapal', []);
            $this->assertSame(5, $lapKapal['ringkasan']['Total kapal']);

            $lapSurat = app(LaporanService::class)->bangun('surat', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']);
            $this->assertSame(1250.0, $lapSurat['ringkasan']['Total Penyaluran (L)']);
            $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);

            // Re-import -> 100% idempoten
            $ulang = $svc->impor($tmp, $admin, false, true, true);
            $this->assertSame(0, $ulang['penjualan']);
            $this->assertSame(7, $ulang['duplikat_dilewati']);
            $this->assertSame(0, $ulang['kapal_baru']);
            $this->assertSame(0, $ulang['surat_baru']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_import_rekap_penjualan_sektor_perikanan_lampiran_1_dan_export_ulang_sinkron(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        app(\App\Services\PenerimaanService::class)->catat([
            'tanggal_penerimaan' => '2026-09-01',
            'nama_pemasok' => 'PT Pertamina Patra Niaga',
            'liter' => 16000,
        ], $admin);

        // Tambah 1 surat bulan sebelumnya tanpa transaksi di September (tidak boleh muncul di export Rekap Penjualan September)
        [, , $suratLama] = $this->buatAnggota('LAMA-FEB');
        $suratLama->update(['nomor_surat' => '523-02/DISKAN/2026']);

        $csv = <<<'CSV'
REKAPITULASI LAPORAN PENJUALAN KE SEKTOR PERIKANAN,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
SPBU-N KOPERASI ASSEA LORGAN MANDIRI,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
No. SPBU Kompak,,: 18.208.032,,,,,,,,,,,,,,,,,,,,,,,,,,Bulan,,,: September,,,,
Alamat,,: Jl. Babalan Gg. Tenggiri No. 45 Pangkalan Brandan,,,,,,,,,,,,,,,,,,,,,,,,,,Tahun,,,: 2026,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,M.SOLAR,,,
No,Nama,Nama Kapal,No. Rekomendasi,Tanggal Penjualan,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Total
,,,,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,
1,A Rafid D,Dolpin,2839-KAB/12/12.05/PERIKANAN/JBT/IX/2026,,,,,,,,250,,,,,,,,,,,,250,,,,,,,,,,,,,,,500
2,Herlina,Join 1,2947-KAB/12/12.05/PERIKANAN/JBT/IX/2026,,,,,,,,,,100,,,,,,,,,,,,,,,,,,,,,,,,,100
3,Herlina,Join 2,2947-KAB/12/12.05/PERIKANAN/JBT/IX/2026,,,,,,,,,,150,,,,,,,,,,,,,,,,,,,,,,,,,150
JUMLAH,,,,0,0,0,0,250,0,150,0,0,0,0,0,0,0,0,0,250,0,0,0,0,0,0,0,0,0,0,0,0,0,0,750
,,,,,,,,,,,,,,,,,,,,,,,,,,,,Tanggal, 30 September 2026,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,Hormat kami,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,SYAFRIYANI,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,Ketua,,,,,,,
CSV;

        $tmp = storage_path('framework/testing/import-lampiran1.csv');
        @mkdir(dirname($tmp), 0777, true);
        file_put_contents($tmp, $csv);

        try {
            $hasil = app(LaporanPenjualanImportService::class)->impor($tmp, $admin, false, true, true);
            $this->assertSame(4, $hasil['penjualan']);
            $this->assertSame('750.00', $hasil['liter_penjualan']);
            $this->assertSame([], $hasil['gagal']);

            // Export Rekap Penjualan September -> hanya 3 kapal aktif September (bukan 4), tanpa #01, nilai cache Total/JUMLAH bukan 0
            $lap = app(LaporanService::class)->bangun('penjualan', ['dari' => '2026-09-01', 'sampai' => '2026-09-30']);
            $this->assertCount(3, $lap['bulanan'][0]['rows']);
            $this->assertSame(750.0, $lap['bulanan'][0]['total_penjualan']);
            $this->assertSame('2947-KAB/12/12.05/PERIKANAN/JBT/IX/2026', $lap['bulanan'][0]['rows'][1]['no_rekom']);
            $this->assertSame('2947-KAB/12/12.05/PERIKANAN/JBT/IX/2026', $lap['bulanan'][0]['rows'][2]['no_rekom']);

            $raw = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanPenjualanExport($lap), \Maatwebsite\Excel\Excel::XLSX);
            $tmpOut = storage_path('framework/testing/export-lampiran1-check.xlsx');
            file_put_contents($tmpOut, $raw);
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpOut);
                $reader->setReadDataOnly(true);
                $ws = $reader->load($tmpOut)->getActiveSheet();
                $this->assertNull($ws->getCell('E9')->getValue());
                $this->assertEquals(500.0, (float) $ws->getCell('AJ9')->getOldCalculatedValue());
                $this->assertEquals(100.0, (float) $ws->getCell('AJ10')->getOldCalculatedValue());
                $this->assertEquals(150.0, (float) $ws->getCell('AJ11')->getOldCalculatedValue());
                $this->assertSame('JUMLAH', $ws->getCell('A12')->getValue());
                $this->assertEquals(750.0, (float) $ws->getCell('AJ12')->getOldCalculatedValue());
            } finally {
                @unlink($tmpOut);
            }
        } finally {
            @unlink($tmp);
        }
    }

    public function test_import_dan_export_surat_memisahkan_baris_identik_menjaga_vol_rekom_murni_dan_tampilkan_baris_nol(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        app(\App\Services\PenerimaanService::class)->catat([
            'tanggal_penerimaan' => '2026-01-05',
            'nama_pemasok' => 'PT Pertamina Patra Niaga',
            'liter' => 32000,
        ], $admin);

        // Skenario 3 pola bug:
        // 1) Junaidi: 2 baris identik (Nama + Kapal + No Rekom sama) dengan Vol Rekom berbeda (5000 & 7000) di Januari
        // 2) Dedi Apriadi - Mhd Azhari: 2 baris identik dengan Vol Rekom sama (800 & 800) termasuk penjualan tanggal & liter sama di Juni
        // 3) Bahlis: aktif Mei (terjual 200 L) lalu terdaftar lagi di Juni dengan total penyaluran 0 L
        $csv = <<<'CSV'
,NO LP,18208032,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NAMA,KSU ASSEA LORGAN MANDIRI,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,Januari,,Penjualan,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NO,Nama Kapal,No Rekom,Vol Rekom,Penyaluran,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Total,Total Vs Rekom
,,,,,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,,
,1,Junaidi,523-02/DISKAN/2026,5000,,,,,,2000,,,,,,,,,,,,,,,,,,,,,,,,,,2000,-3000
,2,Junaidi,523-02/DISKAN/2026,7000,,,,,,3000,,,,,,,,,,,,,,,,,,,,,,,,,,3000,-4000
,Total,,,,0,0,0,0,0,5000,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,5000,5000
,NO LP,,18208032,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,Mei,,,Penjualan,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NO,Nama,Nama Kapal,No Rekom,Vol Rekom,Penyaluran,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Total,Total Vs Rekom
,,,,,,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,,
,1,Bahlis,Bahlis,1617-KAB/12/12.05/PERIKANAN/JBT/V/2026,494,,,,,,,,,,200,,,,,,,,,,,,,,,,,,,,,,200,-294
,Total,,,,,0,0,0,0,0,0,0,0,0,200,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,200,200
,NO LP,,18208032,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,Juni,,,Penjualan,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NO,Nama,Nama Kapal,No Rekom,Vol Rekom,Penyaluran,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Total,Total Vs Rekom
,,,,,,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,,
,1,Dedi Apriadi,Mhd Azhari,1808-KAB/12/12.05/PERIKANAN/JBT/VI/2026,800,,,,200,,,,,,400,,,,,,,,,,,,,,,,,,,,,,600,-200
,2,Dedi Apriadi,Mhd Azhari,1808-KAB/12/12.05/PERIKANAN/JBT/VI/2026,800,,,,200,,,,,,300,,,,,,,,,,,,,,,,,,,,,,500,-300
,3,Bahlis,Bahlis,1617-KAB/12/12.05/PERIKANAN/JBT/V/2026,494,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,0,-494
,Total,,,,,0,0,0,400,0,0,0,0,0,700,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1100,1100
CSV;

        $tmp = storage_path('framework/testing/import-cek-rekom-kasus-khusus.csv');
        @mkdir(dirname($tmp), 0777, true);
        file_put_contents($tmp, $csv);

        try {
            $svc = app(LaporanPenjualanImportService::class);
            $hasil = $svc->impor($tmp, $admin, false, true, true);

            // 7 transaksi: Junaidi (2), Bahlis Mei (1), Dedi Apriadi 2 baris x 2 hari (4) -> total 6.300 L
            $this->assertSame(7, $hasil['penjualan']);
            $this->assertSame('6300.00', $hasil['liter_penjualan']);
            $this->assertSame(5, $hasil['surat_baru']); // 2 Junaidi + 1 Bahlis + 2 Dedi Apriadi
            $this->assertSame([], $hasil['gagal']);

            // Verifikasi Januari: Junaidi tampil sebagai 2 baris terpisah dengan Vol Rekom murni 5.000 & 7.000
            $lapJan = app(LaporanService::class)->bangun('surat', ['dari' => '2026-01-01', 'sampai' => '2026-01-31']);
            $rowsJan = $lapJan['bulanan'][0]['rows'];
            $this->assertCount(2, $rowsJan);
            $volsJan = collect($rowsJan)->pluck('vol_rekom')->sort()->values()->all();
            $totalsJan = collect($rowsJan)->pluck('total')->sort()->values()->all();
            $this->assertSame([5000.0, 7000.0], $volsJan);
            $this->assertSame([2000.0, 3000.0], $totalsJan);

            // Verifikasi Juni: 3 baris (2 Dedi Apriadi masing-masing Vol Rekom 800 + 1 Bahlis Vol Rekom 494 total 0)
            $lapJun = app(LaporanService::class)->bangun('surat', ['dari' => '2026-06-01', 'sampai' => '2026-06-30']);
            $rowsJun = $lapJun['bulanan'][0]['rows'];
            $this->assertCount(3, $rowsJun);
            $this->assertSame(1100.0, $lapJun['bulanan'][0]['total_penyaluran']);

            $dediRows = collect($rowsJun)->where('nama_kapal', 'Mhd Azhari')->values();
            $this->assertCount(2, $dediRows);
            $this->assertSame([800.0, 800.0], $dediRows->pluck('vol_rekom')->all());
            $this->assertSame([500.0, 600.0], $dediRows->pluck('total')->sort()->values()->all());

            $bahlisJun = collect($rowsJun)->firstWhere('nama', 'Bahlis');
            $this->assertNotNull($bahlisJun);
            $this->assertSame(494.0, $bahlisJun['vol_rekom']);
            $this->assertSame(0.0, $bahlisJun['total']);
            $this->assertSame(-494.0, $bahlisJun['total_vs_rekom']);

            // Pastikan Bahlis (0 L di Juni) TIDAK muncul di Rekap Penjualan Sektor Perikanan (hanya 2 baris Dedi Apriadi)
            $lapJualJun = app(LaporanService::class)->bangun('penjualan', ['dari' => '2026-06-01', 'sampai' => '2026-06-30']);
            $this->assertCount(2, $lapJualJun['bulanan'][0]['rows']);
            $this->assertNull(collect($lapJualJun['bulanan'][0]['rows'])->firstWhere('nama', 'Bahlis'));

            // Import ulang file yang sama -> 100% idempoten (tidak membuat surat/penjualan ganda)
            $ulang = $svc->impor($tmp, $admin, false, true, true);
            $this->assertSame(0, $ulang['penjualan']);
            $this->assertSame(7, $ulang['duplikat_dilewati']);
            $this->assertSame(0, $ulang['surat_baru']);
            $this->assertSame(5, SuratRekomendasi::count());
            $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_import_lampiran6_mengisi_gt_dan_pk_serta_export_penjualan_harian_akurat(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $csv = <<<'CSV'
LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR,,,,,,,,,,,,
SPBUN : KSU ASSEA LORGAN MANDIRI,,,,,,,,,,,,
NO SPBUN : 18.208.032,,,,,,,,,,,,
PERIODE : JANUARI 2026,,,,,,,,,,,,
,,,,,,,,,,,,
Laporan Penjualan Produk : Solar/Bio Solar,,,,,,,,,,,,
No,Tanggal,Keterangan,,,,,,,,,,
,,Nama Kapal,Nama Pemilik,GT,PK,Rekomendasi,JML LTR Yang Direkomendasikan (Liter),STOCK AWAL (Liter),PENERIMAAN (Liter),PENJUALAN (Liter),STOCK AKHIR (Liter),Tujuan
1,06/01/2026,KM Bintang,Budi Santoso,2,23,523-02/DISKAN/2026,5000,0,16000,1000,14500,Melaut
2,,KM Cahaya,Siti Aminah,0.8,,1180-KAB/12/12.05/PERIKANAN/JBT/I/2026,2000,,,500,,Melaut
CSV;

        $tmp = storage_path('framework/testing/import-lampiran6-gt-pk.csv');
        @mkdir(dirname($tmp), 0777, true);
        file_put_contents($tmp, $csv);

        try {
            $hasil = app(LaporanPenjualanImportService::class)->impor($tmp, $admin, true, true, true);
            $this->assertSame(2, $hasil['penjualan']);
            $this->assertSame('1500.00', $hasil['liter_penjualan']);
            $this->assertSame(1, $hasil['penerimaan']);
            $this->assertSame('16000.00', $hasil['liter_penerimaan']);
            $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);

            $lap = app(LaporanService::class)->bangun('penjualan_harian', ['dari' => '2026-01-01', 'sampai' => '2026-01-31']);
            $rows = collect($lap['bulanan'][0]['rows']);

            $budi = $rows->firstWhere('nama_pemilik', 'Budi Santoso');
            $siti = $rows->firstWhere('nama_pemilik', 'Siti Aminah');
            $this->assertNotNull($budi);
            $this->assertNotNull($siti);
            $this->assertSame(2.0, $budi['gt']);
            $this->assertSame(23.0, $budi['pk']);
            $this->assertSame(0.8, $siti['gt']);
            $this->assertNull($siti['pk']);
            $this->assertSame(0.0, $budi['stock_awal']);
            $this->assertSame(16000.0, $budi['penerimaan']);
            $this->assertSame(14500.0, $budi['stock_akhir']);

            // Export ke XLSX dan pastikan GT & PK tertulis sebagai nilai numerik yang benar
            $raw = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\LaporanPenjualanHarianExport($lap), \Maatwebsite\Excel\Excel::XLSX);
            $tmpOut = storage_path('framework/testing/export-lampiran6-gt-pk.xlsx');
            file_put_contents($tmpOut, $raw);
            try {
                $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpOut)->getActiveSheet();
                // Row 9..13 = 1..5 Jan (kosong), Row 14 = 6 Jan Budi Santoso, Row 15 = 6 Jan Siti Aminah
                $this->assertSame('Budi Santoso', $ws->getCell('D14')->getValue());
                $this->assertEquals(2.0, (float) $ws->getCell('E14')->getValue());
                $this->assertEquals(23.0, (float) $ws->getCell('F14')->getValue());
                $this->assertEquals(16000.0, (float) $ws->getCell('J14')->getValue());
                $this->assertEquals(14500.0, (float) $ws->getCell('L14')->getValue());
                $this->assertSame('Siti Aminah', $ws->getCell('D15')->getValue());
                $this->assertEquals(0.8, (float) $ws->getCell('E15')->getValue());
                $this->assertNull($ws->getCell('F15')->getValue());
            } finally {
                @unlink($tmpOut);
            }
        } finally {
            @unlink($tmp);
        }
    }
}
