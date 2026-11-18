<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusAktif;
use App\Filament\Admin\Resources\AnggotaResource\Pages;
use App\Filament\Admin\Resources\AnggotaResource\RelationManagers;
use App\Filament\Concerns\Bisnis;
use App\Filament\Concerns\DenganIzin;
use App\Models\Anggota;
use App\Services\AnggotaService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AnggotaResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Anggota::class;
    protected static ?string $slug = 'anggota';
    protected static string $izinLihat = 'anggota.view';
    protected static ?string $izinBuat = 'anggota.create';
    protected static ?string $izinUbah = 'anggota.update';

    protected static ?string $modelLabel = 'Anggota';
    protected static ?string $pluralModelLabel = 'Anggota';
    protected static string|\UnitEnum|null $navigationGroup = 'Data Induk';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'nama_lengkap';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun login')->description('Akun anggota hanya dibuat oleh admin. Username tidak dapat diubah setelah dibuat.')->schema([
                TextInput::make('username')->required()->maxLength(50)->alphaDash()->unique('users', 'username'),
                TextInput::make('password')->label('Kata sandi')->password()->revealable()->required()->minLength(8),
            ])->columns(2)->visibleOn('create'),
            Section::make('Data anggota')->schema([
                TextInput::make('username_tampil')->label('Username')->disabled()->dehydrated(false)->visibleOn('edit'),
                TextInput::make('kode_anggota')->required()->maxLength(30)->unique(ignoreRecord: true)
                    ->default(fn () => 'ANG-' . str_pad((string) ((Anggota::withTrashed()->max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT)),
                TextInput::make('nama_lengkap')->required()->maxLength(150),
                TextInput::make('telepon')->tel()->maxLength(30),
                TextInput::make('email')->email()->maxLength(150),
                DatePicker::make('tanggal_bergabung')->default(today()),
                Textarea::make('alamat')->columnSpanFull(),
                Textarea::make('keterangan')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil anggota')->schema([
                TextEntry::make('kode_anggota')->label('Kode'),
                TextEntry::make('nama_lengkap')->label('Nama'),
                TextEntry::make('user.username')->label('Username'),
                TextEntry::make('telepon')->placeholder('-'),
                TextEntry::make('email')->placeholder('-'),
                TextEntry::make('status')->badge(),
                TextEntry::make('tanggal_bergabung')->date('d F Y')->placeholder('-'),
                TextEntry::make('alamat')->placeholder('-')->columnSpan(2),
                TextEntry::make('keterangan')->placeholder('-')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_anggota')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama_lengkap')->label('Nama')->searchable()->sortable(),
                TextColumn::make('user.username')->label('Username')->searchable(),
                TextColumn::make('telepon')->searchable(),
                TextColumn::make('kapal_count')->counts('kapal')->label('Kapal')->alignCenter(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('tanggal_bergabung')->label('Bergabung')->date('d/m/Y')->sortable()->toggleable(),
            ])
            ->defaultSort('kode_anggota')
            ->filters([SelectFilter::make('status')->options(StatusAktif::opsi())])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    Action::make('resetPassword')->label('Reset kata sandi')->icon('heroicon-o-key')
                        ->visible(fn () => auth()->user()->can('anggota.reset_password'))
                        ->schema([TextInput::make('password')->label('Kata sandi baru')->password()->revealable()->required()->minLength(8)])
                        ->action(fn (Anggota $record, array $data) => Bisnis::jalankan(
                            fn () => app(AnggotaService::class)->resetPassword($record, $data['password'], auth()->user()), 'Kata sandi berhasil diatur ulang.')),
                    Action::make('ubahStatus')
                        ->label(fn (Anggota $r) => $r->isAktif() ? 'Nonaktifkan akun' : 'Aktifkan akun')
                        ->icon(fn (Anggota $r) => $r->isAktif() ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                        ->color(fn (Anggota $r) => $r->isAktif() ? 'danger' : 'success')
                        ->visible(fn () => auth()->user()->can('anggota.nonaktif'))
                        ->requiresConfirmation()
                        ->modalDescription('Anggota nonaktif tidak dapat login. Seluruh riwayat transaksi tetap tersimpan.')
                        ->action(fn (Anggota $record) => Bisnis::jalankan(
                            fn () => app(AnggotaService::class)->setStatus($record, $record->isAktif() ? StatusAktif::Nonaktif : StatusAktif::Aktif, auth()->user()), 'Status anggota diperbarui.')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\KapalRelationManager::class,
            RelationManagers\SuratRelationManager::class,
            RelationManagers\PengajuanRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnggota::route('/'),
            'create' => Pages\CreateAnggota::route('/create'),
            'view' => Pages\ViewAnggota::route('/{record}'),
            'edit' => Pages\EditAnggota::route('/{record}/edit'),
        ];
    }
}
