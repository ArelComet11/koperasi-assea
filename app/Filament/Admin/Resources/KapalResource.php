<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusAktif;
use App\Filament\Admin\Resources\KapalResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Support\Format;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KapalResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Kapal::class;
    protected static ?string $slug = 'kapal';
    protected static string $izinLihat = 'kapal.view';
    protected static ?string $izinBuat = 'kapal.manage';
    protected static ?string $izinUbah = 'kapal.manage';

    protected static ?string $modelLabel = 'Kapal';
    protected static ?string $pluralModelLabel = 'Kapal';
    protected static string|\UnitEnum|null $navigationGroup = 'Data Induk';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'nama_kapal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('anggota_id')->label('Anggota pemilik')->required()->searchable()
                ->options(fn (string $operation, ?Kapal $record) => Anggota::query()
                    ->when($operation === 'create', fn ($q) => $q->where('status', StatusAktif::Aktif->value))
                    ->when($operation !== 'create' && $record, fn ($q) => $q->where('status', StatusAktif::Aktif->value)->orWhereKey($record->anggota_id))
                    ->orderBy('nama_lengkap')->pluck('nama_lengkap', 'id'))
                ->disabledOn('edit')->helperText('Pemilik tidak dapat dipindahkan setelah kapal dibuat (menjaga keterkaitan riwayat transaksi).'),
            TextInput::make('kode_kapal')->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('nama_kapal')->required()->maxLength(150),
            TextInput::make('nama_pemilik')->maxLength(150)->helperText('Boleh berbeda dari nama anggota bila kapal terdaftar atas nama lain.'),
            TextInput::make('nomor_rekomendasi_tampil')->label('Nomor rekomendasi')
                ->formatStateUsing(fn (?Kapal $record) => $record?->suratRekomendasiTerbaru?->nomor_surat ?: $record?->no_registrasi ?: '-')
                ->disabled()->dehydrated(false)
                ->helperText('Diambil otomatis dari Surat Rekomendasi terbaru kapal ini.')
                ->visibleOn('edit'),
            TextInput::make('ukuran_gt')->label('Ukuran / kapasitas (GT)')->numeric()->minValue(0),
            TextInput::make('daya_pk')->label('Daya mesin (PK)')->numeric()->minValue(0),
            Select::make('status')->options(StatusAktif::opsi())->default('aktif')->required(),
            Textarea::make('keterangan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data kapal')->schema([
                TextEntry::make('kode_kapal'), TextEntry::make('nama_kapal'),
                TextEntry::make('anggota.nama_lengkap')->label('Anggota pemilik'),
                TextEntry::make('nama_pemilik')->placeholder('-'),
                TextEntry::make('nomor_rekomendasi')->label('Nomor rekomendasi')
                    ->state(fn (Kapal $record) => $record->suratRekomendasiTerbaru?->nomor_surat ?: $record->no_registrasi)
                    ->placeholder('-'),
                TextEntry::make('ukuran_gt')->label('Ukuran (GT)')->formatStateUsing(fn ($state) => Format::gt($state))->placeholder('-'),
                TextEntry::make('daya_pk')->label('Daya (PK)')->formatStateUsing(fn ($state) => Format::pk($state))->placeholder('-'),
                TextEntry::make('status')->badge(),
            ])->columns(3),
            Section::make('Riwayat pembelian BBM kapal ini')->schema([
                RepeatableEntry::make('pengajuan')->label('')->schema([
                    TextEntry::make('nomor_pengajuan')->label('No. pengajuan'),
                    TextEntry::make('created_at')->label('Tanggal')->date('d/m/Y'),
                    TextEntry::make('liter_diminta')->label('Liter')->formatStateUsing(fn ($state) => Format::liter($state)),
                    TextEntry::make('status_label')->label('Status')->badge()->color(fn ($record) => $record->statusTampil()[1]),
                ])->columns(4),
            ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['anggota', 'suratRekomendasiTerbaru']))
            ->columns([
                TextColumn::make('kode_kapal')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama_kapal')->label('Nama kapal')->searchable()->sortable(),
                TextColumn::make('anggota.nama_lengkap')->label('Anggota')->searchable()->sortable(),
                TextColumn::make('nomor_rekomendasi')->label('Nomor rekomendasi')
                    ->state(fn (Kapal $record) => $record->suratRekomendasiTerbaru?->nomor_surat ?: $record->no_registrasi)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $w) => $w
                        ->whereHas('suratRekomendasi', fn (Builder $q) => $q->where('nomor_surat', 'like', "%{$search}%"))
                        ->orWhere('no_registrasi', 'like', "%{$search}%")))
                    ->placeholder('-'),
                TextColumn::make('ukuran_gt')->label('GT')->formatStateUsing(fn ($state) => Format::gt($state))->placeholder('-')->sortable(),
                TextColumn::make('daya_pk')->label('PK')->formatStateUsing(fn ($state) => Format::pk($state))->placeholder('-')->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('kode_kapal')
            ->filters([
                SelectFilter::make('status')->options(StatusAktif::opsi()),
                SelectFilter::make('anggota_id')->label('Anggota')->relationship('anggota', 'nama_lengkap')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageKapal::route('/')];
    }
}
