<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusBerita;
use App\Filament\Admin\Resources\BeritaResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\Berita;
use App\Models\KategoriBerita;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BeritaResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Berita::class;
    protected static ?string $slug = 'berita';
    protected static string $izinLihat = 'konten.manage';
    protected static ?string $izinBuat = 'konten.manage';
    protected static ?string $izinUbah = 'konten.manage';

    protected static ?string $modelLabel = 'Berita';
    protected static ?string $pluralModelLabel = 'Berita';
    protected static string|\UnitEnum|null $navigationGroup = 'Konten Website';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('judul')->required()->maxLength(200)->live(onBlur: true)->columnSpanFull()
                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
            TextInput::make('slug')->required()->maxLength(220)->alphaDash()->unique(ignoreRecord: true),
            Select::make('kategori_berita_id')->label('Kategori')->searchable()
                ->options(fn () => KategoriBerita::query()->orderBy('nama')->pluck('nama', 'id'))
                ->createOptionForm([TextInput::make('nama')->required(), TextInput::make('slug')->required()->unique('kategori_berita', 'slug')])
                ->createOptionUsing(fn (array $data) => KategoriBerita::create($data)->getKey()),
            Textarea::make('ringkasan')->maxLength(500)->columnSpanFull(),
            RichEditor::make('konten')->required()->columnSpanFull(),
            FileUpload::make('gambar')->image()->disk('public')->directory('berita')->maxSize(2048)->helperText('Maksimal 2 MB.'),
            Select::make('status')->options(StatusBerita::opsi())->default('draft')->required(),
            DateTimePicker::make('published_at')->label('Tanggal terbit')->default(now())->seconds(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('gambar')->disk('public')->square(),
                TextColumn::make('judul')->searchable()->limit(60)->sortable(),
                TextColumn::make('kategori.nama')->label('Kategori'),
                TextColumn::make('status')->badge(),
                TextColumn::make('published_at')->label('Terbit')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([SelectFilter::make('status')->options(StatusBerita::opsi())])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBerita::route('/'),
            'create' => Pages\CreateBerita::route('/create'),
            'edit' => Pages\EditBerita::route('/{record}/edit'),
        ];
    }
}
