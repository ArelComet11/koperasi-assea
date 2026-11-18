<?php

namespace App\Filament\Admin\Resources\PengurusResource\Pages;

use App\Filament\Admin\Resources\PengurusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePengurus extends ManageRecords
{
    protected static string $resource = PengurusResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
