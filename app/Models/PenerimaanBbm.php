<?php

namespace App\Models;

use App\Enums\StatusPenerimaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenerimaanBbm extends Model
{
    protected $table = 'penerimaan_bbm';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => StatusPenerimaan::class,
            'tanggal_penerimaan' => 'date',
            'periode' => 'date:Y-m-d',
            'liter' => 'decimal:2',
            'harga_per_liter' => 'decimal:2',
            'total_nilai' => 'decimal:2',
            'override_pasokan' => 'boolean',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    public function pencatat(): BelongsTo { return $this->belongsTo(User::class, 'dicatat_oleh'); }
}
