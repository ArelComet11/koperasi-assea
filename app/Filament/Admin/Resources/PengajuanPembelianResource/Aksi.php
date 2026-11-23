<?php

namespace App\Filament\Admin\Resources\PengajuanPembelianResource;

use App\Enums\StatusPengajuan;
use App\Enums\StatusPenyaluran;
use App\Filament\Concerns\Bisnis;
use App\Models\PengajuanPembelian;
use App\Services\JadwalService;
use App\Services\KuotaService;
use App\Services\PasokanService;
use App\Services\PengajuanService;
use App\Services\PenyaluranService;
use App\Services\StokService;
use App\Support\Format;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/** Aksi alur pengajuan. Semua perhitungan dan aturan ada di service; di sini hanya antarmuka. */
class Aksi
{
    public static function semua(): array
    {
        return [self::setujui(), self::tolak(), self::jadwal(), self::salurkan(), self::batalkan()];
    }

    private static function bisa(string $izin): bool
    {
        return Otorisasi::bisa($izin);
    }

    public static function setujui(): Action
    {
        return Action::make('setujui')->label('Setujui')->icon('heroicon-o-check-circle')->color('success')
            ->visible(fn (PengajuanPembelian $r) => $r->status === StatusPengajuan::MenungguPersetujuan && self::bisa('pengajuan.approve'))
            ->requiresConfirmation()->modalHeading('Setujui pengajuan')
            ->modalDescription(function (PengajuanPembelian $r) {
                $sisaKuota = $r->suratRekomendasi ? app(KuotaService::class)->sisa($r->suratRekomendasi) : '0';

                return sprintf(
                    'Diminta %s. Sisa kuota surat bulan ini: %s. Stok tersedia: %s. Sisa batas pasokan: %s. Harga dikunci saat persetujuan.',
                    Format::liter($r->liter_diminta), Format::liter($sisaKuota),
                    Format::liter(app(StokService::class)->tersedia()), Format::liter(app(PasokanService::class)->ringkasan()['sisa_alokasi'])
                );
            })
            ->action(function (PengajuanPembelian $record) {
                Bisnis::jalankan(fn () => app(PengajuanService::class)->setujui($record, Otorisasi::userWajib()), 'Pengajuan disetujui. Stok dialokasikan dan tagihan dibuat.');
                $record->refresh();
            });
    }

    public static function tolak(): Action
    {
        return Action::make('tolak')->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')
            ->visible(fn (PengajuanPembelian $r) => $r->status === StatusPengajuan::MenungguPersetujuan && self::bisa('pengajuan.reject'))
            ->modalHeading('Tolak pengajuan')
            ->schema([Textarea::make('alasan')->label('Alasan penolakan')->required()->minLength(3)->maxLength(1000)])
            ->action(function (PengajuanPembelian $record, array $data) {
                Bisnis::jalankan(fn () => app(PengajuanService::class)->tolak($record, Otorisasi::userWajib(), $data['alasan']), 'Pengajuan ditolak.');
                $record->refresh();
            });
    }

    public static function jadwal(): Action
    {
        return Action::make('jadwal')->label('Atur jadwal')->icon('heroicon-o-calendar-days')->color('info')
            ->visible(fn (PengajuanPembelian $r) => $r->status === StatusPengajuan::Disetujui && $r->status_penyaluran === StatusPenyaluran::Belum && self::bisa('jadwal.manage'))
            ->modalHeading('Jadwal pembelian / penyaluran')
            ->schema([
                DateTimePicker::make('tanggal_waktu')->label('Tanggal dan waktu')->required()->seconds(false)
                    ->default(fn (PengajuanPembelian $r) => ($r->tanggal_rencana ? $r->tanggal_rencana->copy() : Carbon::today())->setTime(8, 0)),
                Textarea::make('keterangan')->maxLength(500),
            ])
            ->action(function (PengajuanPembelian $record, array $data) {
                Bisnis::jalankan(fn () => app(JadwalService::class)->buat($record, $data['tanggal_waktu'], $data['keterangan'] ?? null, Otorisasi::userWajib()), 'Jadwal dibuat.');
            });
    }

    public static function salurkan(): Action
    {
        return Action::make('salurkan')->label('Catat penyaluran')->icon('heroicon-o-truck')->color('primary')
            ->visible(fn (PengajuanPembelian $r) => $r->status === StatusPengajuan::Disetujui && $r->status_penyaluran === StatusPenyaluran::Belum && self::bisa('penyaluran.manage'))
            ->modalHeading('Catat penyaluran BBM aktual')
            ->modalDescription('Jumlah aktual tidak boleh melebihi jumlah disetujui. Sisa alokasi dilepas otomatis. Transaksi selesai bila pembayaran sudah lunas.')
            ->schema([
                TextInput::make('liter_aktual')->label('Liter aktual disalurkan')->numeric()->required()->minValue(0.01)->suffix('liter')
                    ->default(fn (PengajuanPembelian $r) => $r->alokasi?->liter_disetujui),
                DateTimePicker::make('tanggal')->label('Tanggal penyaluran')->required()->seconds(false)->default(now())->maxDate(now()->addMinute()),
                Textarea::make('keterangan')->maxLength(500),
            ])
            ->action(function (PengajuanPembelian $record, array $data) {
                Bisnis::jalankan(fn () => app(PenyaluranService::class)->catat($record, $data['liter_aktual'], Otorisasi::userWajib(), Carbon::parse($data['tanggal']), $data['keterangan'] ?? null), 'Penyaluran dicatat. Stok fisik dan kuota diperbarui.');
                $record->refresh();
            });
    }

    public static function batalkan(): Action
    {
        return Action::make('batalkan')->label('Batalkan')->icon('heroicon-o-arrow-uturn-left')->color('danger')
            ->visible(fn (PengajuanPembelian $r) => $r->status === StatusPengajuan::Disetujui && $r->status_penyaluran === StatusPenyaluran::Belum && self::bisa('pengajuan.cancel'))
            ->modalHeading('Batalkan pengajuan yang telah disetujui')
            ->modalDescription('Alokasi stok dan kuota akan dilepas. Stok fisik tidak berubah karena BBM belum keluar.')
            ->schema([Textarea::make('alasan')->label('Alasan pembatalan')->required()->minLength(3)->maxLength(1000)])
            ->action(function (PengajuanPembelian $record, array $data) {
                Bisnis::jalankan(fn () => app(PengajuanService::class)->batalkan($record, Otorisasi::userWajib(), $data['alasan']), 'Pengajuan dibatalkan dan alokasi dilepas.');
                $record->refresh();
            });
    }
}
