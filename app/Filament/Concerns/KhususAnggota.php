<?php

namespace App\Filament\Concerns;

use App\Support\Otorisasi;
use Illuminate\Database\Eloquent\Model;

/** Resource panel anggota: hanya-baca (kecuali dinyatakan lain). Isolasi data dijamin global scope pada model. */
trait KhususAnggota
{
    public static function canViewAny(): bool { return (bool) Otorisasi::user()?->hasRole('anggota'); }
    public static function canView(Model $record): bool { return static::canViewAny(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
}
