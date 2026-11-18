<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KuotaPasokanBulanan extends Model
{
    protected $table = 'kuota_pasokan_bulanan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'periode' => 'date:Y-m-d',
            'batas_liter' => 'decimal:2',
            'total_diterima' => 'decimal:2',
            'total_dialokasikan' => 'decimal:2',
            'total_disalurkan' => 'decimal:2',
        ];
    }
}
