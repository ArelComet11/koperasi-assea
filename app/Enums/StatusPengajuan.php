<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPengajuan: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';
    case Selesai = 'selesai';

    public function getColor(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'warning',
            self::Disetujui => 'info',
            self::Ditolak, self::Dibatalkan => 'danger',
            self::Selesai => 'success',
        };
    }
}
