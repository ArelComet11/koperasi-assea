<?php

namespace App\Filament\Admin\Resources\PenerimaanBbmResource\Pages;

use App\Filament\Admin\Resources\PenerimaanBbmResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Concerns\Bisnis;
use App\Services\PenerimaanService;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreatePenerimaan extends CreateRecord
{
    protected static string $resource = PenerimaanBbmResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $p = Bisnis::jalankan(fn () => app(PenerimaanService::class)->catat($data, auth()->user()), 'Penerimaan BBM dicatat. Stok fisik bertambah.');

        if (! $p instanceof Model) {
            throw new Halt();
        }

        return $p;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }
}
