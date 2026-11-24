<?php

namespace App\Filament\Admin\Resources;

use App\Enums\JenisMutasi;
use App\Filament\Admin\Resources\MutasiStokResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\MutasiStok;
use App\Support\Format;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MutasiStokResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = MutasiStok::class;
    protected static ?string $slug = 'mutasi-stok';
    protected static string $izinLihat = 'stok.view';
    protected static ?string $izinBuat = null;
    protected static ?string $izinUbah = null;

    protected static ?string $modelLabel = 'Mutasi stok';
    protected static ?string $pluralModelLabel = 'Riwayat pergerakan stok';
    protected static ?string $navigationLabel = 'Riwayat Pergerakan Stok';
    protected static string|\UnitEnum|null $navigationGroup = 'Operasional BBM';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';
    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        $delta = fn ($state) => ($state > 0 ? '+' : '') . Format::liter($state);

        return $table
            ->columns([
                TextColumn::make('tanggal')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('jenis')->badge(),
                TextColumn::make('delta_fisik')->label('Δ Fisik')->formatStateUsing($delta),
                TextColumn::make('saldo_fisik')->label('Stok fisik')->formatStateUsing(fn ($state) => Format::liter($state)),
                TextColumn::make('keterangan')->wrap()->searchable(),
                TextColumn::make('pembuat.name')->label('Oleh')->placeholder('-')->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([SelectFilter::make('jenis')->options(collect(JenisMutasi::cases())
                ->reject(fn (JenisMutasi $j) => in_array($j, [JenisMutasi::Alokasi, JenisMutasi::LepasAlokasi], true))
                ->mapWithKeys(fn (JenisMutasi $j) => [$j->value => $j->getLabel()])
                ->all())]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMutasiStok::route('/')];
    }
}
