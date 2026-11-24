<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PengurusResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\Pengurus;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PengurusResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Pengurus::class;
    protected static ?string $slug = 'pengurus';
    protected static string $izinLihat = 'konten.manage';
    protected static ?string $izinBuat = 'konten.manage';
    protected static ?string $izinUbah = 'konten.manage';
    protected static ?string $izinHapus = 'konten.manage';

    protected static ?string $modelLabel = 'Pengurus';
    protected static ?string $pluralModelLabel = 'Struktur organisasi';
    protected static ?string $navigationLabel = 'Struktur Organisasi';
    protected static string|\UnitEnum|null $navigationGroup = 'Konten Website';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')
                ->label('Nama lengkap & gelar')
                ->placeholder('mis. H. Radian Salman, S.E., M.M.')
                ->required()
                ->maxLength(150),
            TextInput::make('jabatan')
                ->label('Jabatan')
                ->placeholder('mis. Ketua Koperasi')
                ->required()
                ->maxLength(100),
            Select::make('level')
                ->label('Level jabatan di struktur')
                ->options(Pengurus::LEVEL_OPSI)
                ->default(4)
                ->required()
                ->native(false),
            TextInput::make('urutan')
                ->label('Urutan (kiri ke kanan)')
                ->numeric()
                ->default(1)
                ->minValue(0)
                ->helperText('Angka lebih kecil tampil lebih kiri pada level yang sama.'),
            Field::make('foto')
                ->label('Foto pengurus')
                ->view('filament.forms.components.foto-pengurus-otomatis')
                ->columnSpanFull(),
            Hidden::make('foto_transparan')
                ->default(true),
            Toggle::make('tampil')
                ->label('Tampilkan di website')
                ->default(true)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('level')->orderBy('urutan')->orderBy('id'))
            ->columns([
                ImageColumn::make('foto')->disk('public')->circular(),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('jabatan')->label('Jabatan')->searchable(),
                TextColumn::make('level')->label('Level')->badge()->sortable()
                    ->formatStateUsing(fn ($state) => 'Level ' . $state . ' · ' . (Pengurus::LEVEL_RINGKAS[(int) $state] ?? 'Staf'))
                    ->color(fn ($state) => match ((int) $state) {
                        1 => 'warning',
                        2 => 'info',
                        3 => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('urutan')->label('Urutan')->sortable(),
                IconColumn::make('tampil')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('level')->label('Level')->options(Pengurus::LEVEL_OPSI),
                TernaryFilter::make('tampil')->label('Tampil di website'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePengurus::route('/')];
    }
}
