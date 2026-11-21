<?php

namespace App\Models;

use App\Models\Concerns\HanyaMilikAnggota;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuotaRekomendasiBulanan extends Model
{
    use HanyaMilikAnggota;

    protected $table = 'kuota_rekomendasi_bulanan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'periode' => 'date:Y-m-d',
            'batas_liter' => 'decimal:2',
            'liter_dialokasikan' => 'decimal:2',
            'liter_terpakai' => 'decimal:2',
        ];
    }

    protected static function batasiKeAnggota(Builder $query, int $anggotaId): void
    {
        $query->whereHas('suratRekomendasi', fn (Builder $q) => $q->withoutGlobalScope('milik_anggota')->where('anggota_id', $anggotaId));
    }

    public function suratRekomendasi(): BelongsTo { return $this->belongsTo(SuratRekomendasi::class); }

    public function getSisaAttribute(): string
    {
        return Dec::max0(Dec::sub(Dec::sub($this->batas_liter, $this->liter_terpakai), $this->liter_dialokasikan));
    }
}
