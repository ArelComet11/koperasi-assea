<?php

namespace App\Filament\Admin\Resources\SuratRekomendasiResource\RelationManagers;

use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KuotaBulananRelationManager extends RelationManager
{
    protected static string $relationship = 'kuotaBulanan';

    protected static ?string $title = 'Riwayat penggunaan kuota';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('periode')->date('F Y')->sortable(),
                TextColumn::make('batas_liter')->label('Batas')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextColumn::make('liter_terpakai')->label('Terpakai (aktual)')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextColumn::make('liter_dialokasikan')->label('Alokasi aktif')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextColumn::make('sisa')->label('Sisa')->state(fn ($record) => Format::liter($record->sisa)),
            ])
            ->defaultSort('periode', 'desc');
    }
}
