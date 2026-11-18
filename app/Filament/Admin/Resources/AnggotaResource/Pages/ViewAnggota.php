<?php

namespace App\Filament\Admin\Resources\AnggotaResource\Pages;

use App\Filament\Admin\Resources\AnggotaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAnggota extends ViewRecord
{
    protected static string $resource = AnggotaResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
