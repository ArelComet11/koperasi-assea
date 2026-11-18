<?php

namespace App\Filament\Admin\Widgets;

use App\Services\StokService;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StokStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('stok.view');
    }

    protected function getStats(): array
    {
        $s = app(StokService::class)->ringkasan();

        return [
            Stat::make('Stok fisik saat ini', Format::liter($s['fisik']))->icon('heroicon-o-archive-box')->description('Awal + penerimaan − penyaluran'),
            Stat::make('Stok dialokasikan', Format::liter($s['dialokasikan']))->icon('heroicon-o-lock-closed')->description('Dicadangkan untuk pengajuan disetujui')->color('warning'),
            Stat::make('Stok tersedia', Format::liter($s['tersedia']))->icon('heroicon-o-check-circle')->description('Fisik − dialokasikan')->color('success'),
            Stat::make('Saldo awal', Format::liter($s['awal'])),
            Stat::make('Total penerimaan', Format::liter($s['penerimaan'])),
            Stat::make('Total penyaluran', Format::liter($s['penyaluran'])),
        ];
    }
}
