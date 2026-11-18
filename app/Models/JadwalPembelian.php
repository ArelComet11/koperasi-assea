<?php

namespace App\Models;

use App\Enums\StatusJadwal;
use App\Models\Concerns\HanyaMilikAnggota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPembelian extends Model
{
    use HanyaMilikAnggota;

    protected $table = 'jadwal_pembelian';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => StatusJadwal::class, 'tanggal_waktu' => 'datetime', 'liter_disetujui' => 'decimal:2'];
    }

    protected static function batasiKeAnggota(Builder $query, int $anggotaId): void
    {
        $query->whereHas('pengajuan', fn (Builder $q) => $q->where('anggota_id', $anggotaId));
    }

    public function pengajuan(): BelongsTo { return $this->belongsTo(PengajuanPembelian::class, 'pengajuan_id'); }
    public function pembuat(): BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
}
