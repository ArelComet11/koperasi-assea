<?php

namespace App\Filament\Anggota\Resources\PengajuanSayaResource\Pages;

use App\Filament\Anggota\Resources\PengajuanSayaResource;
use App\Filament\Concerns\Bisnis;
use App\Services\PengajuanService;
use App\Support\Otorisasi;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;

class CreatePengajuanSaya extends CreateRecord
{
    protected static string $resource = PengajuanSayaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $p = Bisnis::jalankan(fn () => app(PengajuanService::class)->ajukan(Otorisasi::userWajib()->anggota, $data), 'Pengajuan terkirim dan menunggu persetujuan admin.');

        if (! $p instanceof Model) {
            throw new Halt();
        }

        return $p;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return PengajuanSayaResource::getUrl('index');
    }
}
