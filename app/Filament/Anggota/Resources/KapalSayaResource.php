<?php

namespace App\Filament\Anggota\Resources;

use App\Filament\Anggota\Resources\KapalSayaResource\Pages;
use App\Filament\Concerns\KhususAnggota;
use App\Models\Kapal;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ->columns([
                TextColumn::make('kode_kapal')->label('Kode'),
                TextColumn::make('nama_kapal')->label('Nama kapal')->searchable(),
                TextColumn::make('no_registrasi')->label('No. registrasi')->placeholder('-'),
                TextColumn::make('jenis_kapal')->label('Jenis')->placeholder('-'),
                TextColumn::make('ukuran_gt')->label('Ukuran (GT)')->placeholder('-'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('nama_kapal');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListKapalSaya::route('/')];
    }
}
