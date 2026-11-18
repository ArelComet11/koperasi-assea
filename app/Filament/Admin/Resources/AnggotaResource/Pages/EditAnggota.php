<?php

namespace App\Filament\Admin\Resources\AnggotaResource\Pages;

use App\Filament\Admin\Resources\AnggotaResource;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Concerns\Bisnis;
use App\Services\AnggotaService;
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
        Bisnis::jalankan(fn () => app(AnggotaService::class)->perbarui($record, $data, auth()->user()));

        return $record->refresh();
    }
}
