<?php

namespace App\Filament\Admin\Resources\AnggotaResource\RelationManagers;

use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KapalRelationManager extends RelationManager
{
    protected static string $relationship = 'kapal';

    protected static ?string $title = 'Kapal';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('kode_kapal')->label('Kode'),
            TextColumn::make('nama_kapal')->label('Nama kapal'),
            TextColumn::make('no_registrasi')->label('No. registrasi')->placeholder('-'),
            TextColumn::make('jenis_kapal')->label('Jenis')->placeholder('-'),
            TextColumn::make('status')->badge(),
        ])->paginated([10, 25]);
    }
}
