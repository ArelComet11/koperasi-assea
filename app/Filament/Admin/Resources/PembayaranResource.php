<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusPembayaranCatatan;
use App\Filament\Admin\Resources\PembayaranResource\Pages;
use App\Filament\Concerns\Bisnis;
use App\Filament\Concerns\DenganIzin;
use App\Models\Pembayaran;
use App\Services\PembayaranService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PembayaranResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Pembayaran::class;
    protected static ?string $slug = 'pembayaran';
    protected static string $izinLihat = 'tagihan.view';
    protected static ?string $izinBuat = null;
    protected static ?string $izinUbah = null;

    protected static ?string $modelLabel = 'Pembayaran';
    protected static ?string $pluralModelLabel = 'Riwayat pembayaran';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_pembayaran')->label('No. pembayaran')->searchable()->sortable(),
                TextColumn::make('tanggal_pembayaran')->label('Tanggal')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('tagihan.anggota.nama_lengkap')->label('Anggota')->searchable(),
                TextColumn::make('tagihan.nomor_tagihan')->label('No. tagihan')->searchable(),
                TextColumn::make('jumlah')->formatStateUsing(fn ($s) => Format::rupiah($s))->sortable(),
                TextColumn::make('metode')->badge(),
                TextColumn::make('nomor_bukti')->label('No. bukti')->placeholder('-')->toggleable(),
                TextColumn::make('penerima.name')->label('Penerima')->toggleable(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('tanggal_pembayaran', 'desc')
            ->filters([SelectFilter::make('status')->options(StatusPembayaranCatatan::opsi())])
            ->recordActions([
                Action::make('cetak')->label('Cetak bukti')->icon('heroicon-o-printer')
                    ->url(fn (Pembayaran $r) => route('cetak.pembayaran', $r))->openUrlInNewTab(),
                Action::make('batalkan')->label('Batalkan')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Pembayaran $r) => $r->status === StatusPembayaranCatatan::Sah && auth()->user()->can('pembayaran.cancel'))
                    ->modalHeading('Batalkan pembayaran')
                    ->modalDescription('Tagihan kembali berstatus belum dibayar. Tidak dapat dilakukan bila BBM sudah disalurkan.')
                    ->schema([Textarea::make('alasan')->required()->minLength(3)->maxLength(1000)])
                    ->action(fn (Pembayaran $record, array $data) => Bisnis::jalankan(
                        fn () => app(PembayaranService::class)->batalkan($record, auth()->user(), $data['alasan']), 'Pembayaran dibatalkan.')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPembayaran::route('/')];
    }
}
