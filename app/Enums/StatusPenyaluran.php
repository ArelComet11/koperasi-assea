<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPenyaluran: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case Belum = 'belum';
    case Selesai = 'selesai';

    public function getColor(): string
    {
        return match ($this) {
            self::Belum => 'warning',
            self::Selesai => 'success',
        };
    }
}
