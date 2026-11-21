<?php

namespace App\Filament\Admin\Resources\AnggotaResource\RelationManagers;

use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PengajuanRelationManager extends RelationManager
{
    protected static string $relationship = 'pengajuan';

    protected static ?string $title = 'Riwayat transaksi';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nomor_pengajuan')->label('No. pengajuan'),
            TextColumn::make('created_at')->label('Tanggal')->date('d/m/Y'),
            TextColumn::make('kapal.nama_kapal')->label('Kapal'),
            TextColumn::make('liter_diminta')->label('Liter')->formatStateUsing(fn ($state) => Format::liter($state)),
            TextColumn::make('total_tagihan')->label('Total')->formatStateUsing(fn ($state, $record) => Format::rupiah($state ?? $record->total_estimasi)),
            TextColumn::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
        ])->paginated([10, 25]);
    }
}
