<?php

namespace App\Services;

use App\Enums\StatusAktif;
use App\Exceptions\BisnisException;
use App\Models\Anggota;
use App\Models\User;
use App\Support\Otorisasi;
use Illuminate\Support\Facades\DB;

class AnggotaService
{
    /** Akun anggota hanya dibuat oleh admin; tidak ada registrasi publik. */
    public function buat(array $data, User $oleh): Anggota
    {
        Otorisasi::pastikan($oleh, 'anggota.create');
        $this->validasiPassword($data['password'] ?? '');

        $email = isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null;
        if ($email !== null && User::query()->where('email', $email)->exists()) {
            throw new BisnisException('Alamat email sudah digunakan oleh akun lain.');
        }

        return DB::transaction(function () use ($data, $oleh, $email) {
            $user = new User();
            $user->forceFill([
                'username' => $data['username'], 'name' => $data['nama_lengkap'],
                'email' => $email, 'password' => $data['password'], 'is_active' => true,
            ])->save();
            $user->assignRole('anggota');

            $anggota = new Anggota();
            $anggota->fill([
                'kode_anggota' => $data['kode_anggota'], 'nama_lengkap' => $data['nama_lengkap'],
                'alamat' => $data['alamat'] ?? null, 'telepon' => $data['telepon'] ?? null, 'email' => $email,
                'status' => StatusAktif::Aktif->value, 'tanggal_bergabung' => $data['tanggal_bergabung'] ?? today()->toDateString(),
                'keterangan' => $data['keterangan'] ?? null,
            ]);
            $anggota->user_id = $user->id;
            $anggota->save();

            AuditService::catat('anggota.dibuat', $anggota, null, ['kode' => $anggota->kode_anggota, 'username' => $user->username], $anggota->nama_lengkap, $oleh);

            return $anggota;
        });
    }

    public function perbarui(Anggota $anggota, array $data, User $oleh): Anggota
    {
        Otorisasi::pastikan($oleh, 'anggota.update');

        $email = array_key_exists('email', $data)
            ? (trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null)
            : $anggota->email;
        if ($email !== null && User::query()->where('email', $email)->whereKeyNot($anggota->user_id)->exists()) {
            throw new BisnisException('Alamat email sudah digunakan oleh akun lain.');
        }

        return DB::transaction(function () use ($anggota, $data, $oleh, $email) {
            $lama = $anggota->only(['kode_anggota', 'nama_lengkap', 'alamat', 'telepon', 'email', 'keterangan']);
            $data['email'] = $email;
            $anggota->fill(array_intersect_key($data, array_flip(['kode_anggota', 'nama_lengkap', 'alamat', 'telepon', 'email', 'tanggal_bergabung', 'keterangan'])))->save();

            $user = $anggota->user;
            $user->name = $anggota->nama_lengkap;
            $user->email = $email;
            if (! empty($data['username']) && $data['username'] !== $user->username) {
                $user->username = $data['username'];
            }
            $user->save();

            AuditService::catat('anggota.diubah', $anggota, $lama, $anggota->only(array_keys($lama)), $anggota->nama_lengkap, $oleh);

            return $anggota;
        });
    }

    public function setStatus(Anggota $anggota, StatusAktif $status, User $oleh): Anggota
    {
        Otorisasi::pastikan($oleh, 'anggota.nonaktif');

        return DB::transaction(function () use ($anggota, $status, $oleh) {
            $anggota->forceFill(['status' => $status])->save();
            $anggota->user->forceFill(['is_active' => $status === StatusAktif::Aktif])->save();

            if ($status === StatusAktif::Nonaktif) {
                DB::table('sessions')->where('user_id', $anggota->user_id)->delete();
            }

            AuditService::catat('anggota.status_diubah', $anggota, null, ['status' => $status->value], $anggota->nama_lengkap, $oleh);

            return $anggota;
        });
    }

    public function resetPassword(Anggota $anggota, string $passwordBaru, User $oleh): void
    {
        Otorisasi::pastikan($oleh, 'anggota.reset_password');
        $this->validasiPassword($passwordBaru);

        $anggota->user->forceFill(['password' => $passwordBaru])->save();
        DB::table('sessions')->where('user_id', $anggota->user_id)->delete();
        AuditService::catat('anggota.reset_password', $anggota, null, null, $anggota->nama_lengkap, $oleh);
    }

    private function validasiPassword(string $password): void
    {
        if (mb_strlen($password) < 8) {
            throw new BisnisException('Kata sandi minimal 8 karakter.');
        }
    }
}
