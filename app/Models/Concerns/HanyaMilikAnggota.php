<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Lapisan isolasi data: bila pengguna yang login berperan "anggota", SEMUA query pada model ini
 * (termasuk relasi, route binding Filament, dan manipulasi URL) otomatis dibatasi ke data miliknya.
 * Model pemakai wajib mengimplementasikan batasiKeAnggota().
 */
trait HanyaMilikAnggota
{
    public static function bootHanyaMilikAnggota(): void
    {
        static::addGlobalScope('milik_anggota', function (Builder $query) {
            $user = auth()->user();
            if ($user && $user->hasRole('anggota')) {
                static::batasiKeAnggota($query, (int) $user->anggota?->id);
            }
        });
    }

    abstract protected static function batasiKeAnggota(Builder $query, int $anggotaId): void;
}
