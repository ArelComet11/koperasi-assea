<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Pembayaran;
use App\Support\Format;
use App\Support\Otorisasi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PembayaranTerbaru extends TableWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Otorisasi::bisa('tagihan.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Transaksi pembayaran terbaru')
            ->query(fn () => Pembayaran::query()->with('tagihan.anggota')->latest('id')->limit(6))
            ->columns([
                TextColumn::make('nomor_pembayaran')->label('No.'),
                TextColumn::make('tagihan.anggota.nama_lengkap')->label('Anggota'),
                TextColumn::make('jumlah')->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextColumn::make('metode')->badge(),
                TextColumn::make('tanggal_pembayaran')->label('Tanggal')->dateTime('d/m/Y H:i'),
                TextColumn::make('status')->badge(),
            ])
            ->paginated(false);
    }
}
