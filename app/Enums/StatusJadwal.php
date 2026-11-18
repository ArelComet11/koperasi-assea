<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusJadwal: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case Terjadwal = 'terjadwal';
    case Ditunda = 'ditunda';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function getColor(): string
    {
        return match ($this) {
            self::Terjadwal => 'info',
            self::Ditunda => 'warning',
            self::Selesai => 'success',
            self::Dibatalkan => 'danger',
        };
    }
}
