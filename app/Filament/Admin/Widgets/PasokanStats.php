<?php

namespace App\Filament\Admin\Widgets;

use App\Services\PasokanService;
use App\Support\Format;
use App\Support\Otorisasi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PasokanStats extends StatsOverviewWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return Otorisasi::bisa('penerimaan.view');
    }

    protected function getHeading(): ?string
    {
        return 'Bulan ' . now()->translatedFormat('F Y');
    }

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $p = app(PasokanService::class)->ringkasan();

        return [
            Stat::make('Batas pasokan bulanan', Format::liter($p['batas'])),
            Stat::make('Total diterima', Format::liter($p['diterima']))->description('Sisa ruang penerimaan ' . Format::liter($p['sisa_penerimaan'])),
            Stat::make('Alokasi + tersalurkan', Format::liter($p['digunakan']))->description($p['persen'] . '% dari batas')->color($p['persen'] >= 90 ? 'danger' : ($p['persen'] >= 70 ? 'warning' : 'success')),
            Stat::make('Sisa batas untuk alokasi', Format::liter($p['sisa_alokasi'])),
        ];
    }
}
