<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusAktif;
use App\Enums\StatusBerita;
use App\Filament\Pages\Login;
use App\Models\Berita;
use App\Models\Galeri;
use App\Models\KategoriBerita;
use App\Models\Pengurus;
use App\Models\Tagihan;
use App\Services\HargaService;
use App\Services\JadwalService;
use App\Services\LaporanService;
use App\Services\PembayaranService;
use App\Services\PengajuanService;
use App\Services\PenyaluranService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

/** Whitebox sweep menyeluruh: kondisi database kosong, publik, alur lengkap 3 peran, laporan, otorisasi, IDOR, dan edge-case filter. */
class FullWhiteboxSweepTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
    }

    public function test_kondisi_database_kosong_tidak_memicu_error_di_seluruh_halaman(): void
    {
        foreach (['/', '/profil', '/layanan', '/berita', '/galeri', '/kontak', '/admin/login', '/anggota/login'] as $uri) {
            $this->get($uri)->assertOk();
        }
        $this->get('/login')->assertRedirect('/anggota/login');
        $this->get('/login-anggota')->assertRedirect('/anggota/login');

        $admin = $this->admin();
        foreach ([
            '/admin', '/admin/anggota', '/admin/kapal', '/admin/surat-rekomendasi',
            '/admin/pengajuan-pembelian', '/admin/jadwal-pembelian', '/admin/penerimaan-bbm',
            '/admin/mutasi-stok', '/admin/persediaan-stok', '/admin/batas-pasokan',
            '/admin/monitoring-kuota', '/admin/tagihan', '/admin/pembayaran',
            '/admin/riwayat-harga', '/admin/laporan', '/admin/berita',
            '/admin/kategori-berita', '/admin/galeri', '/admin/pengurus',
            '/admin/pengaturan', '/admin/aktivitas-admin',
        ] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }

        $this->flushSession();
        $finance = $this->finance();
        foreach (['/admin', '/admin/pengajuan-pembelian', '/admin/tagihan', '/admin/pembayaran', '/admin/riwayat-harga', '/admin/laporan'] as $uri) {
            $this->actingAs($finance)->get($uri)->assertOk();
        }
    }

    public function test_whitebox_alur_lengkap_publik_admin_finance_anggota_idor_dan_edge_case(): void
    {
        $admin = $this->admin();
        $finance = $this->finance();
        $this->isiStok(20000);
        $this->terima(16000);
        app(HargaService::class)->ubah('7000', 'Penyesuaian harga audit', $admin);

        $kat = KategoriBerita::create(['nama' => 'Pengumuman', 'slug' => 'pengumuman']);
        $beritaTerbit = Berita::create([
            'kategori_id' => $kat->id, 'judul' => 'Jadwal Distribusi', 'slug' => 'jadwal-distribusi',
            'ringkasan' => 'Info distribusi', 'konten' => '<p>Konten berita.</p>',
            'status' => StatusBerita::Terbit, 'published_at' => now(), 'penulis_id' => $admin->id,
        ]);
        Berita::create([
            'kategori_id' => $kat->id, 'judul' => 'Draf Rahasia', 'slug' => 'draf-rahasia',
            'konten' => '<p>Draf</p>', 'status' => StatusBerita::Draft, 'penulis_id' => $admin->id,
        ]);
        Pengurus::create(['nama' => 'Ketua Koperasi', 'jabatan' => 'Ketua', 'urutan' => 1, 'tampil' => true]);
        Galeri::create(['judul' => 'Kegiatan', 'gambar' => 'galeri/sample.jpg', 'tampil' => true, 'urutan' => 1]);

        [$agtA, $kapalA, $suratA, $userA] = $this->buatAnggota('WBA');
        [$agtB, $kapalB, $suratB, $userB] = $this->buatAnggota('WBB');
        Storage::disk('local')->put('surat-rekomendasi/wb-a.pdf', '%PDF-1.4 alpha');
        $suratA->forceFill(['file_path' => 'surat-rekomendasi/wb-a.pdf'])->save();

        // Siklus penuh: ajukan -> setujui -> jadwal -> bayar (finance) -> salurkan kurang -> selesaikan refund (finance)
        $this->actingAs($userA);
        $p1 = app(PengajuanService::class)->ajukan($agtA, [
            'kapal_id' => $kapalA->id, 'surat_rekomendasi_id' => $suratA->id,
            'liter_diminta' => 100, 'tanggal_rencana' => today()->addDay()->toDateString(),
        ]);
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p1, $admin);
        app(JadwalService::class)->buat($p1, now()->addHours(2), 'Antrean pagi', $admin);
        $t1 = Tagihan::where('pengajuan_id', $p1->id)->firstOrFail();
        $b1 = app(PembayaranService::class)->catat($t1, $finance, MetodePembayaran::Transfer, 'TRF-WB-1', now());
        app(PenyaluranService::class)->catat($p1, '75', $admin, now(), 'Aktual 75 L');
        app(PembayaranService::class)->selesaikanPenyesuaian($t1, $finance, 'Refund tunai selesai');

        // 1. Publik + proteksi draf
        Auth::logout();
        $this->flushSession();
        foreach (['/', '/profil', '/layanan', '/berita', '/berita/jadwal-distribusi', '/galeri', '/kontak'] as $uri) {
            $this->get($uri)->assertOk();
        }
        $this->get('/berita/draf-rahasia')->assertNotFound();
        $this->get('/berita/tidak-ada-xyz')->assertNotFound();

        // 2. Admin: seluruh halaman + detail + semua 8 jenis laporan + edge-case filter tidak memicu 500
        $this->flushSession();
        foreach ([
            '/admin', '/admin/anggota', '/admin/anggota/' . $agtA->id, '/admin/anggota/' . $agtA->id . '/edit',
            '/admin/kapal', '/admin/surat-rekomendasi', '/admin/surat-rekomendasi/' . $suratA->id . '/edit',
            '/admin/pengajuan-pembelian', '/admin/pengajuan-pembelian/' . $p1->id,
            '/admin/jadwal-pembelian', '/admin/penerimaan-bbm', '/admin/mutasi-stok',
            '/admin/persediaan-stok', '/admin/batas-pasokan', '/admin/monitoring-kuota',
            '/admin/tagihan', '/admin/pembayaran', '/admin/riwayat-harga',
            '/admin/berita', '/admin/berita/' . $beritaTerbit->id . '/edit',
            '/admin/kategori-berita', '/admin/galeri', '/admin/pengurus', '/admin/pengaturan', '/admin/aktivitas-admin',
            '/dokumen/surat/' . $suratA->id, '/cetak/pembayaran/' . $b1->id,
        ] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
        foreach (array_keys(LaporanService::JENIS) as $j) {
            $this->actingAs($admin)->get("/admin/laporan?jenis={$j}")->assertOk();
            $this->actingAs($admin)->get("/laporan/cetak?jenis={$j}")->assertOk();
            $this->actingAs($admin)->get("/laporan/export?jenis={$j}")->assertOk();
        }
        foreach ([
            '/admin/laporan?jenis=keuangan&dari=2026-12-31&sampai=2026-01-01',
            '/admin/laporan?jenis=keuangan&dari=invalid-date',
            '/admin/laporan?jenis=keuangan&status=tidak_valid',
            '/admin/laporan?jenis=keuangan&cari=%27%22%3Cscript%3E',
            '/admin/laporan?jenis=tidak_ada',
        ] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }

        // 3. Finance: rute diizinkan vs rute ditolak (403)
        $this->flushSession();
        foreach ([
            '/admin', '/admin/pengajuan-pembelian', '/admin/pengajuan-pembelian/' . $p1->id,
            '/admin/tagihan', '/admin/pembayaran', '/admin/riwayat-harga',
            '/admin/laporan?jenis=keuangan', '/admin/laporan?jenis=realisasi',
            '/laporan/cetak?jenis=keuangan', '/laporan/export?jenis=keuangan',
            '/cetak/pembayaran/' . $b1->id,
        ] as $uri) {
            $this->actingAs($finance)->get($uri)->assertOk();
        }
        foreach ([
            '/admin/anggota', '/admin/kapal', '/admin/surat-rekomendasi', '/admin/jadwal-pembelian',
            '/admin/penerimaan-bbm', '/admin/persediaan-stok', '/admin/batas-pasokan',
            '/admin/berita', '/admin/pengaturan', '/admin/aktivitas-admin',
            '/dokumen/surat/' . $suratA->id, '/laporan/export?jenis=kapal',
            '/laporan/cetak?jenis=penjualan', '/anggota',
        ] as $uri) {
            $this->actingAs($finance)->get($uri)->assertForbidden();
        }
        $this->actingAs($finance)->get('/admin/laporan?jenis=kapal')
            ->assertOk()
            ->assertSee('tidak memiliki izin');

        // 4. Anggota A: seluruh halaman diizinkan; Anggota B: isolasi IDOR & larangan admin
        $this->flushSession();
        foreach ([
            '/anggota', '/anggota/kapal-saya', '/anggota/surat-rekomendasi',
            '/anggota/pengajuan-saya', '/anggota/pengajuan-saya/create',
            '/anggota/pengajuan-saya/' . $p1->id, '/anggota/jadwal-pembelian',
            '/anggota/riwayat-transaksi', '/anggota/profil-saya',
            '/dokumen/surat/' . $suratA->id, '/cetak/pembayaran/' . $b1->id,
        ] as $uri) {
            $this->actingAs($userA)->get($uri)->assertOk();
        }
        $this->flushSession();
        foreach (['/admin', '/laporan/export?jenis=keuangan', '/laporan/cetak?jenis=realisasi'] as $uri) {
            $this->actingAs($userB)->get($uri)->assertForbidden();
        }
        foreach (['/anggota/pengajuan-saya/' . $p1->id, '/dokumen/surat/' . $suratA->id, '/cetak/pembayaran/' . $b1->id] as $uri) {
            $this->actingAs($userB)->get($uri)->assertNotFound();
        }

        // 5. Autentikasi Livewire Login: Admin, Finance, Anggota, salah password, dan penolakan lintas panel
        Auth::logout();
        $this->flushSession();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)
            ->fillForm(['username' => $admin->username, 'password' => 'password123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertTrue(Auth::check() && Auth::id() === $admin->id);

        Auth::logout();
        $this->flushSession();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)
            ->fillForm(['username' => $finance->username, 'password' => 'password123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertTrue(Auth::check() && Auth::id() === $finance->id);

        Auth::logout();
        $this->flushSession();
        Filament::setCurrentPanel(Filament::getPanel('anggota'));
        Livewire::test(Login::class)
            ->fillForm(['username' => $userA->username, 'password' => 'rahasia1234'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertTrue(Auth::check() && Auth::id() === $userA->id);

        Auth::logout();
        $this->flushSession();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)
            ->fillForm(['username' => $userA->username, 'password' => 'rahasia1234'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);
        $this->assertFalse(Auth::check());

        Filament::setCurrentPanel(Filament::getPanel('anggota'));
        Livewire::test(Login::class)
            ->fillForm(['username' => $userA->username, 'password' => 'SalahPassword!'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);
        $this->assertFalse(Auth::check());
    }
}
