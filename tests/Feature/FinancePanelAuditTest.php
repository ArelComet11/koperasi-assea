<?php

namespace Tests\Feature;

use App\Enums\StatusTagihan;
use App\Exceptions\BisnisException;
use App\Filament\Admin\Resources\PengajuanPembelianResource;
use App\Filament\Admin\Resources\PengajuanPembelianResource\Pages\ListPengajuan;
use App\Filament\Admin\Resources\PengajuanPembelianResource\Pages\ViewPengajuan;
use App\Filament\Admin\Resources\PembayaranResource\Pages\ListPembayaran;
use App\Filament\Admin\Resources\RiwayatHargaResource\Pages\ManageRiwayatHarga;
use App\Filament\Admin\Resources\TagihanResource;
use App\Filament\Admin\Resources\TagihanResource\Pages\ListTagihan;
use App\Filament\Admin\Widgets\GrafikPemasukan;
use App\Filament\Admin\Widgets\GrafikTransaksi;
use App\Filament\Admin\Widgets\PembayaranTerbaru;
use App\Filament\Admin\Widgets\PengajuanTerbaru;
use App\Filament\Admin\Widgets\StatistikKeuangan;
use App\Models\Tagihan;
use App\Services\LaporanService;
use App\Services\PembayaranService;
use App\Services\PengajuanService;
use App\Services\PenyaluranService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class FinancePanelAuditTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_dashboard_widget_badge_dan_batas_izin_role_finance(): void
    {
        $admin = $this->admin();
        $finance = $this->finance();
        $this->isiStok(10000);
        [$anggota, $kapal, $surat, $userAnggota] = $this->buatAnggota('FIN-01');

        $this->actingAs($userAnggota);
        $pMenunggu = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id,
            'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 50,
            'tanggal_rencana' => today()->toDateString(),
        ]);

        // #8 Badge Pengajuan Pembelian tidak tampil untuk finance (tidak punya pengajuan.approve), tampil untuk admin
        $this->actingAs($finance);
        $this->assertNull(PengajuanPembelianResource::getNavigationBadge());
        $this->actingAs($admin);
        $this->assertSame('1', PengajuanPembelianResource::getNavigationBadge());

        // Semua widget & menu finance dapat dibuka tanpa error
        $this->actingAs($finance);
        $this->get('/admin')->assertOk()->assertSee('Koreksi / refund tertunda');
        Livewire::actingAs($finance)->test(StatistikKeuangan::class)->assertSuccessful();
        Livewire::actingAs($finance)->test(GrafikPemasukan::class)->assertSuccessful();
        Livewire::actingAs($finance)->test(GrafikTransaksi::class)->assertSuccessful();
        Livewire::actingAs($finance)->test(PengajuanTerbaru::class)->assertSuccessful();
        Livewire::actingAs($finance)->test(PembayaranTerbaru::class)->assertSuccessful();
        Livewire::actingAs($finance)->test(ListPengajuan::class)->assertSuccessful();
        Livewire::actingAs($finance)->test(ViewPengajuan::class, ['record' => $pMenunggu->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('setujui')
            ->assertActionHidden('tolak')
            ->assertActionHidden('jadwal')
            ->assertActionHidden('salurkan')
            ->assertActionHidden('batalkan');
        Livewire::actingAs($finance)->test(ListPembayaran::class)->assertSuccessful();
        Livewire::actingAs($finance)->test(ManageRiwayatHarga::class)->assertSuccessful()->assertActionHidden('create');

        // #5 Akses laporan terlarang menampilkan pesan error yang jelas (bukan halaman kosong)
        $this->get('/admin/laporan?jenis=anggota')
            ->assertOk()
            ->assertSee('Anda tidak memiliki izin untuk membuka jenis laporan tersebut.');
        $this->get('/admin/laporan?jenis=keuangan')
            ->assertOk()
            ->assertSee('Laporan Keuangan')
            ->assertSee('Belum dibayar');
        $this->get('/dokumen/surat/' . $surat->id)->assertForbidden();
        $this->get('/admin/anggota')->assertForbidden();
        $this->get('/anggota')->assertForbidden();
    }

    public function test_tagihan_refund_akuntansi_neto_filter_laporan_dan_bukti_cetak(): void
    {
        $admin = $this->admin();
        $finance = $this->finance();
        $this->isiStok(10000);
        [$anggota, $kapal, $surat, $userAnggota] = $this->buatAnggota('FIN-02');

        // Pengajuan 1: disetujui (belum dibayar)
        $this->actingAs($userAnggota);
        $pBelum = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id,
            'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 100,
            'tanggal_rencana' => today()->toDateString(),
        ]);
        // Pengajuan 2: disetujui -> dibayar lunas 100 L (Rp 680.000) -> disalurkan 80 L (Rp 544.000) -> refund Rp 136.000
        $pRefund = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id,
            'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 100,
            'tanggal_rencana' => today()->toDateString(),
        ]);

        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($pBelum, $admin);
        app(PengajuanService::class)->setujui($pRefund, $admin);
        $tagihanBelum = Tagihan::where('pengajuan_id', $pBelum->id)->firstOrFail();
        $tagihanRefund = Tagihan::where('pengajuan_id', $pRefund->id)->firstOrFail();

        // #6 Pembayaran tanggal masa depan ditolak di service
        try {
            app(PembayaranService::class)->catat($tagihanBelum, $finance, 'tunai', null, now()->addHour());
            $this->fail('Seharusnya menolak tanggal pembayaran di masa depan.');
        } catch (BisnisException $e) {
            $this->assertStringContainsString('melebihi waktu saat ini', $e->getMessage());
        }

        // Finance mencatat pembayaran tagihan 2, lalu admin menyalurkan aktual 80 L
        $bayarRefund = app(PembayaranService::class)->catat($tagihanRefund, $finance, 'tunai', 'BKT-FIN', now());
        app(PenyaluranService::class)->catat($pRefund, 80, $admin, now());

        // #1 Filter 'perlu_koreksi' langsung menampilkan tagihan refund tanpa bentrok filter default status
        $this->actingAs($finance);
        Livewire::actingAs($finance)->test(ListTagihan::class)
            ->filterTable('perlu_koreksi', true)
            ->assertCanSeeTableRecords([$tagihanRefund])
            ->assertCanNotSeeTableRecords([$tagihanBelum]);

        // #3 Laporan keuangan periode kosong (2020-01) tidak membocorkan refund tertunda di luar periode
        $lapKosong = app(LaporanService::class)->bangun('keuangan', ['dari' => '2020-01-01', 'sampai' => '2020-01-31']);
        $this->assertArrayNotHasKey('Selisih pengembalian tertunda', $lapKosong['ringkasan']);

        // #4 Filter status=belum_dibayar di Laporan Keuangan menyelaraskan total pembayaran & baris harian (= Rp0)
        $lapBelum = app(LaporanService::class)->bangun('keuangan', ['status' => StatusTagihan::BelumDibayar->value]);
        $this->assertSame('Rp680.000', $lapBelum['ringkasan']['Total tagihan']);
        $this->assertSame('Rp0', $lapBelum['ringkasan']['Total pembayaran diterima']);
        $this->assertCount(0, $lapBelum['rows']);

        // #7 Bukti cetak menampilkan informasi koreksi/refund tertunda
        $this->actingAs($finance)->get('/cetak/pembayaran/' . $bayarRefund->id)
            ->assertOk()
            ->assertSee('Koreksi / pengembalian selisih')
            ->assertSee('Rp136.000')
            ->assertSee('Menunggu pengembalian ke anggota');

        // #2 & #6 Finance menyelesaikan refund secara atomik -> tagihan & laporan neto sinkron + klik ganda ditolak
        Livewire::actingAs($finance)->test(ListTagihan::class)
            ->callTableAction('selesaikanKoreksi', $tagihanRefund, ['catatan' => 'Refund tunai diserahkan ke anggota'])
            ->assertHasNoTableActionErrors();

        $tagihanRefund->refresh();
        $pRefund->refresh();
        $this->assertEquals('544000.00', $tagihanRefund->total);
        $this->assertEquals('80.00', $tagihanRefund->liter);
        $this->assertEquals('544000.00', $pRefund->total_tagihan);

        // Klik ganda ditolak oleh PembayaranService::selesaikanPenyesuaian
        $this->expectException(BisnisException::class);
        try {
            app(PembayaranService::class)->selesaikanPenyesuaian($tagihanRefund, $finance, 'Duplikat klik');
        } finally {
            // Verifikasi laporan keuangan neto & bukti cetak setelah refund selesai
            $lapSesudah = app(LaporanService::class)->bangun('keuangan', []);
            $this->assertSame('Rp680.000', $lapSesudah['ringkasan']['Total pembayaran diterima']);
            $this->assertSame('Rp136.000', $lapSesudah['ringkasan']['Total refund selesai']);
            $this->assertSame('Rp544.000', $lapSesudah['ringkasan']['Pemasukan neto']);
            $this->assertArrayNotHasKey('Selisih pengembalian tertunda', $lapSesudah['ringkasan']);

            // Dashboard StatistikKeuangan menampilkan pemasukan neto (Rp544.000) & refund tertunda Rp0
            Livewire::actingAs($finance)->test(StatistikKeuangan::class)
                ->assertSee('Rp544.000')
                ->assertSee('Koreksi / refund tertunda');

            // Bukti cetak menampilkan status sudah dikembalikan
            $this->actingAs($finance)->get('/cetak/pembayaran/' . $bayarRefund->id)
                ->assertOk()
                ->assertSee('Sudah dikembalikan / dikoreksi')
                ->assertSee('Rp544.000');

            // Badge Tagihan turun dari 2 -> 1 (tinggal tagihanBelum)
            $this->assertSame('1', TagihanResource::getNavigationBadge());
        }
    }
}
