<?php

namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\SuratRekomendasi;
use App\Models\User;
use App\Services\AnggotaService;
use App\Services\PenerimaanService;
use App\Services\StokService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** DATA CONTOH untuk uji coba. Jangan dijalankan di server produksi: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->firstOrFail();
        auth()->setUser($admin);

        DB::transaction(function () use ($admin) {
            app(StokService::class)->saldoAwal(20000, $admin);
            app(PenerimaanService::class)->catat([
                'tanggal_penerimaan' => today()->toDateString(), 'nama_pemasok' => 'Pemasok Contoh', 'liter' => 16000,
                'nomor_dokumen' => 'DOK-CONTOH-001',
            ], $admin);
        });

        if (Anggota::query()->where('kode_anggota', 'ANG-CONTOH-001')->exists()) {
            return;
        }

        $anggota = app(AnggotaService::class)->buat([
            'kode_anggota' => 'ANG-CONTOH-001', 'nama_lengkap' => 'Anggota Contoh', 'username' => 'anggota.contoh',
            'password' => 'ContohAnggota123', 'alamat' => 'Alamat contoh', 'telepon' => '0800000000', 'email' => null,
        ], $admin);

        $kapal = Kapal::create([
            'kode_kapal' => 'KPL-CONTOH-001', 'anggota_id' => $anggota->id, 'nama_kapal' => 'KM Contoh',
            'nama_pemilik' => $anggota->nama_lengkap, 'jenis_kapal' => 'Kapal nelayan', 'status' => 'aktif',
        ]);

        SuratRekomendasi::create([
            'nomor_surat' => 'SR-CONTOH-001', 'anggota_id' => $anggota->id, 'kapal_id' => $kapal->id,
            'tanggal_surat' => today(), 'berlaku_mulai' => today()->startOfYear(), 'berlaku_sampai' => today()->endOfYear(),
            'kuota_bulanan_liter' => 1000, 'status' => 'aktif',
        ]);
    }
}
