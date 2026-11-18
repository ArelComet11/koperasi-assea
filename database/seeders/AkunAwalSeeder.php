<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AkunAwalSeeder extends Seeder
{
    public function run(): void
    {
        $this->buat('admin', 'Administrator', 'admin', env('SEED_ADMIN_USERNAME', 'admin'), env('SEED_ADMIN_PASSWORD'));
        $this->buat('finance', 'Petugas Keuangan', 'finance', env('SEED_FINANCE_USERNAME', 'finance'), env('SEED_FINANCE_PASSWORD'));
    }

    private function buat(string $role, string $nama, string $_, string $username, ?string $password): void
    {
        if (User::query()->where('username', $username)->exists()) {
            return;
        }

        $generated = false;
        if (! $password) {
            $password = Str::password(14, symbols: false);
            $generated = true;
        }

        $user = new User();
        $user->forceFill(['username' => $username, 'name' => $nama, 'password' => $password, 'is_active' => true])->save();
        $user->assignRole($role);

        $this->command?->info("Akun {$role} dibuat: username={$username}" . ($generated ? " password={$password}  (CATAT, hanya ditampilkan sekali)" : ''));
    }
}
