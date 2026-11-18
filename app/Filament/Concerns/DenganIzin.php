<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Otorisasi resource berbasis permission Spatie. Resource mendeklarasikan:
 *   protected static string $izinLihat; protected static ?string $izinBuat; protected static ?string $izinUbah;
 * Izin null berarti aksi tersebut tidak tersedia. Penghapusan tidak pernah tersedia di UI (pakai status nonaktif).
 */
trait DenganIzin
{
    protected static function punya(?string $izin): bool
    {
        return $izin !== null && (auth()->user()?->can($izin) ?? false);
    }

    public static function canViewAny(): bool { return static::punya(static::$izinLihat); }
    public static function canView(Model $record): bool { return static::punya(static::$izinLihat); }
    public static function canCreate(): bool { return static::punya(static::$izinBuat ?? null); }
    public static function canEdit(Model $record): bool { return static::punya(static::$izinUbah ?? null); }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
}
