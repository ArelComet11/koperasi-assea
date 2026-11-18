<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\PasokanChart;
use App\Filament\Admin\Widgets\PasokanStats;
use Filament\Pages\Page;

class MonitoringPasokan extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Operasional BBM';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Batas Pasokan Bulanan';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Monitoring Batas Pasokan Bulanan';
    protected static ?string $slug = 'batas-pasokan';

    protected string $view = 'filament.pages.monitoring-pasokan';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('penerimaan.view');
    }

    protected function getHeaderWidgets(): array
    {
        return [PasokanStats::class, PasokanChart::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
