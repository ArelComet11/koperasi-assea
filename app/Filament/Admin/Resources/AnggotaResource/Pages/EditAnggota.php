<?php

namespace App\Filament\Admin\Resources\AnggotaResource\Pages;

use App\Filament\Admin\Resources\AnggotaResource;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Concerns\Bisnis;
use App\Services\AnggotaService;
use App\Support\Otorisasi;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditAnggota extends EditRecord
{
    protected static string $resource = AnggotaResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['username_tampil'] = $this->getRecord()->user?->username;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $hasil = Bisnis::jalankan(fn () => app(AnggotaService::class)->perbarui($record, $data, Otorisasi::userWajib()));

        if ($hasil === null) {
            throw new Halt();
        }

        return $record->refresh();
    }
}
