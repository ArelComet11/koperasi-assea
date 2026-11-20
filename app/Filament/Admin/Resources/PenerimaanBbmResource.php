<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusPenerimaan;
use App\Filament\Admin\Resources\PenerimaanBbmResource\Pages;
use App\Filament\Concerns\Bisnis;
use App\Filament\Concerns\DenganIzin;
use App\Models\PenerimaanBbm;
use App\Services\PenerimaanService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PenerimaanBbmResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = PenerimaanBbm::class;
    protected static ?string $slug = 'penerimaan-bbm';
    protected static string $izinLihat = 'penerimaan.view';
    protected static ?string $izinBuat = 'penerimaan.manage';
    protected static ?string $izinUbah = null; // koreksi = batalkan + catat ulang (ledger append-only)

    protected static ?string $modelLabel = 'Penerimaan BBM';
    protected static ?string $pluralModelLabel = 'Penerimaan BBM';
    protected static string|\UnitEnum|null $navigationGroup = 'Operasional BBM';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'nomor_penerimaan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Penerimaan BBM')->description('Nomor penerimaan diterbitkan otomatis. Stok fisik dan total pasokan bulanan bertambah saat disimpan.')->schema([
                DatePicker::make('tanggal_penerimaan')->required()->default(today())->maxDate(today()),
                TextInput::make('nama_pemasok')->label('Pemasok / sumber pasokan')->required()->maxLength(150),
                TextInput::make('liter')->label('Jumlah diterima (liter)')->numeric()->required()->minValue(0.01),
                TextInput::make('harga_per_liter')->label('Harga per liter (opsional)')->numeric()->minValue(0)->prefix('Rp'),
                TextInput::make('nomor_dokumen')->maxLength(80),
                Textarea::make('keterangan')->columnSpanFull(),
                Toggle::make('override_pasokan')->label('Override batas pasokan bulanan')->live()
                    ->helperText('Hanya bila penerimaan melampaui batas bulanan. Memerlukan izin khusus dan akan tercatat di audit log.')
                    ->visible(fn () => auth()->user()->can('pasokan.override')),
                Textarea::make('alasan_override')->label('Alasan override')->visible(fn (Get $get) => (bool) $get('override_pasokan'))->required(fn (Get $get) => (bool) $get('override_pasokan'))->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_penerimaan')->label('No. penerimaan')->searchable()->sortable(),
                TextColumn::make('tanggal_penerimaan')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('nama_pemasok')->label('Pemasok')->searchable(),
                TextColumn::make('liter')->formatStateUsing(fn ($s) => Format::liter($s))->sortable(),
                TextColumn::make('total_nilai')->label('Nilai')->formatStateUsing(fn ($s) => $s === null ? '-' : Format::rupiah($s))->toggleable(),
                TextColumn::make('nomor_dokumen')->label('No. dokumen')->placeholder('-')->toggleable(),
                TextColumn::make('override_pasokan')->label('Override')->badge()->formatStateUsing(fn ($s) => $s ? 'Ya' : '-')->color(fn ($s) => $s ? 'warning' : 'gray'),
                TextColumn::make('pencatat.name')->label('Petugas')->toggleable(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('tanggal_penerimaan', 'desc')
            ->filters([
                SelectFilter::make('status')->options(StatusPenerimaan::opsi()),
                SelectFilter::make('periode')->label('Bulan')
                    ->options(fn () => collect(range(0, 11))->mapWithKeys(fn ($i) => [now()->startOfMonth()->subMonths($i)->toDateString() => now()->startOfMonth()->subMonths($i)->translatedFormat('F Y')])->all())
                    ->query(fn (Builder $query, array $data) => ! empty($data['value']) ? $query->whereDate('periode', $data['value']) : $query),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('batalkan')->label('Batalkan')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (PenerimaanBbm $r) => $r->status === StatusPenerimaan::Terkonfirmasi && auth()->user()->can('penerimaan.manage'))
                    ->modalHeading('Batalkan penerimaan')
                    ->modalDescription('Stok fisik dikurangi melalui entri pembalik di ledger. Gunakan untuk koreksi, lalu catat penerimaan yang benar.')
                    ->schema([Textarea::make('alasan')->required()->minLength(3)->maxLength(1000)])
                    ->action(fn (PenerimaanBbm $record, array $data) => Bisnis::jalankan(
                        fn () => app(PenerimaanService::class)->batalkan($record, auth()->user(), $data['alasan']), 'Penerimaan dibatalkan.')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPenerimaan::route('/'),
            'create' => Pages\CreatePenerimaan::route('/create'),
        ];
    }
}
