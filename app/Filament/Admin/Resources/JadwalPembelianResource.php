<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StatusJadwal;
use App\Filament\Admin\Resources\JadwalPembelianResource\Pages;
use App\Filament\Concerns\Bisnis;
use App\Filament\Concerns\DenganIzin;
use App\Models\JadwalPembelian;
use App\Services\JadwalService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JadwalPembelianResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = JadwalPembelian::class;
    protected static ?string $slug = 'jadwal-pembelian';
    protected static string $izinLihat = 'jadwal.manage';
    protected static ?string $izinBuat = null; // jadwal dibuat dari halaman pengajuan yang disetujui
    protected static ?string $izinUbah = null;

    protected static ?string $modelLabel = 'Jadwal pembelian';
    protected static ?string $pluralModelLabel = 'Jadwal pembelian';
    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 2;

    private static function aktif(JadwalPembelian $r): bool
    {
        return in_array($r->status, [StatusJadwal::Terjadwal, StatusJadwal::Ditunda], true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_jadwal')->label('No. jadwal')->searchable(),
                TextColumn::make('tanggal_waktu')->label('Waktu')->dateTime('D, d/m/Y H:i')->sortable(),
                TextColumn::make('pengajuan.nomor_pengajuan')->label('No. pengajuan')->searchable(),
                TextColumn::make('pengajuan.anggota.nama_lengkap')->label('Anggota'),
                TextColumn::make('pengajuan.kapal.nama_kapal')->label('Kapal'),
                TextColumn::make('liter_disetujui')->label('Liter')->formatStateUsing(fn ($s) => Format::liter($s)),
                TextColumn::make('status')->badge(),
                TextColumn::make('keterangan')->placeholder('-')->wrap()->toggleable(),
            ])
            ->defaultSort('tanggal_waktu', 'asc')
            ->filters([
                SelectFilter::make('status')->options(StatusJadwal::opsi()),
                SelectFilter::make('rentang')->label('Tanggal')->options(['hari_ini' => 'Hari ini', 'minggu_ini' => '7 hari ke depan', 'bulan_ini' => 'Bulan ini'])
                    ->query(function (Builder $query, array $data) {
                        match ($data['value'] ?? null) {
                            'hari_ini' => $query->whereDate('tanggal_waktu', today()),
                            'minggu_ini' => $query->whereBetween('tanggal_waktu', [now()->startOfDay(), now()->addDays(7)->endOfDay()]),
                            'bulan_ini' => $query->whereBetween('tanggal_waktu', [now()->startOfMonth(), now()->endOfMonth()]),
                            default => null,
                        };
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('ubah')->label('Ubah jadwal')->icon('heroicon-o-pencil-square')
                        ->visible(fn (JadwalPembelian $r) => self::aktif($r))
                        ->schema([
                            DateTimePicker::make('tanggal_waktu')->required()->seconds(false)->default(fn (JadwalPembelian $r) => $r->tanggal_waktu),
                            Textarea::make('keterangan')->default(fn (JadwalPembelian $r) => $r->keterangan),
                        ])
                        ->action(fn (JadwalPembelian $record, array $data) => Bisnis::jalankan(
                            fn () => app(JadwalService::class)->ubah($record, $data['tanggal_waktu'], $data['keterangan'] ?? null, auth()->user()), 'Jadwal diubah.')),
                    Action::make('tunda')->label('Tunda')->icon('heroicon-o-pause-circle')->color('warning')
                        ->visible(fn (JadwalPembelian $r) => $r->status === StatusJadwal::Terjadwal)
                        ->schema([Textarea::make('alasan')->label('Alasan penundaan')->required()])
                        ->action(fn (JadwalPembelian $record, array $data) => Bisnis::jalankan(
                            fn () => app(JadwalService::class)->tunda($record, $data['alasan'], auth()->user()), 'Jadwal ditunda. Atur ulang dengan "Ubah jadwal".')),
                    Action::make('selesai')->label('Tandai selesai')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (JadwalPembelian $r) => self::aktif($r))->requiresConfirmation()
                        ->action(fn (JadwalPembelian $record) => Bisnis::jalankan(
                            fn () => app(JadwalService::class)->tandaiSelesai($record, auth()->user()), 'Jadwal ditandai selesai.')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListJadwal::route('/')];
    }
}
