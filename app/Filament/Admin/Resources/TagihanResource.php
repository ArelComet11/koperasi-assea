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
use App\Support\Dec;
use App\Support\Format;
use App\Support\LencanaCache;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

    public static function getNavigationBadge(): ?string
    {
        $n = LencanaCache::semua()['tagihan'];

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Tagihan belum dibayar / koreksi selisih refund tertunda';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['penyesuaian' => fn ($q) => $q->where('diterapkan', false)]))
            ->columns([
                TextColumn::make('nomor_tagihan')->label('No. tagihan')->searchable()->sortable(),
                TextColumn::make('tanggal_tagihan')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('anggota.nama_lengkap')->label('Anggota')->searchable(),
                TextColumn::make('pengajuan.nomor_pengajuan')->label('No. pengajuan')->searchable(),
                TextColumn::make('liter')->formatStateUsing(fn ($state) => Format::liter($state)),
                TextColumn::make('harga_per_liter')->label('Harga/L')->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextColumn::make('total')->formatStateUsing(fn ($state) => Format::rupiah($state))->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('koreksi_selisih')->label('Koreksi')->badge()->color('danger')
                    ->state(function (Tagihan $record): ?string {
                        $tertunda = $record->penyesuaian->where('diterapkan', false)->sum('selisih');

                        return Dec::lt($tertunda, 0) ? 'Refund ' . Format::rupiah(Dec::sub('0', $tertunda)) : null;
                    })
                    ->placeholder('-'),
            ])
            ->defaultSort('tanggal_tagihan', 'desc')
            ->filters([
                SelectFilter::make('status')->options(StatusTagihan::opsi()),
                Filter::make('perlu_koreksi')->label('Perlu koreksi / pengembalian dana')
                    ->query(fn (Builder $query) => $query->whereHas('penyesuaian', fn ($q) => $q->where('diterapkan', false))),
            ])
            ->recordActions([
                Action::make('bayar')->label('Catat pembayaran')->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (Tagihan $r) => $r->status === StatusTagihan::BelumDibayar && $r->pengajuan?->status === StatusPengajuan::Disetujui && Otorisasi::bisa('pembayaran.create'))
                    ->modalHeading('Catat pembayaran penuh')
                    ->modalDescription(fn (Tagihan $r) => 'Pembayaran harus penuh: ' . Format::rupiah($r->total) . ' (tidak ada cicilan). Jumlah tidak dapat diubah.')
                    ->schema([
                        Select::make('metode')->options(MetodePembayaran::opsi())->default('tunai')->required(),
                        TextInput::make('nomor_bukti')->label('Nomor bukti (opsional)')->maxLength(80),
                        DateTimePicker::make('tanggal')->label('Tanggal pembayaran')->required()->seconds(false)->default(fn () => now())->maxDate(fn () => now()->addMinutes(5)),
                    ])
                    ->action(fn (Tagihan $record, array $data) => Bisnis::jalankan(
                        fn () => app(PembayaranService::class)->catat($record, Otorisasi::userWajib(), $data['metode'], $data['nomor_bukti'] ?? null, Carbon::parse($data['tanggal'])),
                        'Pembayaran lunas dicatat.')),
                Action::make('selesaikanKoreksi')->label('Selesaikan refund')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(fn (Tagihan $r) => $r->penyesuaian->where('diterapkan', false)->isNotEmpty() && Otorisasi::bisa('pembayaran.create'))
                    ->modalHeading('Tandai pengembalian selisih selesai')
                    ->modalDescription(function (Tagihan $r): string {
                        $selisih = Dec::sub('0', $r->penyesuaian->where('diterapkan', false)->sum('selisih'));

                        return 'Konfirmasi bahwa selisih kelebihan pembayaran sebesar ' . Format::rupiah($selisih) . ' telah dikembalikan / dikoreksi kepada anggota.';
                    })
                    ->schema([Textarea::make('catatan')->label('Catatan penyelesaian')->required()->minLength(3)->maxLength(500)])
                    ->action(fn (Tagihan $record, array $data) => Bisnis::jalankan(
                        fn () => app(PembayaranService::class)->selesaikanPenyesuaian($record, Otorisasi::userWajib(), $data['catatan']),
                        'Koreksi / pengembalian selisih ditandai selesai.')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTagihan::route('/')];
    }
}
