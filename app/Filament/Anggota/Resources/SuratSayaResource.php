<?php

namespace App\Filament\Anggota\Resources;

use App\Filament\Anggota\Resources\SuratSayaResource\Pages;
use App\Filament\Concerns\KhususAnggota;
use App\Models\SuratRekomendasi;
use App\Services\KuotaService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuratSayaResource extends Resource
{
    use KhususAnggota;

    protected static ?string $model = SuratRekomendasi::class;
    protected static ?string $slug = 'surat-rekomendasi';
    protected static ?string $modelLabel = 'Surat rekomendasi';
    protected static ?string $pluralModelLabel = 'Surat Rekomendasi';
    protected static ?string $navigationLabel = 'Surat Rekomendasi';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_surat')->label('Nomor surat')->searchable(),
                TextColumn::make('kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('berlaku_mulai')->label('Berlaku dari')->date('d/m/Y'),
                TextColumn::make('berlaku_sampai')->label('Berlaku sampai')->date('d/m/Y'),
                TextColumn::make('status_tampil')->label('Status')->badge()->formatStateUsing(fn ($s) => ucfirst($s))
                    ->color(fn ($s) => match ($s) { 'aktif' => 'success', 'kedaluwarsa' => 'danger', default => 'gray' }),
                TextColumn::make('kuota')->label('Kuota/bulan')->state(fn (SuratRekomendasi $r) => Format::liter(app(KuotaService::class)->batas($r))),
                TextColumn::make('sisa')->label('Sisa bulan ini')->state(fn (SuratRekomendasi $r) => Format::liter(app(KuotaService::class)->sisa($r)))->weight('bold'),
            ])
            ->defaultSort('berlaku_sampai', 'desc')
            ->recordActions([
                Action::make('dokumen')->label('Dokumen')->icon('heroicon-o-paper-clip')
                    ->visible(fn (SuratRekomendasi $r) => (bool) $r->file_path)
                    ->url(fn (SuratRekomendasi $r) => route('dokumen.surat', $r))->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSuratSaya::route('/')];
    }
}
