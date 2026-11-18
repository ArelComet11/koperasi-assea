<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusAlokasi: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case Aktif = 'aktif';
    case Selesai = 'selesai';
    case Dilepas = 'dilepas';

    public function getColor(): string
    {
        return match ($this) {
            self::Aktif => 'info',
            self::Selesai => 'success',
            self::Dilepas => 'gray',
        };
    }
}
