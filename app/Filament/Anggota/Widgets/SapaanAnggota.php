<?php

namespace App\Filament\Anggota\Widgets;

use Filament\Widgets\Widget;

class SapaanAnggota extends Widget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.sapaan';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole('anggota');
    }

    protected function getViewData(): array
    {
        $a = auth()->user()->anggota;

        return ['nama' => $a?->nama_lengkap ?? auth()->user()->name, 'kode' => $a?->kode_anggota ?? '-'];
    }
}
