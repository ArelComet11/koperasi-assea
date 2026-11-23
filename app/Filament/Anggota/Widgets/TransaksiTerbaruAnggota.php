<?php

namespace App\Filament\Anggota\Widgets;

use App\Models\PengajuanPembelian;
use App\Support\Format;
use App\Support\Otorisasi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TransaksiTerbaruAnggota extends TableWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Otorisasi::user()?->hasRole('anggota');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pengajuan dan transaksi terbaru')
            ->query(fn () => PengajuanPembelian::query()->with('kapal')->latest('id')->limit(5))
            ->columns([
                TextColumn::make('nomor_pengajuan')->label('No.'),
                TextColumn::make('created_at')->label('Diajukan')->date('d/m/Y'),
                TextColumn::make('kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('liter_diminta')->label('Liter')->formatStateUsing(fn ($state) => Format::liter($state)),
                TextColumn::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
            ])
            ->paginated(false);
    }
}
