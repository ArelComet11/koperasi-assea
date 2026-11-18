<?php

namespace App\Filament\Admin\Resources\AnggotaResource\Pages;

use App\Filament\Admin\Resources\AnggotaResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Concerns\Bisnis;
use App\Services\AnggotaService;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateAnggota extends CreateRecord
{
    protected static string $resource = AnggotaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $anggota = Bisnis::jalankan(fn () => app(AnggotaService::class)->buat($data, auth()->user()));

        if (! $anggota instanceof Model) {
            throw new Halt();
        }

        return $anggota;
    }
}
