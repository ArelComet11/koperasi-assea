<?php

namespace Tests\Feature;

use App\Filament\Anggota\Pages\ProfilSaya;
use App\Filament\Anggota\Resources\JadwalSayaResource\Pages\ListJadwalSaya;
use App\Filament\Anggota\Resources\KapalSayaResource\Pages\ListKapalSaya;
use App\Filament\Anggota\Resources\PengajuanSayaResource\Pages\CreatePengajuanSaya;
use App\Filament\Anggota\Resources\PengajuanSayaResource\Pages\ListPengajuanSaya;
use App\Filament\Anggota\Resources\PengajuanSayaResource\Pages\ViewPengajuanSaya;
use App\Filament\Anggota\Resources\RiwayatPembayaranResource\Pages\ListRiwayatPembayaran;
use App\Filament\Anggota\Resources\RiwayatTransaksiResource\Pages\ListRiwayatTransaksi;
use App\Filament\Anggota\Resources\SuratSayaResource\Pages\ListSuratSaya;
use App\Filament\Anggota\Widgets\KuotaSuratAnggota;
use App\Filament\Anggota\Widgets\SapaanAnggota;
use App\Filament\Anggota\Widgets\StatistikAnggota;
use App\Filament\Anggota\Widgets\TransaksiTerbaruAnggota;
use App\Services\JadwalService;
use App\Services\PembayaranService;
use App\Services\PengajuanService;
use App\Services\PenyaluranService;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class AnggotaPanelAuditTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
    }

    public function test_semua_menu_widget_dan_fitur_panel_anggota_berjalan_tanpa_error(): void
    {
        $admin = $this->admin();
        $this->isiStok(10000);
        [$anggota, $kapal, $surat, $userAnggota] = $this->buatAnggota('ANG-MBR');

        // Pengajuan selesai (disetujui + dijadwalkan + lunas + disalurkan)
        $this->actingAs($userAnggota);
        $pSelesai = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id, 'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 200, 'tanggal_rencana' => today()->toDateString(),
        ]);

        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($pSelesai, $admin);
        $pSelesai->refresh();
        app(JadwalService::class)->buat($pSelesai, now()->addHours(2), 'Ambil di depot', $admin);
        app(PembayaranService::class)->catat($pSelesai->tagihan, $admin, 'tunai', 'BKT-MBR', now());
        app(PenyaluranService::class)->catat($pSelesai, 200, $admin, now());

        // Masuk sebagai anggota di panel anggota
        $this->actingAs($userAnggota);
        Filament::setCurrentPanel(Filament::getPanel('anggota'));

        // Dashboard & semua widget (termasuk KuotaSuratAnggota yang sebelumnya crash TypeError)
        Livewire::actingAs($userAnggota)->test(Dashboard::class)->assertSuccessful();
        Livewire::actingAs($userAnggota)->test(SapaanAnggota::class)->assertSuccessful()->assertSee('ANG-MBR');
        Livewire::actingAs($userAnggota)->test(StatistikAnggota::class)->assertSuccessful()->assertSee('200 L');
        Livewire::actingAs($userAnggota)->test(KuotaSuratAnggota::class)->assertSuccessful()->assertSee('1.000 L')->assertSee('800 L');
        Livewire::actingAs($userAnggota)->test(TransaksiTerbaruAnggota::class)->assertSuccessful()->assertSee('200 L');

        // ProfilSaya & ubah kata sandi
        Livewire::actingAs($userAnggota)->test(ProfilSaya::class)
            ->assertSuccessful()
            ->assertSee('ANG-MBR')
            ->callAction('ubahPassword', [
                'lama' => 'rahasia1234',
                'baru' => 'sandibaru1234',
                'baru_konfirmasi' => 'sandibaru1234',
            ])
            ->assertHasNoActionErrors();
        $this->assertTrue(Hash::check('sandibaru1234', $userAnggota->fresh()->password));

        // Seluruh menu Resource Anggota (verifikasi nilai rupiah & liter tampil asli, bukan 0 L / Rp0)
        Livewire::actingAs($userAnggota)->test(ListKapalSaya::class)->assertSuccessful()->assertSee($kapal->nama_kapal);
        Livewire::actingAs($userAnggota)->test(ListSuratSaya::class)->assertSuccessful()->assertSee('Aktif')->assertSee('800 L');
        Livewire::actingAs($userAnggota)->test(ListJadwalSaya::class)->assertSuccessful()->assertSee('200 L');
        Livewire::actingAs($userAnggota)->test(ListRiwayatTransaksi::class)->assertSuccessful()->assertSee('200 L')->assertSee('Rp6.800')->assertSee('Rp1.360.000');
        Livewire::actingAs($userAnggota)->test(ListRiwayatPembayaran::class)->assertSuccessful()->assertSee('Rp1.360.000');

        // PengajuanSaya: Create, List, View, dan Batalkan oleh anggota
        Livewire::actingAs($userAnggota)->test(CreatePengajuanSaya::class)
            ->fillForm([
                'kapal_id' => $kapal->id,
                'surat_rekomendasi_id' => $surat->id,
                'liter_diminta' => 100,
                'tanggal_rencana' => today()->addDay()->toDateString(),
                'catatan' => 'Pengajuan dari panel anggota',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pBaru = \App\Models\PengajuanPembelian::where('catatan', 'Pengajuan dari panel anggota')->firstOrFail();

        Livewire::actingAs($userAnggota)->test(ListPengajuanSaya::class)
            ->assertSuccessful()
            ->assertSee('100 L')
            ->assertSee('Rp680.000')
            ->callTableAction('batalkan', $pBaru, ['alasan' => 'Batal uji'])
            ->assertHasNoTableActionErrors();

        Livewire::actingAs($userAnggota)->test(ViewPengajuanSaya::class, ['record' => $pSelesai->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('200 L')
            ->assertSee('Rp6.800')
            ->assertSee('Rp1.360.000');
    }

    public function test_perbaikan_temuan_1_sampai_7_panel_anggota(): void
    {
        $admin = $this->admin();
        $this->isiStok(10000);
        [$anggota, $kapal, $surat, $u] = $this->buatAnggota('ANG-FIX');

        // #1 Surat kedaluwarsa tidak menampilkan sisa kuota penuh (tampil '-')
        \App\Models\SuratRekomendasi::create([
            'nomor_surat' => 'SR-EXP-FIX', 'anggota_id' => $anggota->id, 'kapal_id' => $kapal->id,
            'berlaku_mulai' => today()->subMonths(2), 'berlaku_sampai' => today()->subDay(),
            'kuota_bulanan_liter' => 777, 'status' => \App\Enums\StatusAktif::Aktif,
        ]);

        // #2 Penyaluran parsial setelah bayar lunas menampilkan liter aktual & info refund di ViewPengajuanSaya + RiwayatTransaksi
        $this->actingAs($u);
        $p = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id, 'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 200, 'tanggal_rencana' => today()->toDateString(),
        ]);
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        $p->refresh();
        app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai');
        app(PenyaluranService::class)->catat($p, 150, $admin);

        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('anggota'));

        Livewire::actingAs($u)->test(ListSuratSaya::class)
            ->assertSuccessful()
            ->assertSee('SR-EXP-FIX')
            ->assertSee('Kedaluwarsa');

        $htmlSurat = Livewire::actingAs($u)->test(ListSuratSaya::class)->html();
        // 777 L hanya muncul 1x (kolom Kuota/bulan), bukan 2x (kolom Sisa bulan ini)
        $this->assertSame(1, substr_count($htmlSurat, '777 L'));

        Livewire::actingAs($u)->test(ListRiwayatTransaksi::class)
            ->assertSuccessful()
            ->assertSee('150 L')
            ->assertSee('Refund Rp340.000');

        Livewire::actingAs($u)->test(ViewPengajuanSaya::class, ['record' => $p->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('150 L')
            ->assertSee($p->tagihan->nomor_tagihan)
            ->assertSee('Menunggu pengembalian Rp340.000');

        // #3 Sidebar di /anggota/pengajuan-saya/create hanya menyorot 1 menu (tidak double active)
        $htmlIndex = $this->actingAs($u)->get('/anggota/pengajuan-saya')->assertOk()->getContent();
        $htmlCreate = $this->actingAs($u)->get('/anggota/pengajuan-saya/create')->assertOk()->getContent();
        $this->assertSame(substr_count($htmlIndex, 'fi-active'), substr_count($htmlCreate, 'fi-active'));

        // #4 Estimasi total tidak pernah negatif saat input liter negatif
        Livewire::actingAs($u)->test(CreatePengajuanSaya::class)
            ->set('data.liter_diminta', -50)
            ->assertSet('data.total_tampil', 'Rp0');

        // #5 Ubah kata sandi dengan sandi lama salah gagal di validasi field (modal tidak tertutup diam-diam)
        Livewire::actingAs($u)->test(ProfilSaya::class)
            ->callAction('ubahPassword', [
                'lama' => 'sandisalah99',
                'baru' => 'sandibaru1234',
                'baru_konfirmasi' => 'sandibaru1234',
            ])
            ->assertHasActionErrors(['lama']);

        // #7 Empty state arahan untuk anggota tanpa kapal aktif
        $anggotaKosong = app(\App\Services\AnggotaService::class)->buat([
            'kode_anggota' => 'ANG-NO-KPL', 'nama_lengkap' => 'Tanpa Kapal',
            'username' => 'tanpakapal_u', 'password' => 'rahasia1234',
        ], $admin);
        $this->actingAs($anggotaKosong->user);
        Livewire::actingAs($anggotaKosong->user)->test(CreatePengajuanSaya::class)
            ->assertSuccessful()
            ->assertSee('Belum ada kapal aktif terdaftar');
    }
}
