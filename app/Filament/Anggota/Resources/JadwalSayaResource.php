<?php

namespace App\Filament\Anggota\Resources;

use App\Filament\Anggota\Resources\JadwalSayaResource\Pages;
use App\Filament\Concerns\KhususAnggota;
use App\Models\JadwalPembelian;
use App\Support\Format;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JadwalSayaResource extends Resource
{
    use KhususAnggota;

    protected static ?string $model = JadwalPembelian::class;
    protected static ?string $slug = 'jadwal-pembelian';
    protected static ?string $modelLabel = 'Jadwal';
    protected static ?string $pluralModelLabel = 'Jadwal Pembelian';
    protected static ?string $navigationLabel = 'Jadwal Pembelian';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_waktu')->label('Waktu')->dateTime('l, d F Y H:i')->sortable(),
                TextColumn::make('nomor_jadwal')->label('No. jadwal'),
                TextColumn::make('pengajuan.nomor_pengajuan')->label('No. pengajuan'),
                TextColumn::make('pengajuan.kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('liter_disetujui')->label('Liter')->formatStateUsing(fn ($state) => Format::liter($state)),
                TextColumn::make('status')->badge(),
                TextColumn::make('keterangan')->placeholder('-')->wrap(),
            ])
            ->defaultSort('tanggal_waktu', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListJadwalSaya::route('/')];
    }
}
