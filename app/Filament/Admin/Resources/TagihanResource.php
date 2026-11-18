<?php

namespace App\Filament\Admin\Resources;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTagihan;
use App\Filament\Admin\Resources\TagihanResource\Pages;
use App\Filament\Concerns\Bisnis;
use App\Filament\Concerns\DenganIzin;
use App\Models\Tagihan;
use App\Services\PembayaranService;
use App\Support\Format;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TagihanResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Tagihan::class;
    protected static ?string $slug = 'tagihan';
    protected static string $izinLihat = 'tagihan.view';
    protected static ?string $izinBuat = null;
    protected static ?string $izinUbah = null;

    protected static ?string $modelLabel = 'Tagihan';
    protected static ?string $pluralModelLabel = 'Tagihan';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_tagihan')->label('No. tagihan')->searchable()->sortable(),
                TextColumn::make('tanggal_tagihan')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('anggota.nama_lengkap')->label('Anggota')->searchable(),
                TextColumn::make('pengajuan.nomor_pengajuan')->label('No. pengajuan')->searchable(),
                TextColumn::make('liter')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextColumn::make('harga_per_liter')->label('Harga/L')->formatStateUsing(fn ($s) => Format::rupiah($s)),
                TextColumn::make('total')->formatStateUsing(fn ($s) => Format::rupiah($s))->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('tanggal_tagihan', 'desc')
            ->filters([SelectFilter::make('status')->options(StatusTagihan::opsi())->default('belum_dibayar')])
            ->recordActions([
                Action::make('bayar')->label('Catat pembayaran')->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (Tagihan $r) => $r->status === StatusTagihan::BelumDibayar && $r->pengajuan?->status === StatusPengajuan::Disetujui && auth()->user()->can('pembayaran.create'))
                    ->modalHeading('Catat pembayaran penuh')
                    ->modalDescription(fn (Tagihan $r) => 'Pembayaran harus penuh: ' . Format::rupiah($r->total) . ' (tidak ada cicilan). Jumlah tidak dapat diubah.')
                    ->schema([
                        Select::make('metode')->options(MetodePembayaran::opsi())->default('tunai')->required(),
                        TextInput::make('nomor_bukti')->label('Nomor bukti (opsional)')->maxLength(80),
                        DateTimePicker::make('tanggal')->label('Tanggal pembayaran')->required()->seconds(false)->default(now())->maxDate(now()->addMinute()),
                    ])
                    ->action(fn (Tagihan $record, array $data) => Bisnis::jalankan(
                        fn () => app(PembayaranService::class)->catat($record, auth()->user(), $data['metode'], $data['nomor_bukti'] ?? null, Carbon::parse($data['tanggal'])),
                        'Pembayaran lunas dicatat.')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTagihan::route('/')];
    }
}
