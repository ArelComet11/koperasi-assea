<?php

namespace App\Filament\Anggota\Resources\PengajuanSayaResource\Pages;

use App\Filament\Anggota\Resources\PengajuanSayaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPengajuanSaya extends ViewRecord
{
    protected static string $resource = PengajuanSayaResource::class;

    protected function getHeaderActions(): array
    {
        return [PengajuanSayaResource::batalkanAksi()];
    }
}
