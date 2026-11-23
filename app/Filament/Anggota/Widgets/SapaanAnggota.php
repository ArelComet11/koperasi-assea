<?php

namespace App\Filament\Anggota\Widgets;

use App\Support\Otorisasi;
use Filament\Widgets\Widget;

class SapaanAnggota extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.sapaan';

    public static function canView(): bool
    {
        return (bool) Otorisasi::user()?->hasRole('anggota');
    }

    protected function getViewData(): array
    {
        $user = Otorisasi::userWajib();
        $a = $user->anggota;

        return ['nama' => $a?->nama_lengkap ?? $user->name, 'kode' => $a?->kode_anggota ?? '-'];
    }
}
