<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusAktif: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';

    public function getColor(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Nonaktif => 'gray',
        };
    }
}
