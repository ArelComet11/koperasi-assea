<?php

namespace App\Filament\Anggota\Resources;

use App\Filament\Anggota\Resources\RiwayatTransaksiResource\Pages;
use App\Filament\Concerns\KhususAnggota;
use App\Models\PengajuanPembelian;
use App\Support\Dec;
use App\Support\Format;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RiwayatTransaksiResource extends Resource
{
    use KhususAnggota;

    protected static ?string $model = PengajuanPembelian::class;
    protected static ?string $slug = 'riwayat-transaksi';
    protected static ?string $modelLabel = 'Transaksi';
    protected static ?string $pluralModelLabel = 'Riwayat Transaksi';
    protected static ?string $navigationLabel = 'Riwayat Transaksi';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';
    protected static ?int $navigationSort = 7;

    /** Transaksi = pengajuan yang selesai (lunas dan BBM sudah disalurkan). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'selesai')
            ->with(['tagihan.penyesuaian' => fn ($q) => $q->where('diterapkan', false)]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('selesai_pada')->label('Selesai')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('nomor_pengajuan')->label('No. transaksi')->searchable(),
                TextColumn::make('kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('penyaluran.liter_aktual')->label('Liter diterima')->formatStateUsing(fn ($state) => Format::liter($state)),
                TextColumn::make('harga_per_liter')->label('Harga/L')->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextColumn::make('total_tagihan')->label('Total')->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextColumn::make('tujuan')->label('Tujuan')->placeholder('Melaut')->toggleable(),
                TextColumn::make('koreksi')->label('Koreksi')->badge()->color('warning')
                    ->state(function (PengajuanPembelian $record): ?string {
                        $selisih = $record->tagihan?->penyesuaian->where('diterapkan', false)->sum('selisih');

                        return Dec::lt($selisih, 0) ? 'Refund ' . Format::rupiah(Dec::sub('0', $selisih)) : null;
                    })
                    ->placeholder('-'),
            ])
            ->defaultSort('selesai_pada', 'desc')
            ->recordActions([
                ViewAction::make()->url(fn (PengajuanPembelian $record) => PengajuanSayaResource::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRiwayatTransaksi::route('/')];
    }
}
