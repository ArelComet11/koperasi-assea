<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AktivitasAdmin;
use App\Support\Otorisasi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AktivitasTerbaru extends TableWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Otorisasi::bisa('audit.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Aktivitas admin terbaru')
            ->query(fn () => AktivitasAdmin::query()->latest('id')->limit(8))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i'),
                TextColumn::make('nama_pengguna')->label('Pengguna')->placeholder('-'),
                TextColumn::make('jenis')->badge(),
                TextColumn::make('keterangan')->limit(50)->placeholder('-'),
            ])
            ->paginated(false);
    }
}
