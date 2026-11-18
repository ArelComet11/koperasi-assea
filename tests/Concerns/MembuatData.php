<?php

namespace Tests\Concerns;

use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\SuratRekomendasi;
use App\Models\User;
use App\Services\AnggotaService;
use App\Services\PenerimaanService;
use App\Services\StokService;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

trait MembuatData
{
    protected function seedDasar(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(PengaturanSeeder::class);
    }

    protected function buatStaf(string $role, string $username): User
    {
        $u = new User();
        $u->forceFill(['username' => $username, 'name' => ucfirst($username), 'password' => 'password123', 'is_active' => true])->save();
        $u->assignRole($role);

        return $u;
    }

    protected function admin(): User
    {
        return User::query()->where('username', 'admin')->first() ?? $this->buatStaf('admin', 'admin');
    }

    protected function finance(): User
    {
        return User::query()->where('username', 'finance')->first() ?? $this->buatStaf('finance', 'finance');
    }

    /** @return array{0:Anggota,1:Kapal,2:SuratRekomendasi,3:User} */
    protected function buatAnggota(string $kode = 'ANG-001'): array
    {
        $admin = $this->admin();
        $anggota = app(AnggotaService::class)->buat([
            'kode_anggota' => $kode, 'nama_lengkap' => "Anggota {$kode}", 'username' => strtolower($kode),
            'password' => 'rahasia1234', 'email' => null,
        ], $admin);

        $kapal = Kapal::create(['kode_kapal' => "KPL-{$kode}", 'anggota_id' => $anggota->id, 'nama_kapal' => "KM {$kode}", 'status' => 'aktif']);
        $surat = SuratRekomendasi::create([
            'nomor_surat' => "SR-{$kode}", 'anggota_id' => $anggota->id, 'kapal_id' => $kapal->id,
            'berlaku_mulai' => today()->startOfYear(), 'berlaku_sampai' => today()->endOfYear(), 'kuota_bulanan_liter' => 1000, 'status' => 'aktif',
        ]);

        return [$anggota, $kapal, $surat, $anggota->user];
    }

    protected function isiStok(float|int $liter = 20000): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        DB::transaction(function () use ($liter, $admin) {
            app(StokService::class)->saldoAwal($liter, $admin);
        });
    }

    protected function terima(float|int $liter, ?string $tanggal = null, bool $override = false, ?string $alasan = null)
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        return app(PenerimaanService::class)->catat([
            'tanggal_penerimaan' => $tanggal ?? today()->toDateString(), 'nama_pemasok' => 'Pemasok Uji', 'liter' => $liter,
            'override_pasokan' => $override, 'alasan_override' => $alasan,
        ], $admin);
    }
}
