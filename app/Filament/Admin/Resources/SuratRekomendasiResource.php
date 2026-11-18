<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusAktif;
use App\Filament\Admin\Resources\SuratRekomendasiResource\Pages;
use App\Filament\Admin\Resources\SuratRekomendasiResource\RelationManagers;
use App\Filament\Concerns\DenganIzin;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\SuratRekomendasi;
use App\Services\KuotaService;
use App\Services\PengaturanService;
use App\Support\Format;
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('anggota_id')->label('Anggota pemilik')->required()->searchable()->live()
                ->options(fn () => Anggota::query()->orderBy('nama_lengkap')->pluck('nama_lengkap', 'id'))
                ->afterStateUpdated(fn (Set $set) => $set('kapal_id', null))
                ->disabledOn('edit'),
            Select::make('kapal_id')->label('Kapal')->required()->searchable()
                ->options(fn (Get $get) => Kapal::query()->where('anggota_id', $get('anggota_id') ?: 0)->orderBy('nama_kapal')->pluck('nama_kapal', 'id'))
                ->disabledOn('edit')
                ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                    if (! Kapal::query()->whereKey($value)->where('anggota_id', $get('anggota_id'))->exists()) {
                        $fail('Kapal tidak sesuai dengan anggota pemilik.');
                    }
                }),
            TextInput::make('nomor_surat')->required()->maxLength(80)->unique(ignoreRecord: true),
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
            ->columns([
                TextColumn::make('nomor_surat')->label('Nomor surat')->searchable()->sortable(),
                TextColumn::make('anggota.nama_lengkap')->label('Anggota')->searchable(),
                TextColumn::make('kapal.nama_kapal')->label('Kapal')->searchable(),
                TextColumn::make('berlaku_mulai')->label('Mulai')->date('d/m/Y')->sortable(),
                TextColumn::make('berlaku_sampai')->label('Sampai')->date('d/m/Y')->sortable(),
                TextColumn::make('status_tampil')->label('Status')->badge()->formatStateUsing(fn ($s) => ucfirst($s))
                    ->color(fn ($s) => match ($s) { 'aktif' => 'success', 'kedaluwarsa' => 'danger', default => 'gray' }),
                TextColumn::make('sisa_kuota')->label('Sisa kuota bulan ini')
                    ->state(fn (SuratRekomendasi $r) => Format::liter(app(KuotaService::class)->sisa($r))),
            ])
            ->defaultSort('berlaku_sampai', 'desc')
            ->filters([
                SelectFilter::make('status_tampil')->label('Status')->options(['aktif' => 'Aktif', 'kedaluwarsa' => 'Kedaluwarsa', 'nonaktif' => 'Nonaktif'])
                    ->query(function (Builder $q, array $data) {
                        match ($data['value'] ?? null) {
                            'aktif' => $q->where('status', 'aktif')->whereDate('berlaku_sampai', '>=', today()),
                            'kedaluwarsa' => $q->where('status', 'aktif')->whereDate('berlaku_sampai', '<', today()),
                            'nonaktif' => $q->where('status', 'nonaktif'),
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
