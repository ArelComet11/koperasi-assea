<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusPengajuan;
use App\Filament\Admin\Resources\PengajuanPembelianResource\RelationManagers;
use App\Filament\Admin\Resources\RiwayatTransaksiResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\PengajuanPembelian;
use App\Support\Dec;
use App\Support\Format;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RiwayatTransaksiResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = PengajuanPembelian::class;
    protected static ?string $slug = 'riwayat-transaksi';
    protected static string $izinLihat = 'pengajuan.view';
    protected static ?string $izinBuat = null;
    protected static ?string $izinUbah = null;

    protected static ?string $modelLabel = 'Riwayat transaksi';
    protected static ?string $pluralModelLabel = 'Riwayat transaksi';
    protected static ?string $navigationLabel = 'Riwayat Transaksi';
    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'nomor_pengajuan';

    /** Hanya transaksi yang sudah selesai (pembayaran lunas dan BBM telah disalurkan). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', StatusPengajuan::Selesai->value)
            ->with([
                'anggota',
                'kapal',
                'suratRekomendasi',
                'penyaluran',
                'tagihan.penyesuaian' => fn ($q) => $q->where('diterapkan', false),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PengajuanPembelianResource::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('selesai_pada')->label('Selesai')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('nomor_pengajuan')->label('No. transaksi')->searchable()->sortable(),
                TextColumn::make('anggota.nama_lengkap')->label('Anggota')->searchable(),
                TextColumn::make('kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('suratRekomendasi.nomor_surat')->label('Surat')->toggleable(),
                TextColumn::make('penyaluran.liter_aktual')->label('Liter disalurkan')->formatStateUsing(fn ($state) => Format::liter($state)),
                TextColumn::make('harga_per_liter')->label('Harga/L')->formatStateUsing(fn ($state) => Format::rupiah($state))->toggleable(),
                TextColumn::make('total_tagihan')->label('Total')->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextColumn::make('tujuan')->label('Tujuan')->placeholder('Melaut')->toggleable(),
                TextColumn::make('koreksi')->label('Koreksi')->badge()->color('warning')
                    ->state(function (PengajuanPembelian $record): ?string {
                        $selisih = $record->tagihan?->penyesuaian->where('diterapkan', false)->sum('selisih');

                        return Dec::lt($selisih, 0) ? 'Refund ' . Format::rupiah(Dec::sub('0', $selisih)) : null;
                    })
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
            ])
            ->defaultSort('selesai_pada', 'desc')
            ->filters([
                SelectFilter::make('anggota_id')->label('Anggota')->relationship('anggota', 'nama_lengkap')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\RiwayatRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRiwayatTransaksi::route('/'),
            'view' => Pages\ViewRiwayatTransaksi::route('/{record}'),
        ];
    }
}
