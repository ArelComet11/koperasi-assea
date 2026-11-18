<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PenerimaanBbm;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PenerimaanTerbaru extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('penerimaan.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Penerimaan BBM terbaru')
            ->query(fn () => PenerimaanBbm::query()->latest('id')->limit(5))
            ->columns([
                TextColumn::make('nomor_penerimaan')->label('No.'),
                TextColumn::make('tanggal_penerimaan')->label('Tanggal')->date('d/m/Y'),
                TextColumn::make('nama_pemasok')->label('Pemasok'),
                TextColumn::make('liter')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextColumn::make('status')->badge(),
            ])
            ->paginated(false);
    }
}
