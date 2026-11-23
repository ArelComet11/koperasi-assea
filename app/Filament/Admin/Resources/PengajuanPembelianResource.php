<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenyaluran;
use App\Filament\Admin\Resources\PengajuanPembelianResource\Aksi;
use App\Filament\Admin\Resources\PengajuanPembelianResource\Pages;
use App\Filament\Admin\Resources\PengajuanPembelianResource\RelationManagers;
use App\Filament\Concerns\DenganIzin;
use App\Models\PengajuanPembelian;
use App\Support\Dec;
use App\Support\Format;
use App\Support\LencanaCache;
use App\Support\Otorisasi;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PengajuanPembelianResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = PengajuanPembelian::class;
    protected static ?string $slug = 'pengajuan-pembelian';
    protected static string $izinLihat = 'pengajuan.view';
    protected static ?string $izinBuat = null;
    protected static ?string $izinUbah = null;

    protected static ?string $modelLabel = 'Pengajuan pembelian';
    protected static ?string $pluralModelLabel = 'Pengajuan pembelian';
    protected static ?string $navigationLabel = 'Pengajuan Pembelian BBM';
    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'nomor_pengajuan';

    public static function getNavigationBadge(): ?string
    {
        if (! Otorisasi::bisa('pengajuan.approve')) {
            return null;
        }

        $n = LencanaCache::semua()['pengajuan'];

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pengajuan menunggu persetujuan';
    }

    public static function infolist(Schema $schema): Schema
    {
        $liter = fn ($state) => Format::liter($state);
        $rp = fn ($state) => $state === null ? '-' : Format::rupiah($state);

        return $schema->components([
            Section::make('Pengajuan')->schema([
                TextEntry::make('nomor_pengajuan')->label('Nomor'),
                TextEntry::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
                TextEntry::make('created_at')->label('Diajukan')->dateTime('d/m/Y H:i'),
                TextEntry::make('anggota.nama_lengkap')->label('Anggota'),
                TextEntry::make('kapal.nama_kapal')->label('Kapal'),
                TextEntry::make('suratRekomendasi.nomor_surat')->label('Surat rekomendasi'),
                TextEntry::make('tanggal_rencana')->label('Rencana pembelian')->date('d/m/Y')->placeholder('-'),
                TextEntry::make('catatan')->placeholder('-')->columnSpan(2),
            ])->columns(3),
            Section::make('Jumlah dan harga')->schema([
                TextEntry::make('liter_diminta')->label('Diminta')->formatStateUsing($liter),
                TextEntry::make('alokasi.liter_disetujui')->label('Disetujui')->formatStateUsing($liter)->placeholder('-'),
                TextEntry::make('penyaluran.liter_aktual')->label('Disalurkan (aktual)')->formatStateUsing($liter)->placeholder('-'),
                TextEntry::make('harga_estimasi_per_liter')->label('Harga estimasi/L')->formatStateUsing($rp),
                TextEntry::make('harga_per_liter')->label('Harga final/L (snapshot)')->formatStateUsing($rp),
                TextEntry::make('total_tagihan')->label('Total tagihan')->formatStateUsing($rp),
                TextEntry::make('periode_kuota')->label('Periode kuota')->date('F Y')->placeholder('-'),
            ])->columns(3),
            Section::make('Pembayaran dan penyaluran')->schema([
                TextEntry::make('status_pembayaran')->badge(),
                TextEntry::make('status_penyaluran')->badge(),
                TextEntry::make('tagihan.nomor_tagihan')->label('Nomor tagihan')->placeholder('-'),
                TextEntry::make('disetujui_pada')->dateTime('d/m/Y H:i')->placeholder('-'),
                TextEntry::make('selesai_pada')->dateTime('d/m/Y H:i')->placeholder('-'),
                TextEntry::make('alasan_penolakan')->placeholder('-')->visible(fn ($record) => $record?->alasan_penolakan !== null),
                TextEntry::make('alasan_pembatalan')->placeholder('-')->visible(fn ($record) => $record?->alasan_pembatalan !== null),
                TextEntry::make('refund_tertunda')->label('Koreksi kelebihan bayar')->badge()->color('danger')
                    ->state(function (?PengajuanPembelian $record): ?string {
                        $selisih = $record?->tagihan?->penyesuaian()->where('diterapkan', false)->sum('selisih');

                        return Dec::lt($selisih, 0) ? 'Perlu pengembalian ' . Format::rupiah(Dec::sub('0', $selisih)) : null;
                    })
                    ->visible(fn (?PengajuanPembelian $record) => (bool) $record?->tagihan?->penyesuaian()->where('diterapkan', false)->exists()),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_pengajuan')->label('No. pengajuan')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('anggota.nama_lengkap')->label('Anggota')->searchable(),
                TextColumn::make('kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('suratRekomendasi.nomor_surat')->label('Surat')->toggleable(),
                TextColumn::make('liter_diminta')->label('Liter')->formatStateUsing(fn ($state) => Format::liter($state))->sortable(),
                TextColumn::make('harga')->label('Harga/L')->state(fn (PengajuanPembelian $record) => Format::rupiah($record->harga_per_liter ?? $record->harga_estimasi_per_liter))->toggleable(),
                TextColumn::make('total')->label('Total')->state(fn (PengajuanPembelian $record) => Format::rupiah($record->total_tagihan ?? $record->total_estimasi)),
                TextColumn::make('tanggal_rencana')->label('Rencana')->date('d/m/Y')->sortable(),
                TextColumn::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(StatusPengajuan::opsi()),
                SelectFilter::make('status_pembayaran')->label('Pembayaran')->options(StatusPembayaran::opsi()),
                SelectFilter::make('status_penyaluran')->label('Penyaluran')->options(StatusPenyaluran::opsi()),
                SelectFilter::make('anggota_id')->label('Anggota')->relationship('anggota', 'nama_lengkap')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make(), ActionGroup::make(Aksi::semua())]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\RiwayatRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPengajuan::route('/'),
            'view' => Pages\ViewPengajuan::route('/{record}'),
        ];
    }
}
