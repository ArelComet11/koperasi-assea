<?php

namespace App\Filament\Anggota\Resources;

use App\Filament\Anggota\Resources\RiwayatPembayaranResource\Pages;
use App\Filament\Concerns\KhususAnggota;
use App\Models\Pembayaran;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RiwayatPembayaranResource extends Resource
{
    use KhususAnggota;

    protected static ?string $model = Pembayaran::class;
    protected static ?string $slug = 'riwayat-pembayaran';
    protected static ?string $modelLabel = 'Pembayaran';
    protected static ?string $pluralModelLabel = 'Riwayat Pembayaran';
    protected static ?string $navigationLabel = 'Riwayat Pembayaran';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?int $navigationSort = 8;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_pembayaran')->label('Tanggal')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('nomor_pembayaran')->label('No. pembayaran')->searchable(),
                TextColumn::make('tagihan.pengajuan.nomor_pengajuan')->label('No. pengajuan'),
                TextColumn::make('jumlah')->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextColumn::make('metode')->badge(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('tanggal_pembayaran', 'desc')
            ->recordActions([
                Action::make('cetak')->label('Bukti')->icon('heroicon-o-printer')
                    ->url(fn (Pembayaran $r) => route('cetak.pembayaran', $r))->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRiwayatPembayaran::route('/')];
    }
}
