<?php

namespace App\Models;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaranCatatan;
use App\Models\Concerns\HanyaMilikAnggota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use HanyaMilikAnggota;

    protected $table = 'pembayaran';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => StatusPembayaranCatatan::class,
            'metode' => MetodePembayaran::class,
            'jumlah' => 'decimal:2',
            'tanggal_pembayaran' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    protected static function batasiKeAnggota(Builder $query, int $anggotaId): void
    {
        $query->whereHas('tagihan', fn (Builder $q) => $q->where('anggota_id', $anggotaId));
    }

    public function tagihan(): BelongsTo { return $this->belongsTo(Tagihan::class); }
    public function penerima(): BelongsTo { return $this->belongsTo(User::class, 'diterima_oleh'); }
}
