<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\MutasiStokResource;
use App\Filament\Admin\Widgets\StokStats;
use App\Services\StokService;
use App\Support\Otorisasi;
use Filament\Pages\Page;

class PersediaanStok extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Operasional BBM';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Persediaan Stok';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Persediaan Stok BBM';
    protected static ?string $slug = 'persediaan-stok';

    protected string $view = 'filament.pages.persediaan-stok';

    public static function canAccess(): bool
    {
        return Otorisasi::bisa('stok.view');
    }

    protected function getHeaderWidgets(): array
    {
        return [StokStats::class];
    }

    protected function getViewData(): array
    {
        $v = app(StokService::class)->verifikasi();

        return ['konsisten' => $v['konsisten'], 'urlLedger' => MutasiStokResource::getUrl()];
    }
}
