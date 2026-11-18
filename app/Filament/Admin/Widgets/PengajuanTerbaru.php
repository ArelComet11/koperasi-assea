<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PengajuanPembelian;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PengajuanTerbaru extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('pengajuan.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pengajuan terbaru')
            ->query(fn () => PengajuanPembelian::query()->with(['anggota', 'kapal'])->latest('id')->limit(6))
            ->columns([
                TextColumn::make('nomor_pengajuan')->label('No.'),
                TextColumn::make('anggota.nama_lengkap')->label('Anggota'),
                TextColumn::make('kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('liter_diminta')->label('Liter')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextColumn::make('created_at')->label('Diajukan')->since(),
                TextColumn::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
            ])
            ->paginated(false);
    }
}
