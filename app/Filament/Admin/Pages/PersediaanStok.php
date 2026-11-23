<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\MutasiStokResource;
use App\Filament\Admin\Widgets\StokStats;
use App\Filament\Concerns\Bisnis;
use App\Models\MutasiStok;
use App\Enums\JenisMutasi;
use App\Services\StokService;
use App\Support\Otorisasi;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saldoAwal')->label('Isi saldo awal')->icon('heroicon-o-plus-circle')
                ->visible(fn () => Otorisasi::bisa('stok.saldo_awal') && ! MutasiStok::query()->where('jenis', JenisMutasi::SaldoAwal->value)->exists())
                ->modalHeading('Saldo awal stok')
                ->modalDescription('Dicatat satu kali saat sistem mulai dipakai dan tidak dapat diubah. Isi dengan stok fisik BBM saat ini.')
                ->schema([TextInput::make('liter')->label('Stok fisik awal (liter)')->numeric()->required()->minValue(0.01)])
                ->action(fn (array $data) => Bisnis::jalankan(fn () => app(StokService::class)->saldoAwal($data['liter'], Otorisasi::userWajib()), 'Saldo awal dicatat.')),
        ];
    }

    protected function getViewData(): array
    {
        $v = app(StokService::class)->verifikasi();

        return ['konsisten' => $v['konsisten'], 'urlLedger' => MutasiStokResource::getUrl()];
    }
}
