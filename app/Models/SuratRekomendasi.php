<?php

namespace App\Models;

use App\Models\Concerns\DiauditOtomatis;
use App\Enums\StatusAktif;
use App\Models\Concerns\HanyaMilikAnggota;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratRekomendasi extends Model
{
    use DiauditOtomatis, HanyaMilikAnggota, SoftDeletes;

    protected $table = 'surat_rekomendasi';

    protected $fillable = ['nomor_surat', 'anggota_id', 'kapal_id', 'tanggal_surat', 'berlaku_mulai', 'berlaku_sampai', 'kuota_bulanan_liter', 'status', 'file_path', 'keterangan'];

    protected function casts(): array
    {
        return [
            'status' => StatusAktif::class,
            'tanggal_surat' => 'date',
            'berlaku_mulai' => 'date',
            'berlaku_sampai' => 'date',
            'kuota_bulanan_liter' => 'decimal:2',
        ];
    }

    protected static function batasiKeAnggota(Builder $query, int $anggotaId): void
    {
        $query->where($query->getModel()->getTable() . '.anggota_id', $anggotaId);
    }

    public function anggota(): BelongsTo { return $this->belongsTo(Anggota::class); }
    public function kapal(): BelongsTo { return $this->belongsTo(Kapal::class); }
    public function pengajuan(): HasMany { return $this->hasMany(PengajuanPembelian::class); }
    public function kuotaBulanan(): HasMany { return $this->hasMany(KuotaRekomendasiBulanan::class); }

    public function kuotaBulanIni(): HasOne
    {
        return $this->hasOne(KuotaRekomendasiBulanan::class)
            ->withoutGlobalScopes()
            ->where('periode', now()->startOfMonth()->toDateString());
    }

    /** Surat berstatus aktif dan masa berlakunya mencakup hari ini (ramah index pada MySQL, aman di SQLite). */
    public function scopeAktifHariIni(Builder $query): Builder
    {
        $hariIni = today()->toDateString();
        $query->where('status', StatusAktif::Aktif->value);

        if ($query->getConnection()->getDriverName() === 'sqlite') {
            return $query->whereDate('berlaku_mulai', '<=', $hariIni)
                ->whereDate('berlaku_sampai', '>=', $hariIni);
        }

        return $query->where('berlaku_mulai', '<=', $hariIni)
            ->where('berlaku_sampai', '>=', $hariIni);
    }

    public function berlakuPada(CarbonInterface $tanggal): bool
    {
        $t = $tanggal->copy()->startOfDay();

        return $this->status === StatusAktif::Aktif
            && $t->gte($this->berlaku_mulai->copy()->startOfDay())
            && $t->lte($this->berlaku_sampai->copy()->startOfDay());
    }

    /** aktif | belum_berlaku | kedaluwarsa | nonaktif (kedaluwarsa & belum berlaku dihitung dari tanggal). */
    public function getStatusTampilAttribute(): string
    {
        if ($this->status !== StatusAktif::Aktif) {
            return 'nonaktif';
        }
        if ($this->berlaku_mulai->copy()->startOfDay()->isFuture()) {
            return 'belum_berlaku';
        }

        return $this->berlaku_sampai->copy()->endOfDay()->isPast() ? 'kedaluwarsa' : 'aktif';
    }
}
