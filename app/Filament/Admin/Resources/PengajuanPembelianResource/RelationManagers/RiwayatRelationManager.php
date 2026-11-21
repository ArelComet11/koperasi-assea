<?php

namespace App\Filament\Admin\Resources\PengajuanPembelianResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RiwayatRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayat';

    protected static ?string $title = 'Riwayat status';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i'),
                TextColumn::make('dari_status')->label('Dari')->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', (string) $state)) : '-'),
                TextColumn::make('ke_status')->label('Menjadi')->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state)))->badge(),
                TextColumn::make('user.name')->label('Oleh')->placeholder('-'),
                TextColumn::make('alasan')->placeholder('-')->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
