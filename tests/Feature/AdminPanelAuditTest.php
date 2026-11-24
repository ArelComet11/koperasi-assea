<?php

namespace Tests\Feature;

use App\Enums\StatusAktif;
use App\Enums\StatusBerita;
use App\Filament\Admin\Pages\Laporan;
use App\Filament\Admin\Pages\MonitoringKuota;
use App\Filament\Admin\Pages\MonitoringPasokan;
use App\Filament\Admin\Pages\PersediaanStok;
use App\Filament\Admin\Resources\AktivitasAdminResource\Pages\ListAktivitas;
use App\Filament\Admin\Resources\AnggotaResource\Pages\CreateAnggota;
use App\Filament\Admin\Resources\AnggotaResource\Pages\EditAnggota;
use App\Filament\Admin\Resources\AnggotaResource\Pages\ListAnggota;
use App\Filament\Admin\Resources\AnggotaResource\Pages\ViewAnggota;
use App\Filament\Admin\Resources\AnggotaResource\RelationManagers\KapalRelationManager;
use App\Filament\Admin\Resources\AnggotaResource\RelationManagers\PengajuanRelationManager;
use App\Filament\Admin\Resources\AnggotaResource\RelationManagers\SuratRelationManager;
use App\Filament\Admin\Resources\BeritaResource\Pages\CreateBerita;
use App\Filament\Admin\Resources\BeritaResource\Pages\EditBerita;
use App\Filament\Admin\Resources\BeritaResource\Pages\ListBerita;
use App\Filament\Admin\Resources\GaleriResource\Pages\ManageGaleri;
use App\Filament\Admin\Resources\JadwalPembelianResource\Pages\ListJadwal;
use App\Filament\Admin\Resources\KapalResource\Pages\ManageKapal;
use App\Filament\Admin\Resources\KategoriBeritaResource\Pages\ManageKategoriBerita;
use App\Filament\Admin\Resources\MutasiStokResource\Pages\ListMutasiStok;
use App\Filament\Admin\Resources\PembayaranResource\Pages\ListPembayaran;
use App\Filament\Admin\Resources\PenerimaanBbmResource\Pages\CreatePenerimaan;
use App\Filament\Admin\Resources\PenerimaanBbmResource\Pages\ListPenerimaan;
use App\Filament\Admin\Resources\PengajuanPembelianResource\Pages\ListPengajuan;
use App\Filament\Admin\Resources\PengajuanPembelianResource\Pages\ViewPengajuan;
use App\Filament\Admin\Resources\PengajuanPembelianResource\RelationManagers\RiwayatRelationManager;
use App\Filament\Admin\Resources\PengaturanResource\Pages\EditPengaturan;
use App\Filament\Admin\Resources\PengaturanResource\Pages\ListPengaturan;
use App\Filament\Admin\Resources\PengurusResource\Pages\ManagePengurus;
use App\Filament\Admin\Resources\RiwayatHargaResource\Pages\ManageRiwayatHarga;
use App\Filament\Admin\Resources\SuratRekomendasiResource\Pages\CreateSuratRekomendasi;
use App\Filament\Admin\Resources\SuratRekomendasiResource\Pages\EditSuratRekomendasi;
use App\Filament\Admin\Resources\SuratRekomendasiResource\Pages\ListSuratRekomendasi;
use App\Filament\Admin\Resources\SuratRekomendasiResource\RelationManagers\KuotaBulananRelationManager;
use App\Filament\Admin\Resources\TagihanResource\Pages\ListTagihan;
use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Filament\Admin\Widgets\AktivitasTerbaru;
use App\Filament\Admin\Widgets\GrafikKuota;
use App\Filament\Admin\Widgets\GrafikPemasukan;
use App\Filament\Admin\Widgets\GrafikPenerimaan;
use App\Filament\Admin\Widgets\GrafikPenyaluran;
use App\Filament\Admin\Widgets\GrafikTransaksi;
use App\Filament\Admin\Widgets\PasokanChart;
use App\Filament\Admin\Widgets\PasokanStats;
use App\Filament\Admin\Widgets\PembayaranTerbaru;
use App\Filament\Admin\Widgets\PenerimaanTerbaru;
use App\Filament\Admin\Widgets\PengajuanTerbaru;
use App\Filament\Admin\Widgets\StatistikKeuangan;
use App\Filament\Admin\Widgets\StatistikOperasional;
use App\Filament\Admin\Widgets\StokStats;
use App\Models\Berita;
use App\Models\Galeri;
use App\Models\KategoriBerita;
use App\Models\Pengaturan;
use App\Models\Pengurus;
use App\Services\JadwalService;
use App\Services\LaporanService;
use App\Services\PembayaranService;
use App\Services\PengajuanService;
use App\Services\PenyaluranService;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class AdminPanelAuditTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_semua_menu_widget_halaman_dan_format_data_admin_berjalan_tanpa_error(): void
    {
        $admin = $this->admin();
        $this->isiStok(5000);
        $penerimaan = $this->terima(16000);
        [$anggota, $kapal, $surat, $userAnggota] = $this->buatAnggota('ANG-001');

        // Pengajuan 1: Menunggu Persetujuan
        $this->actingAs($userAnggota);
        $pMenunggu = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id,
            'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 100,
            'tanggal_rencana' => today()->addDay()->toDateString(),
            'catatan' => 'Pengajuan menunggu',
        ]);

        // Pengajuan 2: Disetujui -> Dijadwalkan -> Dibayar -> Disalurkan
        $pSelesai = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id,
            'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 200,
            'tanggal_rencana' => today()->toDateString(),
            'catatan' => 'Pengajuan selesai',
        ]);

        // Pengajuan 3: Disetujui (tagihan belum lunas)
        $pDisetujui = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id,
            'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 150,
            'tanggal_rencana' => today()->addDays(2)->toDateString(),
            'catatan' => 'Pengajuan disetujui',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        app(PengajuanService::class)->setujui($pSelesai, $admin);
        $pSelesai->refresh();
        $jadwal = app(JadwalService::class)->buat($pSelesai, now()->addHours(2), 'Jadwal uji', $admin);
        $pembayaran = app(PembayaranService::class)->catat($pSelesai->tagihan, $admin, 'tunai', 'BKT-01', now());
        app(PenyaluranService::class)->catat($pSelesai, 180, $admin, now(), 'Penyaluran uji');

        app(PengajuanService::class)->setujui($pDisetujui, $admin);
        $pDisetujui->refresh();

        $kat = KategoriBerita::create(['nama' => 'Pengumuman', 'slug' => 'pengumuman']);
        $berita = Berita::create([
            'judul' => 'Berita Uji', 'slug' => 'berita-uji', 'kategori_berita_id' => $kat->id,
            'konten' => '<p>Isi berita</p>', 'status' => StatusBerita::Terbit, 'published_at' => now(), 'penulis_id' => $admin->id,
        ]);
        Galeri::create(['judul' => 'Foto Uji', 'gambar' => 'galeri/uji.jpg', 'urutan' => 1, 'tampil' => true]);
        Pengurus::create(['nama' => 'Ketua Uji', 'jabatan' => 'Ketua', 'urutan' => 1, 'tampil' => true]);

        // Dashboard & all widgets
        Livewire::actingAs($admin)->test(Dashboard::class)->assertSuccessful();
        foreach ([
            StatistikOperasional::class, StatistikKeuangan::class,
            GrafikPenerimaan::class, GrafikPenyaluran::class, GrafikPemasukan::class, GrafikTransaksi::class, GrafikKuota::class,
            StokStats::class, PasokanStats::class, PasokanChart::class, AktivitasTerbaru::class,
        ] as $widget) {
            Livewire::actingAs($admin)->test($widget)->assertSuccessful();
        }

        // Verify TableWidgets display formatted real values (not 0 L / Rp0)
        Livewire::actingAs($admin)->test(PengajuanTerbaru::class)->assertSuccessful()->assertSee('200 L');
        Livewire::actingAs($admin)->test(PembayaranTerbaru::class)->assertSuccessful()->assertSee('Rp1.360.000');
        Livewire::actingAs($admin)->test(PenerimaanTerbaru::class)->assertSuccessful()->assertSee('16.000 L');

        // Custom pages
        foreach ([PersediaanStok::class, MonitoringPasokan::class, MonitoringKuota::class, Laporan::class] as $page) {
            Livewire::actingAs($admin)->test($page)->assertSuccessful();
        }

        // Pengajuan list & view (previously crashed with 500 + showed 0 L / '-')
        Livewire::actingAs($admin)->test(ListPengajuan::class)
            ->assertSuccessful()
            ->assertSee('100 L')
            ->assertSee('Rp6.800')
            ->assertSee('Rp1.360.000')
            ->mountTableAction('setujui', $pMenunggu)->assertSuccessful()
            ->mountTableAction('salurkan', $pDisetujui)->assertSuccessful()
            ->mountTableAction('jadwal', $pDisetujui)->assertSuccessful();

        Livewire::actingAs($admin)->test(ViewPengajuan::class, ['record' => $pSelesai->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('200 L')
            ->assertSee('180 L')
            ->assertSee('Rp6.800')
            ->assertSee('Rp1.360.000');

        // Jadwal, Penerimaan, Mutasi, Tagihan, Pembayaran, Harga, Surat, Pengaturan formatted columns
        Livewire::actingAs($admin)->test(ListJadwal::class)->assertSuccessful()->assertSee('200 L')->mountTableAction('ubah', $jadwal)->assertSuccessful();
        Livewire::actingAs($admin)->test(ListPenerimaan::class)->assertSuccessful()->assertSee('16.000 L')->mountTableAction('view', $penerimaan)->assertSuccessful();
        Livewire::actingAs($admin)->test(ListMutasiStok::class)->assertSuccessful()->assertSee('+16.000 L');
        Livewire::actingAs($admin)->test(ListTagihan::class)->assertSuccessful()->assertSee('150 L')->assertSee('Rp1.020.000');
        Livewire::actingAs($admin)->test(ListPembayaran::class)->assertSuccessful()->assertSee('Rp1.360.000');
        Livewire::actingAs($admin)->test(ManageRiwayatHarga::class)->assertSuccessful()->assertSee('Rp6.800');
        Livewire::actingAs($admin)->test(ListSuratRekomendasi::class)->assertSuccessful()->assertSee('Aktif');
        Livewire::actingAs($admin)->test(ListPengaturan::class)->assertSuccessful()->assertSee('Koperasi Serba Usaha Assea Lorgan Mandiri');

        // EditPengaturan for ALL 5 types including numeric (previously crashed TipTap with 500)
        foreach (['string', 'number', 'text', 'richtext', 'file'] as $tipe) {
            $rec = Pengaturan::where('tipe', $tipe)->firstOrFail();
            Livewire::actingAs($admin)->test(EditPengaturan::class, ['record' => $rec->getRouteKey()])->assertSuccessful();
        }

        // Save numeric Pengaturan successfully
        $batasPasokan = Pengaturan::where('kunci', 'pasokan.batas_bulanan')->firstOrFail();
        Livewire::actingAs($admin)->test(EditPengaturan::class, ['record' => $batasPasokan->getRouteKey()])
            ->fillForm(['nilai_angka' => 170000])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('170000', $batasPasokan->fresh()->nilai);

        // Other Resource Pages & RelationManagers
        foreach ([
            ListAktivitas::class, ListAnggota::class, CreateAnggota::class,
            ListBerita::class, CreateBerita::class, ManageGaleri::class,
            ManageKapal::class, ManageKategoriBerita::class, ManagePengurus::class,
            CreatePenerimaan::class, CreateSuratRekomendasi::class, ListUsers::class, CreateUser::class,
        ] as $page) {
            Livewire::actingAs($admin)->test($page)->assertSuccessful();
        }

        Livewire::actingAs($admin)->test(ViewAnggota::class, ['record' => $anggota->getRouteKey()])->assertSuccessful();
        Livewire::actingAs($admin)->test(EditAnggota::class, ['record' => $anggota->getRouteKey()])
            ->fillForm(['nama_lengkap' => 'Anggota Diubah'])
            ->call('save')
            ->assertHasNoFormErrors();
        Livewire::actingAs($admin)->test(EditBerita::class, ['record' => $berita->getRouteKey()])->assertSuccessful();
        Livewire::actingAs($admin)->test(EditSuratRekomendasi::class, ['record' => $surat->getRouteKey()])->assertSuccessful();
        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $admin->getRouteKey()])->assertSuccessful();

        // RelationManagers display formatted values properly
        Livewire::actingAs($admin)->test(KapalRelationManager::class, ['ownerRecord' => $anggota, 'pageClass' => ViewAnggota::class])->assertSuccessful();
        Livewire::actingAs($admin)->test(SuratRelationManager::class, ['ownerRecord' => $anggota, 'pageClass' => ViewAnggota::class])->assertSuccessful()->assertSee('Aktif');
        Livewire::actingAs($admin)->test(PengajuanRelationManager::class, ['ownerRecord' => $anggota, 'pageClass' => ViewAnggota::class])->assertSuccessful()->assertSee('200 L')->assertSee('Rp1.360.000');
        Livewire::actingAs($admin)->test(RiwayatRelationManager::class, ['ownerRecord' => $pSelesai, 'pageClass' => ViewPengajuan::class])->assertSuccessful()->assertSee('Selesai');
        Livewire::actingAs($admin)->test(KuotaBulananRelationManager::class, ['ownerRecord' => $surat, 'pageClass' => EditSuratRekomendasi::class])->assertSuccessful()->assertSee('1.000 L');

        // Form submissions on Create pages & ManageRiwayatHarga
        Livewire::actingAs($admin)->test(CreateBerita::class)
            ->fillForm(['judul' => 'Berita Baru', 'slug' => 'berita-baru', 'kategori_berita_id' => $kat->id, 'konten' => '<p>Halo</p>', 'status' => 'draft'])
            ->call('create')->assertHasNoFormErrors();

        Livewire::actingAs($admin)->test(CreateAnggota::class)
            ->fillForm(['username' => 'anggota_baru', 'password' => 'rahasia1234', 'kode_anggota' => 'ANG-002', 'nama_lengkap' => 'Anggota Baru'])
            ->call('create')->assertHasNoFormErrors();

        Livewire::actingAs($admin)->test(CreatePenerimaan::class)
            ->fillForm(['tanggal_penerimaan' => today()->toDateString(), 'nama_pemasok' => 'Pemasok Baru', 'liter' => 2500])
            ->call('create')->assertHasNoFormErrors();

        Livewire::actingAs($admin)->test(ManageRiwayatHarga::class)
            ->callAction('create', ['harga_per_liter' => 7000, 'alasan' => 'Penyesuaian harga'])
            ->assertHasNoActionErrors();

        $financeRoleId = Role::where('name', 'finance')->value('id');
        Livewire::actingAs($admin)->test(CreateUser::class)
            ->fillForm(['name' => 'Staf Finance Baru', 'username' => 'finance_baru', 'password' => 'rahasia1234', 'is_active' => true, 'roles' => [$financeRoleId]])
            ->call('create')->assertHasNoFormErrors();

        // All 8 report types (preview & print; Excel export is covered by LaporanTest)
        foreach (array_keys(LaporanService::JENIS) as $jenis) {
            $this->actingAs($admin)->get('/admin/laporan?jenis=' . $jenis)->assertOk();
            $this->actingAs($admin)->get('/laporan/cetak?jenis=' . $jenis)->assertOk();
        }
    }

    public function test_navigation_badge_menampilkan_jumlah_prioritas_dan_hilang_bila_nol(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Saat kosong, semua badge tidak ditampilkan (null)
        $this->assertNull(\App\Filament\Admin\Resources\PengajuanPembelianResource::getNavigationBadge());
        $this->assertNull(\App\Filament\Admin\Resources\TagihanResource::getNavigationBadge());
        $this->assertNull(\App\Filament\Admin\Resources\JadwalPembelianResource::getNavigationBadge());
        $this->assertNull(\App\Filament\Admin\Resources\SuratRekomendasiResource::getNavigationBadge());

        $this->isiStok(10000);
        [$anggota, $kapal, $surat, $userAnggota] = $this->buatAnggota('ANG-BDG');

        // Buat surat kedua yang segera berakhir (7 hari lagi)
        \App\Models\SuratRekomendasi::create([
            'nomor_surat' => 'SR-SEGERA-HABIS',
            'anggota_id' => $anggota->id,
            'kapal_id' => $kapal->id,
            'berlaku_mulai' => today()->subMonth(),
            'berlaku_sampai' => today()->addDays(7),
            'kuota_bulanan_liter' => 500,
            'status' => StatusAktif::Aktif,
        ]);

        // 2 pengajuan: 1 menunggu, 1 disetujui (menghasilkan 1 tagihan belum dibayar + 1 jadwal aktif)
        $this->actingAs($userAnggota);
        app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id, 'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 100, 'tanggal_rencana' => today()->addDay()->toDateString(),
        ]);
        $p2 = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id, 'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 150, 'tanggal_rencana' => today()->addDay()->toDateString(),
        ]);

        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p2, $admin);
        app(JadwalService::class)->buat($p2->fresh(), now()->addHours(3), 'Uji badge', $admin);

        $this->assertSame('1', \App\Filament\Admin\Resources\PengajuanPembelianResource::getNavigationBadge());
        $this->assertSame('warning', \App\Filament\Admin\Resources\PengajuanPembelianResource::getNavigationBadgeColor());

        $this->assertSame('1', \App\Filament\Admin\Resources\TagihanResource::getNavigationBadge());
        $this->assertSame('danger', \App\Filament\Admin\Resources\TagihanResource::getNavigationBadgeColor());

        $this->assertSame('1', \App\Filament\Admin\Resources\JadwalPembelianResource::getNavigationBadge());
        $this->assertSame('info', \App\Filament\Admin\Resources\JadwalPembelianResource::getNavigationBadgeColor());

        $this->assertSame('1', \App\Filament\Admin\Resources\SuratRekomendasiResource::getNavigationBadge());
        $this->assertSame('warning', \App\Filament\Admin\Resources\SuratRekomendasiResource::getNavigationBadgeColor());

        // Jika ada surat yang sudah kedaluwarsa, warna badge berubah menjadi danger
        \App\Models\SuratRekomendasi::create([
            'nomor_surat' => 'SR-KEDALUWARSA',
            'anggota_id' => $anggota->id,
            'kapal_id' => $kapal->id,
            'berlaku_mulai' => today()->subMonths(2),
            'berlaku_sampai' => today()->subDay(),
            'kuota_bulanan_liter' => 500,
            'status' => StatusAktif::Aktif,
        ]);

        $this->assertSame('2', \App\Filament\Admin\Resources\SuratRekomendasiResource::getNavigationBadge());
        $this->assertSame('danger', \App\Filament\Admin\Resources\SuratRekomendasiResource::getNavigationBadgeColor());
    }

    public function test_perbaikan_laporan_self_lockout_infolist_penerimaan_dan_sinkron_kuota(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // 1. Laporan menolak rentang tanggal terbalik (dari > sampai) di halaman pratinjau
        $this->get('/admin/laporan?jenis=keuangan&dari=2026-11-25&sampai=2026-11-01')
            ->assertOk()
            ->assertSee('tidak boleh melebihi tanggal')
            ->assertDontSee('Export Excel');

        // 2. Admin tidak bisa mencabut role admin atau menonaktifkan akun sendiri
        $financeRoleId = Role::where('name', 'finance')->value('id');
        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['roles' => [$financeRoleId], 'is_active' => false])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $adminRoleId = Role::where('name', 'admin')->value('id');
        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['roles' => [$adminRoleId], 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($admin->fresh()->is_active);

        // 3. Modal View Penerimaan BBM menampilkan nomor, status, dan alasan pembatalan
        $penerimaan = $this->terima(5000);
        app(\App\Services\PenerimaanService::class)->batalkan($penerimaan, $admin, 'Koreksi DO uji');
        $modalTest = Livewire::actingAs($admin)->test(ListPenerimaan::class)
            ->mountTableAction('view', $penerimaan->fresh())
            ->assertSuccessful();
        $modalHtml = implode("\n", $modalTest->effects['partials'] ?? []) . $modalTest->html();
        $this->assertStringContainsString($penerimaan->nomor_penerimaan, $modalHtml);
        $this->assertStringContainsString('Informasi pembatalan', $modalHtml);
        $this->assertStringContainsString('Koreksi DO uji', $modalHtml);

        // 4. Sinkronisasi kuota surat rekomendasi bulan berjalan saat surat & pengaturan global diubah
        $this->isiStok(10000);
        [$anggota, $kapal, $surat, $userAnggota] = $this->buatAnggota('ANG-KTA');
        $this->actingAs($userAnggota);
        $p = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id, 'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 200, 'tanggal_rencana' => today()->addDay()->toDateString(),
        ]);
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);

        // Ubah kuota surat jadi 800 L -> batas_liter bulan berjalan ikut turun menjadi 800 L
        Livewire::actingAs($admin)->test(EditSuratRekomendasi::class, ['record' => $surat->getRouteKey()])
            ->fillForm(['kuota_bulanan_liter' => 800])
            ->call('save')
            ->assertHasNoFormErrors();
        $kuotaBulanIni = \App\Models\KuotaRekomendasiBulanan::where('surat_rekomendasi_id', $surat->id)->firstOrFail();
        $this->assertSame('800.00', (string) $kuotaBulanIni->batas_liter);

        // Ubah batas global kuota.surat_bulanan jadi 600 L -> batas_liter bulan berjalan ikut turun menjadi 600 L
        $settingKuota = Pengaturan::where('kunci', 'kuota.surat_bulanan')->firstOrFail();
        Livewire::actingAs($admin)->test(EditPengaturan::class, ['record' => $settingKuota->getRouteKey()])
            ->fillForm(['nilai_angka' => 600])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('600.00', (string) $kuotaBulanIni->fresh()->batas_liter);
    }

    public function test_perbaikan_whitebox_1_sampai_12(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email' => 'admin-unik@example.com'])->save();
        $this->actingAs($admin);

        // #1 Duplikat email anggota ditolak validasi form (bukan 500) & AnggotaService aman tanpa key email
        Livewire::actingAs($admin)->test(CreateAnggota::class)
            ->fillForm([
                'username' => 'anggota_dup',
                'password' => 'rahasia1234',
                'kode_anggota' => 'ANG-DUP',
                'nama_lengkap' => 'Anggota Duplikat',
                'email' => 'admin-unik@example.com',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $anggotaTanpaKeyEmail = app(\App\Services\AnggotaService::class)->buat([
            'kode_anggota' => 'ANG-NOEM',
            'nama_lengkap' => 'Tanpa Email Key',
            'username' => 'tanpaemailkey',
            'password' => 'rahasia1234',
        ], $admin);
        $this->assertNull($anggotaTanpaKeyEmail->email);

        // #2 Dec::n aman pada notasi ilmiah, spasi, dan input non-numerik
        $this->assertSame('0.00', \App\Support\Dec::n(0.00001));
        $this->assertSame('100.00', \App\Support\Dec::n(' 100 '));
        $this->assertSame('0.00', \App\Support\Dec::n('abc'));

        // #4 PenyesuaianTagihan (refund selisih) tampil di TagihanResource & dapat diselesaikan + muncul di Laporan Keuangan
        $this->isiStok(10000);
        [$anggota, $kapal, $surat, $userAnggota] = $this->buatAnggota('ANG-WB');
        $this->actingAs($userAnggota);
        $p = app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id, 'surat_rekomendasi_id' => $surat->id,
            'liter_diminta' => 200, 'tanggal_rencana' => today()->toDateString(),
        ]);
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        $p->refresh();
        $bayar = app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai', 'BKT-WB', now());
        app(PenyaluranService::class)->catat($p, 150, $admin, now());

        $tagihan = $p->tagihan->fresh();
        $this->assertSame('1', \App\Filament\Admin\Resources\TagihanResource::getNavigationBadge());
        Livewire::actingAs($admin)->test(ListTagihan::class)
            ->filterTable('status', null)
            ->filterTable('perlu_koreksi', true)
            ->assertSee('Refund Rp340.000')
            ->callTableAction('selesaikanKoreksi', $tagihan, ['catatan' => 'Refund tunai diserahkan'])
            ->assertHasNoTableActionErrors();
        $this->assertNull(\App\Filament\Admin\Resources\TagihanResource::getNavigationBadge());

        // #5 Penerimaan backdate mencatat tanggal mutasi stok sesuai tanggal penerimaan
        $bulanLalu = now()->subMonth()->startOfMonth()->addDays(4);
        $penerimaanLalu = app(\App\Services\PenerimaanService::class)->catat([
            'tanggal_penerimaan' => $bulanLalu->toDateString(),
            'nama_pemasok' => 'Pertamina Bulan Lalu',
            'liter' => 4000,
        ], $admin);
        $mutasiLalu = \App\Models\MutasiStok::where('referensi_id', $penerimaanLalu->id)
            ->where('jenis', \App\Enums\JenisMutasi::Penerimaan->value)
            ->firstOrFail();
        $this->assertSame($bulanLalu->toDateString(), $mutasiLalu->tanggal->toDateString());
        $this->assertEquals(4000.0, (float) $mutasiLalu->delta_fisik);

        // #6 LaporanExport menetralkan Excel formula injection
        $export = new \App\Exports\LaporanExport([
            'judul' => 'Uji', 'periode' => 'Hari ini', 'ringkasan' => [],
            'headings' => ['Nama'], 'rows' => [['=SUM(A1:A2)'], ['+cmd'], ['-10'], ['@test'], ['Normal']],
        ]);
        $rows = array_slice($export->array(), 5);
        $this->assertSame("'=SUM(A1:A2)", $rows[0][0]);
        $this->assertSame("'+cmd", $rows[1][0]);
        $this->assertSame("'-10", $rows[2][0]);
        $this->assertSame("'@test", $rows[3][0]);
        $this->assertSame('Normal', $rows[4][0]);

        // #7 Filter cari di laporan surat & filter anggota_id di laporan keuangan bekerja
        $this->assertCount(0, app(LaporanService::class)->bangun('surat', ['cari' => 'TIDAK-ADA-999'])['rows']);
        $lapKeuKosong = app(LaporanService::class)->bangun('keuangan', ['anggota_id' => $anggotaTanpaKeyEmail->id]);
        $this->assertSame('Rp0', $lapKeuKosong['ringkasan']['Total pembayaran diterima']);

        // #8 Berita berstatus Terbit wajib/otomatis punya published_at sehingga tampil di publik
        $beritaAuto = Berita::create([
            'judul' => 'Berita Auto Terbit', 'slug' => 'berita-auto-terbit', 'konten' => '<p>Isi</p>',
            'status' => StatusBerita::Terbit, 'published_at' => null, 'penulis_id' => $admin->id,
        ]);
        $this->assertNotNull($beritaAuto->published_at);
        $this->assertSame(1, Berita::terbit()->whereKey($beritaAuto->id)->count());

        // #9 Surat dengan berlaku_mulai di masa depan berstatus 'belum_berlaku'
        $suratFuture = \App\Models\SuratRekomendasi::create([
            'nomor_surat' => 'SR-WB-FUTURE', 'anggota_id' => $anggota->id, 'kapal_id' => $kapal->id,
            'berlaku_mulai' => today()->addDays(5), 'berlaku_sampai' => today()->addMonths(2),
            'kuota_bulanan_liter' => 500, 'status' => StatusAktif::Aktif,
        ]);
        $this->assertSame('belum_berlaku', $suratFuture->status_tampil);

        // #10 Form SuratRekomendasi menolak kapal nonaktif saat create
        $kapalNonaktif = \App\Models\Kapal::create([
            'kode_kapal' => 'KPL-NON', 'anggota_id' => $anggota->id, 'nama_kapal' => 'KM Nonaktif', 'status' => StatusAktif::Nonaktif,
        ]);
        Livewire::actingAs($admin)->test(CreateSuratRekomendasi::class)
            ->fillForm([
                'anggota_id' => $anggota->id, 'kapal_id' => $kapalNonaktif->id, 'nomor_surat' => 'SR-FAIL-NON',
                'berlaku_mulai' => today()->toDateString(), 'berlaku_sampai' => today()->addMonth()->toDateString(),
                'kuota_bulanan_liter' => 500, 'status' => StatusAktif::Aktif->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['kapal_id']);

        // #12 Harga sama dengan harga aktif ditolak & Pengaturan email menolak format invalid
        $this->expectException(\App\Exceptions\BisnisException::class);
        app(\App\Services\HargaService::class)->ubah(app(\App\Services\HargaService::class)->aktif(), 'Sama', $admin);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        gc_collect_cycles();
    }
}
