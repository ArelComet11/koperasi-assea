<?php

namespace App\Models;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenyaluran;
use App\Models\Concerns\HanyaMilikAnggota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PengajuanPembelian extends Model
{
    use HanyaMilikAnggota;

    protected $table = 'pengajuan_pembelian';

    /** Hanya field masukan pengguna. Status, harga snapshot, dsb. diisi service lewat forceFill. */
    protected $fillable = ['anggota_id', 'kapal_id', 'surat_rekomendasi_id', 'liter_diminta', 'tanggal_rencana', 'catatan'];

    protected function casts(): array
    {
        return [
            'status' => StatusPengajuan::class,
            'status_pembayaran' => StatusPembayaran::class,
            'status_penyaluran' => StatusPenyaluran::class,
            'liter_diminta' => 'decimal:2',
            'harga_estimasi_per_liter' => 'decimal:2',
            'total_estimasi' => 'decimal:2',
            'harga_per_liter' => 'decimal:2',
            'total_tagihan' => 'decimal:2',
            'tanggal_rencana' => 'date',
            'periode_kuota' => 'date:Y-m-d',
            'disetujui_pada' => 'datetime',
            'ditolak_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    protected static function batasiKeAnggota(Builder $query, int $anggotaId): void
    {
        $query->where($query->getModel()->getTable() . '.anggota_id', $anggotaId);
    }

    public function anggota(): BelongsTo { return $this->belongsTo(Anggota::class); }
    public function kapal(): BelongsTo { return $this->belongsTo(Kapal::class); }
    public function suratRekomendasi(): BelongsTo { return $this->belongsTo(SuratRekomendasi::class); }
    public function alokasi(): HasOne { return $this->hasOne(AlokasiBbm::class, 'pengajuan_id'); }
    public function jadwal(): HasMany { return $this->hasMany(JadwalPembelian::class, 'pengajuan_id'); }
    public function penyaluran(): HasOne { return $this->hasOne(PenyaluranBbm::class, 'pengajuan_id'); }
    public function tagihan(): HasOne { return $this->hasOne(Tagihan::class, 'pengajuan_id'); }
    public function riwayat(): HasMany { return $this->hasMany(RiwayatStatusPengajuan::class, 'pengajuan_id'); }

    /** @return array{0:string,1:string} [label, warna] termasuk status turunan "Menunggu pembayaran/penyaluran". */
    public function statusTampil(): array
    {
        if ($this->status === StatusPengajuan::Disetujui) {
            if ($this->status_pembayaran === StatusPembayaran::BelumDibayar) {
                return ['Menunggu pembayaran', 'warning'];
            }
            if ($this->status_penyaluran === StatusPenyaluran::Belum) {
                return ['Menunggu penyaluran', 'info'];
            }
        }

        return [$this->status->getLabel(), $this->status->getColor()];
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusTampil()[0];
    }
}
