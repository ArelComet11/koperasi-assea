<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JenisMutasi: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case SaldoAwal = 'saldo_awal';
    case Penerimaan = 'penerimaan';
    case PembatalanPenerimaan = 'pembatalan_penerimaan';
    case Alokasi = 'alokasi';
    case LepasAlokasi = 'lepas_alokasi';
    case Penyaluran = 'penyaluran';
    case Koreksi = 'koreksi';

    public function getColor(): string
    {
        return match ($this) {
            self::SaldoAwal, self::Penerimaan => 'success',
            self::PembatalanPenerimaan, self::Koreksi => 'danger',
            self::Alokasi => 'info',
            self::LepasAlokasi => 'warning',
            self::Penyaluran => 'primary',
        };
    }
}
