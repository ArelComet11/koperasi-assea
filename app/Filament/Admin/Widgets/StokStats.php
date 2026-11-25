<?php

namespace App\Filament\Admin\Widgets;

use App\Services\StokService;
use App\Support\Format;
use App\Support\Otorisasi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StokStats extends StatsOverviewWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return Otorisasi::bisa('stok.view');
    }

    protected function getStats(): array
    {
        $s = app(StokService::class)->ringkasan();

        return [
            Stat::make('Stok fisik saat ini', Format::liter($s['fisik']))->icon('heroicon-o-archive-box')->description('Penerimaan − penyaluran')->color('success'),
            Stat::make('Total penerimaan', Format::liter($s['penerimaan'])),
            Stat::make('Total penyaluran', Format::liter($s['penyaluran'])),
        ];
    }
}
