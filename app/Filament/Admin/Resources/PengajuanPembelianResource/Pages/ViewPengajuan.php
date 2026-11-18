<?php

namespace App\Filament\Admin\Resources\PengajuanPembelianResource\Pages;

use App\Filament\Admin\Resources\PengajuanPembelianResource;
use App\Filament\Admin\Resources\PengajuanPembelianResource\Aksi;
use Filament\Resources\Pages\ViewRecord;

class ViewPengajuan extends ViewRecord
{
    protected static string $resource = PengajuanPembelianResource::class;

    protected function getHeaderActions(): array
    {
        return Aksi::semua();
    }
}
