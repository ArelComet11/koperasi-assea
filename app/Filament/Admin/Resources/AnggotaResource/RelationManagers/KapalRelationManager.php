<?php

namespace App\Filament\Admin\Resources\AnggotaResource\RelationManagers;

use App\Models\Kapal;
use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('suratRekomendasiTerbaru'))
            ->columns([
                TextColumn::make('kode_kapal')->label('Kode'),
                TextColumn::make('nama_kapal')->label('Nama kapal'),
                TextColumn::make('nomor_rekomendasi')->label('Nomor rekomendasi')
                    ->state(fn (Kapal $record) => $record->suratRekomendasiTerbaru?->nomor_surat ?: $record->no_registrasi)
                    ->placeholder('-'),
                TextColumn::make('ukuran_gt')->label('GT')->formatStateUsing(fn ($state) => Format::gt($state))->placeholder('-'),
                TextColumn::make('status')->badge(),
            ])->paginated([10, 25]);
    }
}
