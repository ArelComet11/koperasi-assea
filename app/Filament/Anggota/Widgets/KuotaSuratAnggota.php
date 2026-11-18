<?php

namespace App\Filament\Anggota\Widgets;

use App\Models\SuratRekomendasi;
use App\Services\KuotaService;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class KuotaSuratAnggota extends TableWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole('anggota');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sisa kuota BBM bulan ini per surat rekomendasi')
            ->query(fn () => SuratRekomendasi::query()->with('kapal')->where('status', 'aktif')->whereDate('berlaku_sampai', '>=', today())->orderBy('nomor_surat'))
            ->columns([
                TextColumn::make('nomor_surat')->label('Surat rekomendasi'),
                TextColumn::make('kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('berlaku_sampai')->label('Berlaku sampai')->date('d/m/Y'),
                TextColumn::make('kuota')->label('Kuota/bulan')->state(fn ($r) => Format::liter(app(KuotaService::class)->batas($r))),
                TextColumn::make('sisa')->label('Sisa bulan ini')->state(fn ($r) => Format::liter(app(KuotaService::class)->sisa($r)))->weight('bold')->color('success'),
            ])
            ->paginated(false);
    }
}
