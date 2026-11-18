<?php

namespace App\Models;

use App\Enums\StatusTagihan;
use App\Models\Concerns\HanyaMilikAnggota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tagihan extends Model
{
    use HanyaMilikAnggota;

    protected $table = 'tagihan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => StatusTagihan::class,
            'liter' => 'decimal:2',
            'harga_per_liter' => 'decimal:2',
            'total' => 'decimal:2',
            'tanggal_tagihan' => 'date',
        ];
    }

    protected static function batasiKeAnggota(Builder $query, int $anggotaId): void
    {
        $query->where($query->getModel()->getTable() . '.anggota_id', $anggotaId);
    }

    public function pengajuan(): BelongsTo { return $this->belongsTo(PengajuanPembelian::class, 'pengajuan_id'); }
    public function anggota(): BelongsTo { return $this->belongsTo(Anggota::class); }
    public function pembayaran(): HasMany { return $this->hasMany(Pembayaran::class); }
    public function penyesuaian(): HasMany { return $this->hasMany(PenyesuaianTagihan::class); }
}
