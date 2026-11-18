<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AktivitasAdminResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\AktivitasAdmin;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AktivitasAdminResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = AktivitasAdmin::class;
    protected static ?string $slug = 'aktivitas-admin';
    protected static string $izinLihat = 'audit.view';
    protected static ?string $izinBuat = null;
    protected static ?string $izinUbah = null;

    protected static ?string $modelLabel = 'Aktivitas';
    protected static ?string $pluralModelLabel = 'Riwayat aktivitas';
    protected static ?string $navigationLabel = 'Riwayat Aktivitas';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        $json = fn ($data) => $data ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-';
        $mono = ['style' => 'white-space:pre-wrap;font-family:monospace;font-size:.8rem'];

        return $schema->components([
            TextEntry::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i:s'),
            TextEntry::make('nama_pengguna')->label('Pengguna')->placeholder('-'),
            TextEntry::make('jenis'),
            TextEntry::make('keterangan')->placeholder('-'),
            TextEntry::make('ip_address')->label('IP')->placeholder('-'),
            TextEntry::make('data_lama')->label('Data lama')->state(fn ($record) => $json($record->data_lama))->extraAttributes($mono),
            TextEntry::make('data_baru')->label('Data baru')->state(fn ($record) => $json($record->data_baru))->extraAttributes($mono),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('nama_pengguna')->label('Pengguna')->searchable()->placeholder('-'),
                TextColumn::make('jenis')->badge()->searchable(),
                TextColumn::make('keterangan')->limit(60)->searchable()->placeholder('-'),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([SelectFilter::make('jenis')->options(fn () => AktivitasAdmin::query()->distinct()->orderBy('jenis')->pluck('jenis', 'jenis')->all())->searchable()])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAktivitas::route('/')];
    }
}
