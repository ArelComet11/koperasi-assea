<?php

namespace Tests\Feature;

use App\Enums\StatusPengajuan;
use App\Exceptions\BisnisException;
use App\Models\PengajuanPembelian;
use App\Services\HargaService;
use App\Services\JadwalService;
use App\Services\PembayaranService;
use App\Services\PengajuanService;
use App\Services\PenyaluranService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class AlurPengajuanTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    private function ajukan($anggota, $kapal, $surat, $user, $liter): PengajuanPembelian
    {
        $this->actingAs($user);

        return app(PengajuanService::class)->ajukan($anggota, [
            'kapal_id' => $kapal->id, 'surat_rekomendasi_id' => $surat->id, 'liter_diminta' => $liter, 'tanggal_rencana' => today()->toDateString(),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
    }

    public function test_alur_lengkap_sampai_transaksi_selesai(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 500);
        $this->assertSame(StatusPengajuan::MenungguPersetujuan, $p->status);

        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        $p->refresh();

        $r = app(StokService::class)->ringkasan();
        $this->assertSame('20000.00', $r['fisik']);          // stok fisik tidak berubah saat disetujui (tanpa alokasi stok)
        $this->assertSame('20000.00', $r['tersedia']);
        $this->assertArrayNotHasKey('dialokasikan', $r);
        $this->assertSame('6800.00', $p->harga_per_liter);
        $this->assertSame('3400000.00', $p->total_tagihan);   // 500 x Rp6.800
        $this->assertSame(today()->startOfMonth()->toDateString(), $p->periode_kuota->toDateString());

        app(JadwalService::class)->buat($p, now()->addDay(), null, $admin);

        $fin = $this->finance();
        $this->actingAs($fin);
        app(PembayaranService::class)->catat($p->tagihan, $fin, 'tunai');
        $p->refresh();
        $this->assertNotSame(StatusPengajuan::Selesai, $p->status, 'Lunas tetapi belum disalurkan: belum selesai');

        $this->actingAs($admin);
        app(PenyaluranService::class)->catat($p, 500, $admin);
        $p->refresh();

        $this->assertSame(StatusPengajuan::Selesai, $p->status);
        $r = app(StokService::class)->ringkasan();
        $this->assertSame('19500.00', $r['fisik']);
        $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);
    }

    public function test_kuota_1000_liter_per_surat_per_bulan(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();

        try {
            $this->ajukan($a, $k, $s, $u, 1001);
            $this->fail('Seharusnya ditolak: melebihi kuota');
        } catch (BisnisException $e) {
            $this->assertStringContainsString('kuota', mb_strtolower($e->getMessage()));
        }

        $p = $this->ajukan($a, $k, $s, $u, 600);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);

        $this->expectException(BisnisException::class);
        $this->ajukan($a, $k, $s, $u, 500); // sisa kuota tinggal 400
    }

    public function test_kuota_dihitung_terpisah_untuk_tiap_surat(): void
    {
        $this->isiStok(20000);
        [$a1, $k1, $s1, $u1] = $this->buatAnggota('ANG-001');
        [$a2, $k2, $s2, $u2] = $this->buatAnggota('ANG-002');
        $admin = $this->admin();

        foreach ([[$a1, $k1, $s1, $u1], [$a2, $k2, $s2, $u2]] as [$a, $k, $s, $u]) {
            $p = $this->ajukan($a, $k, $s, $u, 1000);
            $this->actingAs($admin);
            app(PengajuanService::class)->setujui($p, $admin);
        }

        $this->assertSame('0.00', app(\App\Services\KuotaService::class)->sisa($s1));
        $this->assertSame('0.00', app(\App\Services\KuotaService::class)->sisa($s2));
        $this->assertSame('20000.00', app(StokService::class)->ringkasan()['fisik']);
    }

    public function test_stok_tersedia_tidak_cukup_ditolak(): void
    {
        $this->isiStok(100);
        [$a, $k, $s, $u] = $this->buatAnggota();

        $this->expectException(BisnisException::class);
        $this->ajukan($a, $k, $s, $u, 200);
    }

    public function test_persetujuan_ganda_ditolak(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 100);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);

        $this->expectException(BisnisException::class);
        app(PengajuanService::class)->setujui(PengajuanPembelian::find($p->id), $admin);
    }

    public function test_tolak_wajib_alasan_dan_tidak_mengubah_stok_atau_kuota(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 100);
        $admin = $this->admin();
        $this->actingAs($admin);

        try {
            app(PengajuanService::class)->tolak($p, $admin, '  ');
            $this->fail('Alasan kosong harus ditolak');
        } catch (BisnisException) {
            $this->assertTrue(true);
        }

        app(PengajuanService::class)->tolak($p, $admin, 'Dokumen tidak lengkap');
        $p->refresh();
        $this->assertSame(StatusPengajuan::Ditolak, $p->status);
        $this->assertSame('20000.00', app(StokService::class)->ringkasan()['fisik']);
        $this->assertSame('1000.00', app(\App\Services\KuotaService::class)->sisa($s));
    }

    public function test_batalkan_melepas_kuota_tanpa_mengubah_stok_fisik(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 700);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        $this->assertSame('300.00', app(\App\Services\KuotaService::class)->sisa($s));

        app(PengajuanService::class)->batalkan($p, $admin, 'Anggota berhalangan');
        $r = app(StokService::class)->ringkasan();

        $this->assertSame('20000.00', $r['fisik']);
        $this->assertSame('1000.00', app(\App\Services\KuotaService::class)->sisa($s));
        $this->assertSame(StatusPengajuan::Dibatalkan, $p->fresh()->status);
        $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);
    }

    public function test_harga_baru_tidak_mengubah_transaksi_lama(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 100);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);

        app(HargaService::class)->ubah(7000, 'Penyesuaian harga', $admin);

        $this->assertSame('6800.00', $p->fresh()->harga_per_liter);
        $this->assertSame('680000.00', $p->fresh()->tagihan->total);
        $this->assertSame('7000.00', app(HargaService::class)->aktif());
    }

    public function test_pembayaran_hanya_satu_kali_dan_penuh(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 100);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);

        $bayar = app(PembayaranService::class)->catat($p->tagihan, $admin, 'transfer', 'TRX-1');
        $this->assertSame($p->fresh()->tagihan->total, $bayar->jumlah);

        $this->expectException(BisnisException::class);
        app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai');
    }

    public function test_penyaluran_tidak_boleh_melebihi_disetujui_dan_sisa_menyesuaikan_tagihan(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 500);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);

        try {
            app(PenyaluranService::class)->catat($p, 501, $admin);
            $this->fail('Melebihi jumlah disetujui harus ditolak');
        } catch (BisnisException) {
            $this->assertTrue(true);
        }

        app(PenyaluranService::class)->catat($p, 400, $admin);
        $r = app(StokService::class)->ringkasan();
        $this->assertSame('19600.00', $r['fisik']);
        $this->assertSame('2720000.00', $p->fresh()->tagihan->total); // 400 x 6800, belum lunas -> disesuaikan
        $this->assertSame('600.00', app(\App\Services\KuotaService::class)->sisa($s)); // hanya 400 terpakai
        $this->assertTrue(app(StokService::class)->verifikasi()['konsisten']);
    }

    public function test_pembatalan_diblokir_setelah_lunas(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 100);
        $admin = $this->admin();
        $this->actingAs($admin);
        app(PengajuanService::class)->setujui($p, $admin);
        app(PembayaranService::class)->catat($p->tagihan, $admin, 'tunai');

        $this->expectException(BisnisException::class);
        app(PengajuanService::class)->batalkan($p, $admin, 'Uji');
    }

    public function test_finance_tidak_dapat_menyetujui_atau_mengubah_harga(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        $p = $this->ajukan($a, $k, $s, $u, 100);
        $fin = $this->finance();
        $this->actingAs($fin);

        try {
            app(PengajuanService::class)->setujui($p, $fin);
            $this->fail('Finance tidak boleh menyetujui');
        } catch (BisnisException) {
            $this->assertTrue(true);
        }

        $this->expectException(BisnisException::class);
        app(HargaService::class)->ubah(9000, 'coba', $fin);
    }

    public function test_anggota_nonaktif_tidak_dapat_mengajukan(): void
    {
        $this->isiStok(20000);
        [$a, $k, $s, $u] = $this->buatAnggota();
        app(\App\Services\AnggotaService::class)->setStatus($a, \App\Enums\StatusAktif::Nonaktif, $this->admin());

        $this->expectException(BisnisException::class);
        $this->ajukan($a->fresh(), $k, $s, $u->fresh(), 100);
    }
}
