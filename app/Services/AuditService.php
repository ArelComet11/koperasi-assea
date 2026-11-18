<?php

namespace App\Services;

use App\Models\AktivitasAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    private const RAHASIA = ['password', 'remember_token'];

    public static function catat(string $jenis, ?Model $subjek = null, ?array $lama = null, ?array $baru = null, ?string $keterangan = null, ?User $user = null): void
    {
        $user ??= auth()->user();
        $web = ! app()->runningInConsole() && request();

        AktivitasAdmin::create([
            'user_id' => $user?->id,
            'nama_pengguna' => $user?->name,
            'jenis' => $jenis,
            'subjek_type' => $subjek?->getMorphClass(),
            'subjek_id' => $subjek?->getKey(),
            'data_lama' => $lama ? self::bersihkan($lama) : null,
            'data_baru' => $baru ? self::bersihkan($baru) : null,
            'keterangan' => $keterangan,
            'ip_address' => $web ? request()->ip() : null,
            'user_agent' => $web ? mb_substr((string) request()->userAgent(), 0, 255) : null,
        ]);
    }

    private static function bersihkan(array $data): array
    {
        return array_diff_key($data, array_flip(self::RAHASIA));
    }
}
