<?php

namespace App\Filament\Admin\Resources\AnggotaResource\RelationManagers;

use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuratRelationManager extends RelationManager
{
    protected static string $relationship = 'suratRekomendasi';

    protected static ?string $title = 'Surat rekomendasi';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nomor_surat')->label('No. Rekomendasi'),
            TextColumn::make('kapal.nama_kapal')->label('Kapal'),
            TextColumn::make('berlaku_mulai')->date('d/m/Y')->label('Mulai'),
            TextColumn::make('berlaku_sampai')->date('d/m/Y')->label('Sampai'),
            TextColumn::make('status_tampil')->label('Status')->badge()
                ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state)))
                ->color(fn ($state) => match ($state) { 'aktif' => 'success', 'belum_berlaku' => 'info', 'kedaluwarsa' => 'danger', default => 'gray' }),
        ])->paginated([10, 25]);
    }
}
