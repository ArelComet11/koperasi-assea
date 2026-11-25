<?php

namespace App\Filament\Anggota\Resources;

use App\Filament\Anggota\Resources\KapalSayaResource\Pages;
use App\Filament\Concerns\KhususAnggota;
use App\Models\Kapal;
use App\Support\Format;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KapalSayaResource extends Resource
{
    use KhususAnggota;

    protected static ?string $model = Kapal::class;
    protected static ?string $slug = 'kapal-saya';
    protected static ?string $modelLabel = 'Kapal';
    protected static ?string $pluralModelLabel = 'Kapal Saya';
    protected static ?string $navigationLabel = 'Kapal Saya';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('suratRekomendasiTerbaru'))
            ->columns([
                TextColumn::make('kode_kapal')->label('Kode'),
                TextColumn::make('nama_kapal')->label('Nama kapal')->searchable(),
                TextColumn::make('nomor_rekomendasi')->label('Nomor rekomendasi')
                    ->state(fn (Kapal $record) => $record->suratRekomendasiTerbaru?->nomor_surat ?: $record->no_registrasi)
                    ->placeholder('-'),
                TextColumn::make('ukuran_gt')->label('GT')->formatStateUsing(fn ($state) => Format::gt($state))->placeholder('-'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('nama_kapal');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListKapalSaya::route('/')];
    }
}
