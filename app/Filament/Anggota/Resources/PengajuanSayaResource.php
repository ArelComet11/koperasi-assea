<?php

namespace App\Filament\Anggota\Resources;

use App\Enums\StatusPengajuan;
use App\Filament\Anggota\Resources\PengajuanSayaResource\Pages;
use App\Filament\Concerns\Bisnis;
use App\Models\Kapal;
use App\Models\PengajuanPembelian;
use App\Models\SuratRekomendasi;
use App\Services\HargaService;
use App\Services\KuotaService;
use App\Services\PengajuanService;
use App\Support\Dec;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PengajuanSayaResource extends Resource
{
    protected static ?string $model = PengajuanPembelian::class;
    protected static ?string $slug = 'pengajuan-saya';
    protected static ?string $modelLabel = 'Pengajuan';
    protected static ?string $pluralModelLabel = 'Pengajuan Saya';
    protected static ?string $navigationLabel = 'Pengajuan Saya';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 5;
    protected static ?string $recordTitleAttribute = 'nomor_pengajuan';

    public static function canViewAny(): bool { return (bool) auth()->user()?->hasRole('anggota'); }
    public static function canView(Model $record): bool { return static::canViewAny(); }
    public static function canCreate(): bool { return static::canViewAny() && (bool) auth()->user()->can('pengajuan.create'); }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Ajukan Pembelian BBM')->icon('heroicon-o-plus-circle')->sort(4)
                ->visible(fn () => static::canCreate())
                ->url(static::getUrl('create'))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.create')),
            ...parent::getNavigationItems(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $harga = fn () => app(HargaService::class)->aktif();

        return $schema->components([
            Section::make('Form pengajuan pembelian BBM')->description('Harga ditetapkan koperasi dan tidak dapat diubah. Jumlah dibatasi sisa kuota surat rekomendasi, stok, dan batas pasokan bulanan.')->schema([
                TextInput::make('nama')->label('Nama anggota')->default(fn () => auth()->user()->anggota?->nama_lengkap)->disabled()->dehydrated(false),
                Select::make('kapal_id')->label('Kapal')->required()->live()
                    ->options(fn () => Kapal::query()->where('status', 'aktif')->orderBy('nama_kapal')->pluck('nama_kapal', 'id'))
                    ->afterStateUpdated(fn (Set $set) => $set('surat_rekomendasi_id', null)),
                Select::make('surat_rekomendasi_id')->label('Surat rekomendasi')->required()->live()
                    ->options(fn (Get $get) => SuratRekomendasi::query()->where('kapal_id', $get('kapal_id') ?: 0)->where('status', 'aktif')
                        ->whereDate('berlaku_mulai', '<=', today())->whereDate('berlaku_sampai', '>=', today())->pluck('nomor_surat', 'id'))
                    ->helperText(function (Get $get) {
                        $s = $get('surat_rekomendasi_id') ? SuratRekomendasi::query()->find($get('surat_rekomendasi_id')) : null;

                        return $s ? 'Sisa kuota bulan ini: ' . Format::liter(app(KuotaService::class)->sisa($s)) : 'Pilih kapal, lalu surat rekomendasi yang berlaku.';
                    }),
                TextInput::make('liter_diminta')->label('Jumlah BBM yang diminta')->numeric()->required()->minValue(1)->suffix('liter')->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set) use ($harga) {
                        $l = $get('liter_diminta');
                        $set('total_tampil', Format::rupiah(is_numeric($l) ? Dec::mul($l, $harga()) : 0));
                    }),
                TextInput::make('harga_tampil')->label('Harga per liter')->default(fn () => Format::rupiah($harga()))->disabled()->dehydrated(false),
                TextInput::make('total_tampil')->label('Total estimasi pembayaran')->default('Rp0')->disabled()->dehydrated(false),
                DatePicker::make('tanggal_rencana')->label('Tanggal rencana pembelian')->required()->minDate(today())->default(today()),
                Textarea::make('catatan')->maxLength(500)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $rp = fn ($s) => $s === null ? '-' : Format::rupiah($s);

        return $schema->components([
            Section::make('Pengajuan')->schema([
                TextEntry::make('nomor_pengajuan')->label('Nomor'),
                TextEntry::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
                TextEntry::make('created_at')->label('Diajukan')->dateTime('d/m/Y H:i'),
                TextEntry::make('kapal.nama_kapal')->label('Kapal'),
                TextEntry::make('suratRekomendasi.nomor_surat')->label('Surat rekomendasi'),
                TextEntry::make('tanggal_rencana')->label('Rencana pembelian')->date('d/m/Y')->placeholder('-'),
                TextEntry::make('liter_diminta')->label('Jumlah diminta')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextEntry::make('harga')->label('Harga per liter')->state(fn ($r) => $rp($r->harga_per_liter ?? $r->harga_estimasi_per_liter)),
                TextEntry::make('total')->label('Total')->state(fn ($r) => $rp($r->total_tagihan ?? $r->total_estimasi)),
                TextEntry::make('status_pembayaran')->label('Pembayaran')->badge(),
                TextEntry::make('status_penyaluran')->label('Penyaluran')->badge(),
                TextEntry::make('catatan')->placeholder('-'),
                TextEntry::make('alasan_penolakan')->label('Alasan penolakan')->visible(fn ($record) => $record?->alasan_penolakan !== null)->columnSpanFull(),
                TextEntry::make('alasan_pembatalan')->label('Alasan pembatalan')->visible(fn ($record) => $record?->alasan_pembatalan !== null)->columnSpanFull(),
            ])->columns(3),
            Section::make('Riwayat status')->schema([
                RepeatableEntry::make('riwayat')->label('')->schema([
                    TextEntry::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i'),
                    TextEntry::make('ke_status')->label('Status')->formatStateUsing(fn ($s) => ucfirst(str_replace('_', ' ', $s)))->badge(),
                    TextEntry::make('alasan')->placeholder('-'),
                ])->columns(3),
            ])->collapsible(),
        ]);
    }

    public static function batalkanAksi(): Action
    {
        return Action::make('batalkan')->label('Batalkan pengajuan')->icon('heroicon-o-x-circle')->color('danger')
            ->visible(fn (PengajuanPembelian $r) => $r->status === StatusPengajuan::MenungguPersetujuan)
            ->modalDescription('Pengajuan hanya dapat dibatalkan selama belum diproses admin.')
            ->schema([Textarea::make('alasan')->label('Alasan (opsional)')->maxLength(500)])
            ->action(function (PengajuanPembelian $record, array $data) {
                Bisnis::jalankan(fn () => app(PengajuanService::class)->batalkanOlehAnggota($record, auth()->user()->anggota, $data['alasan'] ?? null), 'Pengajuan dibatalkan.');
                $record->refresh();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_pengajuan')->label('No. pengajuan')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('liter_diminta')->label('Liter')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextColumn::make('total')->label('Total')->state(fn ($r) => Format::rupiah($r->total_tagihan ?? $r->total_estimasi)),
                TextColumn::make('tanggal_rencana')->label('Rencana')->date('d/m/Y'),
                TextColumn::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(StatusPengajuan::opsi())])
            ->recordActions([ViewAction::make(), static::batalkanAksi()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPengajuanSaya::route('/'),
            'create' => Pages\CreatePengajuanSaya::route('/create'),
            'view' => Pages\ViewPengajuanSaya::route('/{record}'),
        ];
    }
}
