<?php

namespace App\Filament\Admin\Resources\PenerimaanBbmResource\Pages;

use App\Filament\Admin\Resources\PenerimaanBbmResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenerimaan extends ListRecords
{
    protected static string $resource = PenerimaanBbmResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
