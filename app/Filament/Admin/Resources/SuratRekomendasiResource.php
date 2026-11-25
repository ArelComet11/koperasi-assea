<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusAktif;
use App\Filament\Admin\Resources\SuratRekomendasiResource\Pages;
use App\Filament\Admin\Resources\SuratRekomendasiResource\RelationManagers;
use App\Filament\Concerns\DenganIzin;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\SuratRekomendasi;
use App\Services\PengaturanService;
use App\Support\Format;
use App\Support\LencanaCache;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SuratRekomendasiResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = SuratRekomendasi::class;
    protected static ?string $slug = 'surat-rekomendasi';
    protected static string $izinLihat = 'surat.view';
    protected static ?string $izinBuat = 'surat.manage';
    protected static ?string $izinUbah = 'surat.manage';

    protected static ?string $modelLabel = 'Surat rekomendasi';
    protected static ?string $pluralModelLabel = 'Surat rekomendasi';
    protected static string|\UnitEnum|null $navigationGroup = 'Data Induk';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'nomor_surat';

    public static function getNavigationBadge(): ?string
    {
        $n = LencanaCache::semua()['surat'];

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return LencanaCache::semua()['surat_kedaluwarsa'] ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Surat rekomendasi kedaluwarsa atau segera berakhir (≤ 14 hari)';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('anggota_id')->label('Anggota pemilik')->required()->searchable()->live()
                ->options(fn (string $operation, ?SuratRekomendasi $record) => Anggota::query()
                    ->when($operation === 'create', fn (Builder $q) => $q->where('status', StatusAktif::Aktif->value))
                    ->when($operation !== 'create' && $record, fn (Builder $q) => $q->where('status', StatusAktif::Aktif->value)->orWhereKey($record->anggota_id))
                    ->orderBy('nama_lengkap')->pluck('nama_lengkap', 'id'))
                ->afterStateUpdated(fn (Set $set) => $set('kapal_id', null))
                ->disabledOn('edit'),
            Select::make('kapal_id')->label('Kapal')->required()->searchable()
                ->options(fn (Get $get, string $operation, ?SuratRekomendasi $record) => Kapal::query()
                    ->where('anggota_id', $get('anggota_id') ?: 0)
                    ->when($operation === 'create', fn (Builder $q) => $q->where('status', StatusAktif::Aktif->value))
                    ->when($operation !== 'create' && $record, fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('status', StatusAktif::Aktif->value)->orWhereKey($record->kapal_id)))
                    ->orderBy('nama_kapal')->pluck('nama_kapal', 'id'))
                ->disabledOn('edit')
                ->rule(fn (Get $get, string $operation) => function (string $attribute, $value, \Closure $fail) use ($get, $operation) {
                    $q = Kapal::query()->whereKey($value)->where('anggota_id', $get('anggota_id'));
                    if ($operation === 'create') {
                        $q->where('status', StatusAktif::Aktif->value);
                    }
                    if (! $q->exists()) {
                        $fail($operation === 'create'
                            ? 'Kapal tidak sesuai dengan anggota pemilik atau berstatus nonaktif.'
                            : 'Kapal tidak sesuai dengan anggota pemilik.');
                    }
                }),
            TextInput::make('nomor_surat')->label('No. Rekomendasi')->required()->maxLength(80)->unique(ignoreRecord: true),
            DatePicker::make('tanggal_surat'),
            DatePicker::make('berlaku_mulai')->required(),
            DatePicker::make('berlaku_sampai')->required()->afterOrEqual('berlaku_mulai'),
            TextInput::make('kuota_bulanan_liter')->label('Kuota per bulan (liter)')->numeric()->required()->minValue(1)
                ->default(fn () => (float) app(PengaturanService::class)->angka('kuota.surat_bulanan'))
                ->maxValue(fn () => (float) app(PengaturanService::class)->angka('kuota.surat_bulanan'))
                ->helperText('Tidak boleh melebihi batas kuota global per surat rekomendasi.'),
            Select::make('status')->options(StatusAktif::opsi())->default('aktif')->required(),
            FileUpload::make('file_path')->label('Dokumen surat (PDF/gambar)')->disk('local')->directory('surat-rekomendasi')
                ->visibility('private')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120)
                ->helperText('Maksimal 5 MB. Disimpan pada penyimpanan privat.')->columnSpanFull(),
            Textarea::make('keterangan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['anggota', 'kapal']))
            ->columns([
                TextColumn::make('nomor_surat')->label('No. Rekomendasi')->searchable()->sortable(),
                TextColumn::make('anggota.nama_lengkap')->label('Anggota')->searchable(),
                TextColumn::make('kapal.nama_kapal')->label('Kapal')->searchable(),
                TextColumn::make('berlaku_mulai')->label('Mulai')->date('d/m/Y')->sortable(),
                TextColumn::make('berlaku_sampai')->label('Sampai')->date('d/m/Y')->sortable(),
                TextColumn::make('status_tampil')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state)))
                    ->color(fn ($state) => match ($state) { 'aktif' => 'success', 'belum_berlaku' => 'info', 'kedaluwarsa' => 'danger', default => 'gray' }),
                TextColumn::make('kuota_bulanan_liter')->label('Vol Rekom')
                    ->formatStateUsing(fn ($state) => Format::liter($state))
                    ->sortable(),
            ])
            ->defaultSort('berlaku_sampai', 'desc')
            ->filters([
                SelectFilter::make('status_tampil')->label('Status')->options([
                    'aktif' => 'Aktif',
                    'belum_berlaku' => 'Belum berlaku',
                    'segera_berakhir' => 'Segera berakhir (≤ 14 hari)',
                    'kedaluwarsa' => 'Kedaluwarsa',
                    'nonaktif' => 'Nonaktif',
                ])
                    ->query(function (Builder $query, array $data) {
                        match ($data['value'] ?? null) {
                            'aktif' => $query->where('status', 'aktif')->whereDate('berlaku_mulai', '<=', today())->whereDate('berlaku_sampai', '>=', today()),
                            'belum_berlaku' => $query->where('status', 'aktif')->whereDate('berlaku_mulai', '>', today()),
                            'segera_berakhir' => $query->where('status', 'aktif')->whereDate('berlaku_sampai', '>=', today())->whereDate('berlaku_sampai', '<=', today()->addDays(14)),
                            'kedaluwarsa' => $query->where('status', 'aktif')->whereDate('berlaku_sampai', '<', today()),
                            'nonaktif' => $query->where('status', 'nonaktif'),
                            default => null,
                        };
                    }),
                SelectFilter::make('anggota_id')->label('Anggota')->relationship('anggota', 'nama_lengkap')->searchable()->preload(),
                SelectFilter::make('kapal_id')->label('Kapal')->relationship('kapal', 'nama_kapal')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('dokumen')->label('Dokumen')->icon('heroicon-o-paper-clip')
                    ->visible(fn (SuratRekomendasi $r) => (bool) $r->file_path)
                    ->url(fn (SuratRekomendasi $r) => route('dokumen.surat', $r))->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\KuotaBulananRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuratRekomendasi::route('/'),
            'create' => Pages\CreateSuratRekomendasi::route('/create'),
            'edit' => Pages\EditSuratRekomendasi::route('/{record}/edit'),
        ];
    }
}
