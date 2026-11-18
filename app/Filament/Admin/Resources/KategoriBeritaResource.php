<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\KategoriBeritaResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\KategoriBerita;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class KategoriBeritaResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = KategoriBerita::class;
    protected static ?string $slug = 'kategori-berita';
    protected static string $izinLihat = 'konten.manage';
    protected static ?string $izinBuat = 'konten.manage';
    protected static ?string $izinUbah = 'konten.manage';

    protected static ?string $modelLabel = 'Kategori berita';
    protected static ?string $pluralModelLabel = 'Kategori berita';
    protected static string|\UnitEnum|null $navigationGroup = 'Konten Website';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->required()->maxLength(100)->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
            TextInput::make('slug')->required()->maxLength(120)->alphaDash()->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('nama')->searchable(), TextColumn::make('slug'), TextColumn::make('berita_count')->counts('berita')->label('Jumlah berita')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageKategoriBerita::route('/')];
    }
}
