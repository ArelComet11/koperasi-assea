<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PengaturanResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\Pengaturan;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Pengaturan website dan koperasi (key-value). Kunci dibuat oleh seeder; admin hanya mengubah nilainya. */
class PengaturanResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = Pengaturan::class;
    protected static ?string $slug = 'pengaturan';
    protected static string $izinLihat = 'pengaturan.manage';
    protected static ?string $izinBuat = null;
    protected static ?string $izinUbah = 'pengaturan.manage';

    protected static ?string $modelLabel = 'Pengaturan';
    protected static ?string $pluralModelLabel = 'Pengaturan';
    protected static ?string $navigationLabel = 'Pengaturan Koperasi & Website';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $tipe = fn (string $t) => fn (?Pengaturan $record) => $record?->tipe === $t;

        return $schema->components([
            TextInput::make('label')->disabled()->dehydrated(false)->columnSpanFull(),
            TextInput::make('nilai_string')->label('Nilai')->maxLength(500)->visible($tipe('string'))->columnSpanFull()
                ->email(fn (?Pengaturan $record) => $record?->kunci === 'koperasi.email')
                ->url(fn (?Pengaturan $record) => in_array($record?->kunci, ['koperasi.peta', 'sosial.facebook', 'sosial.instagram', 'sosial.youtube'], true)),
            TextInput::make('nilai_angka')->label('Nilai (angka)')->numeric()->minValue(1)->required()->visible($tipe('number'))->columnSpanFull(),
            Textarea::make('nilai_text')->label('Nilai')->rows(4)->maxLength(2000)->visible($tipe('text'))->columnSpanFull(),
            RichEditor::make('nilai_rich')->label('Isi')->visible($tipe('richtext'))->columnSpanFull(),
            FileUpload::make('nilai_file')->label('Berkas')->image()->disk('public')->directory('logo')->maxSize(2048)->visible($tipe('file'))->columnSpanFull()
                ->helperText('Kosongkan untuk memakai logo resmi bawaan. Logo resmi tidak boleh diubah bentuk dan warnanya.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kelompok')->badge()->sortable(),
                TextColumn::make('label')->searchable()->wrap(),
                TextColumn::make('nilai')->limit(70)->placeholder('(belum diisi)')->html(false)
                    ->formatStateUsing(fn ($state) => $state === null || $state === '' ? null : strip_tags((string) $state)),
            ])
            ->defaultSort('kelompok')
            ->filters([SelectFilter::make('kelompok')->options(fn () => Pengaturan::query()->distinct()->pluck('kelompok', 'kelompok')->all())])
            ->recordActions([EditAction::make()->label('Ubah')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPengaturan::route('/'),
            'edit' => Pages\EditPengaturan::route('/{record}/edit'),
        ];
    }
}
