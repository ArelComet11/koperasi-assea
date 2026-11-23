<?php

namespace App\Filament\Admin\Resources\BeritaResource\Pages;

use App\Filament\Admin\Resources\BeritaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBerita extends CreateRecord
{
    protected static string $resource = BeritaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['penulis_id'] = Auth::id();

        return $data;
    }
}
