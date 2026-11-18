<?php

namespace App\Models;

use App\Enums\StatusAlokasi;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlokasiBbm extends Model
{
    protected $table = 'alokasi_bbm';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => StatusAlokasi::class,
            'periode' => 'date:Y-m-d',
            'liter_disetujui' => 'decimal:2',
            'liter_tersalurkan' => 'decimal:2',
            'liter_dilepas' => 'decimal:2',
        ];
    }

    public function pengajuan(): BelongsTo { return $this->belongsTo(PengajuanPembelian::class, 'pengajuan_id'); }

    /** Liter yang masih dicadangkan (disetujui - tersalurkan - dilepas). */
    public function getLiterAktifAttribute(): string
    {
        return Dec::sub(Dec::sub($this->liter_disetujui, $this->liter_tersalurkan), $this->liter_dilepas);
    }
}
