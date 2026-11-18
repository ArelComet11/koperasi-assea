<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PengurusResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\Pengurus;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PengurusResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Pengurus::class;
    protected static ?string $slug = 'pengurus';
    protected static string $izinLihat = 'konten.manage';
    protected static ?string $izinBuat = 'konten.manage';
    protected static ?string $izinUbah = 'konten.manage';

    protected static ?string $modelLabel = 'Pengurus';
    protected static ?string $pluralModelLabel = 'Struktur organisasi';
    protected static ?string $navigationLabel = 'Struktur Organisasi';
    protected static string|\UnitEnum|null $navigationGroup = 'Konten Website';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->required()->maxLength(150),
            TextInput::make('jabatan')->required()->maxLength(100),
            FileUpload::make('foto')->image()->disk('public')->directory('pengurus')->maxSize(2048),
            TextInput::make('urutan')->numeric()->default(0)->minValue(0),
            Toggle::make('tampil')->label('Tampilkan di website')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')->disk('public')->circular(),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('jabatan'),
                TextColumn::make('urutan')->sortable(),
                IconColumn::make('tampil')->boolean(),
            ])
            ->defaultSort('urutan')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePengurus::route('/')];
    }
}
