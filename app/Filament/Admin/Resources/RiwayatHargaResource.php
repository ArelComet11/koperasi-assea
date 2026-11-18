<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RiwayatHargaResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\RiwayatHarga;
use App\Services\HargaService;
use App\Support\Format;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RiwayatHargaResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = RiwayatHarga::class;
    protected static ?string $slug = 'riwayat-harga';
    protected static string $izinLihat = 'tagihan.view';
    protected static ?string $izinBuat = 'harga.update';
    protected static ?string $izinUbah = null;

    protected static ?string $modelLabel = 'Harga BBM';
    protected static ?string $pluralModelLabel = 'Harga BBM';
    protected static ?string $navigationLabel = 'Harga BBM';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('harga_per_liter')->label('Harga baru per liter')->numeric()->required()->minValue(1)->prefix('Rp')
                ->helperText('Hanya berlaku untuk pengajuan yang disetujui setelah ini. Transaksi lama tidak berubah (harga disimpan sebagai snapshot).'),
            TextInput::make('alasan')->label('Alasan perubahan')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading(fn () => 'Harga aktif saat ini: ' . Format::rupiah(app(HargaService::class)->aktif()) . ' per liter')
            ->columns([
                TextColumn::make('berlaku_mulai')->label('Berlaku sejak')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('harga_per_liter')->label('Harga/liter')->formatStateUsing(fn ($s) => Format::rupiah($s)),
                TextColumn::make('pengubah.name')->label('Diubah oleh')->placeholder('Sistem'),
                TextColumn::make('alasan')->placeholder('-')->wrap(),
            ])
            ->defaultSort('berlaku_mulai', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRiwayatHarga::route('/')];
    }
}
