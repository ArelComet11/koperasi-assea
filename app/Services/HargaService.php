<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\RiwayatHarga;
use App\Models\User;
use App\Support\Dec;
use App\Support\Otorisasi;
use Illuminate\Support\Facades\DB;

class HargaService
{
    public const HARGA_AWAL = '6800.00';

    public function aktif(): string
    {
        $h = RiwayatHarga::query()->orderByDesc('berlaku_mulai')->orderByDesc('id')->value('harga_per_liter');

        return Dec::n($h ?? self::HARGA_AWAL);
    }

    public function ubah(string|int|float $harga, string $alasan, User $user): RiwayatHarga
    {
        Otorisasi::pastikan($user, 'harga.update');
        $harga = Dec::n($harga);

        if (Dec::lte($harga, 0)) {
            throw new BisnisException('Harga per liter harus lebih dari 0.');
        }
        if (trim($alasan) === '') {
            throw new BisnisException('Alasan perubahan harga wajib diisi.');
        }

        return DB::transaction(function () use ($harga, $alasan, $user) {
            $lama = $this->aktif();
            $baris = RiwayatHarga::create([
                'harga_per_liter' => $harga, 'berlaku_mulai' => now(), 'diubah_oleh' => $user->id, 'alasan' => trim($alasan),
            ]);
            AuditService::catat('harga.diubah', $baris, ['harga_per_liter' => $lama], ['harga_per_liter' => $harga], $alasan, $user);

            return $baris;
        });
    }
}
