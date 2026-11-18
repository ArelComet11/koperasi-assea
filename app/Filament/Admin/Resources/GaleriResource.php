<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GaleriResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\Galeri;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GaleriResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Galeri::class;
    protected static ?string $slug = 'galeri';
    protected static string $izinLihat = 'konten.manage';
    protected static ?string $izinBuat = 'konten.manage';
    protected static ?string $izinUbah = 'konten.manage';

    protected static ?string $modelLabel = 'Foto galeri';
    protected static ?string $pluralModelLabel = 'Galeri';
    protected static string|\UnitEnum|null $navigationGroup = 'Konten Website';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('judul')->required()->maxLength(150),
            FileUpload::make('gambar')->required()->image()->disk('public')->directory('galeri')->maxSize(4096)->helperText('Maksimal 4 MB.'),
            Textarea::make('deskripsi'),
            DatePicker::make('tanggal_kegiatan'),
            TextInput::make('urutan')->numeric()->default(0)->minValue(0),
            Toggle::make('tampil')->label('Tampilkan di website')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('gambar')->disk('public')->square(),
                TextColumn::make('judul')->searchable(),
                TextColumn::make('tanggal_kegiatan')->date('d/m/Y'),
                TextColumn::make('urutan')->sortable(),
                IconColumn::make('tampil')->boolean(),
            ])
            ->defaultSort('urutan')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageGaleri::route('/')];
    }
}
