<?php

namespace Tests\Feature;

use App\Models\Kapal;
use App\Models\PengajuanPembelian;
use App\Models\SuratRekomendasi;
use App\Services\PengajuanService;
use App\Services\PembayaranService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class AksesDanIsolasiTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
    }

    public function test_halaman_publik_dan_login_tersedia(): void
    {
        foreach (['/', '/profil', '/layanan', '/berita', '/galeri', '/kontak', '/admin/login', '/anggota/login'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_tidak_ada_registrasi_publik(): void
    {
        $this->get('/admin/register')->assertNotFound();
        $this->get('/anggota/register')->assertNotFound();
        $this->get('/register')->assertNotFound();
    }

    public function test_admin_dapat_buka_halaman_edit_user(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->get("/admin/users/{$admin->id}/edit")
            ->assertOk();
    }

    public function test_pembatasan_akses_panel_menurut_role(): void
    {
        [$a, , , $u] = $this->buatAnggota();

        $this->actingAs($this->admin())->get('/admin')->assertOk();
        $this->flushSession();
        $this->actingAs($this->finance())->get('/admin')->assertOk();
        $this->flushSession();
        $this->actingAs($u)->get('/admin')->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->finance())->get('/anggota')->assertForbidden();
        $this->flushSession();
        $this->actingAs($u)->get('/anggota')->assertOk();
    }

    public function test_anggota_nonaktif_tidak_dapat_masuk_panel(): void
    {
        [$a, , , $u] = $this->buatAnggota();
        app(\App\Services\AnggotaService::class)->setStatus($a, \App\Enums\StatusAktif::Nonaktif, $this->admin());

        $this->actingAs($u->fresh())->get('/anggota')->assertForbidden();
    }

    public function test_finance_tidak_melihat_menu_data_induk(): void
    {
        $this->actingAs($this->finance())
            ->get('/admin/anggota')->assertForbidden();
        $this->actingAs($this->finance())->get('/admin/tagihan')->assertOk();
    }

    public function test_anggota_hanya_melihat_data_miliknya_lewat_model(): void
    {
        [$a1, $k1, $s1, $u1] = $this->buatAnggota('ANG-001');
        [$a2, $k2, $s2, $u2] = $this->buatAnggota('ANG-002');

        $this->actingAs($u1);
        $this->assertSame([$k1->id], Kapal::query()->pluck('id')->all());
        $this->assertNull(Kapal::query()->find($k2->id));
        $this->assertNull(SuratRekomendasi::query()->find($s2->id));
    }

    public function test_anggota_tidak_dapat_membuka_dokumen_dan_bukti_anggota_lain(): void
    {
        $this->terima(5000);
        [$a1, $k1, $s1, $u1] = $this->buatAnggota('ANG-001');
        [$a2, $k2, $s2, $u2] = $this->buatAnggota('ANG-002');

        $this->actingAs($u2);
        $p = app(PengajuanService::class)->ajukan($a2, ['kapal_id' => $k2->id, 'surat_rekomendasi_id' => $s2->id, 'liter_diminta' => 100, 'tanggal_rencana' => today()->toDateString()]);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        $bayar = app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai');

        $this->actingAs($u1)->get(route('cetak.pembayaran', $bayar->id))->assertNotFound();
        $this->actingAs($u1)->get(route('dokumen.surat', $s2->id))->assertNotFound();
        $this->actingAs($u2)->get(route('cetak.pembayaran', $bayar->id))->assertOk();
        $this->actingAs($u1)->get('/anggota/pengajuan-saya/' . $p->id)->assertNotFound();
    }

    public function test_anggota_hanya_melihat_pengajuan_sendiri(): void
    {
        $this->terima(5000);
        [$a1, $k1, $s1, $u1] = $this->buatAnggota('ANG-001');
        [$a2, $k2, $s2, $u2] = $this->buatAnggota('ANG-002');
        $this->actingAs($u2);
        app(PengajuanService::class)->ajukan($a2, ['kapal_id' => $k2->id, 'surat_rekomendasi_id' => $s2->id, 'liter_diminta' => 100, 'tanggal_rencana' => today()->toDateString()]);

        $this->actingAs($u1);
        $this->assertSame(0, PengajuanPembelian::query()->count());
    }

    public function test_anggota_tidak_dapat_memakai_kapal_atau_surat_anggota_lain(): void
    {
        $this->terima(5000);
        [$a1, $k1, $s1, $u1] = $this->buatAnggota('ANG-001');
        [$a2, $k2, $s2, $u2] = $this->buatAnggota('ANG-002');
        $this->actingAs($u1);

        $this->expectException(\App\Exceptions\BisnisException::class);
        app(PengajuanService::class)->ajukan($a1, ['kapal_id' => $k2->id, 'surat_rekomendasi_id' => $s2->id, 'liter_diminta' => 100, 'tanggal_rencana' => today()->toDateString()]);
    }

    public function test_database_menolak_surat_untuk_kapal_milik_anggota_lain(): void
    {
        [$a1, $k1] = $this->buatAnggota('ANG-001');
        [$a2] = $this->buatAnggota('ANG-002');

        $this->expectException(QueryException::class);
        SuratRekomendasi::create([
            'nomor_surat' => 'SR-SALAH', 'anggota_id' => $a2->id, 'kapal_id' => $k1->id,
            'berlaku_mulai' => today(), 'berlaku_sampai' => today()->addMonth(), 'kuota_bulanan_liter' => 1000, 'status' => 'aktif',
        ]);
    }

    public function test_username_harus_unik(): void
    {
        $this->buatAnggota('ANG-001');

        $this->expectException(\Throwable::class);
        $this->buatAnggota('ANG-001');
    }
}
