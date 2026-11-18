<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\KuotaRekomendasiBulanan;
use App\Models\SuratRekomendasi;
use App\Support\Dec;
use App\Support\Format;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class KuotaService
{
    public function __construct(private PengaturanService $pengaturan)
    {
    }

    /** Tanggal 1 pada bulan yang dimaksud. */
    public function periode(?CarbonInterface $tanggal = null): Carbon
    {
        return Carbon::instance($tanggal ?? now())->startOfMonth()->startOfDay();
    }

    /** Batas efektif: nilai pada surat, tidak pernah melebihi batas global. */
    public function batas(SuratRekomendasi $surat): string
    {
        $global = $this->pengaturan->angka('kuota.surat_bulanan');
        $milik = Dec::n($surat->kuota_bulanan_liter);

        return Dec::lt($milik, $global) ? $milik : $global;
    }

    /** Baris kuota yang TERKUNCI (dibuat dulu bila belum ada, karena lockForUpdate tak bisa mengunci baris yang belum ada). */
    public function baris(SuratRekomendasi $surat, CarbonInterface $periode): KuotaRekomendasiBulanan
    {
        $p = $this->periode($periode)->toDateString();

        DB::table('kuota_rekomendasi_bulanan')->insertOrIgnore([
            'surat_rekomendasi_id' => $surat->id, 'periode' => $p, 'batas_liter' => $this->batas($surat),
            'liter_dialokasikan' => 0, 'liter_terpakai' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return KuotaRekomendasiBulanan::query()->withoutGlobalScopes()
            ->where('surat_rekomendasi_id', $surat->id)->where('periode', $p)->lockForUpdate()->firstOrFail();
    }

    /** Sisa kuota tanpa kunci (validasi awal / tampilan). Sisa = batas - terpakai - alokasi aktif. */
    public function sisa(SuratRekomendasi $surat, ?CarbonInterface $periode = null): string
    {
        $row = KuotaRekomendasiBulanan::query()->withoutGlobalScopes()
            ->where('surat_rekomendasi_id', $surat->id)->where('periode', $this->periode($periode)->toDateString())->first();

        if (! $row) {
            return $this->batas($surat);
        }

        return Dec::max0(Dec::sub(Dec::sub($row->batas_liter, $row->liter_terpakai), $row->liter_dialokasikan));
    }

    public function alokasi(KuotaRekomendasiBulanan $row, string $liter): void
    {
        $sisa = Dec::sub(Dec::sub($row->batas_liter, $row->liter_terpakai), $row->liter_dialokasikan);
        if (Dec::gt($liter, $sisa)) {
            throw new BisnisException('Kuota bulanan surat rekomendasi tidak mencukupi (sisa ' . Format::liter(Dec::max0($sisa)) . ').');
        }
        $row->forceFill(['liter_dialokasikan' => Dec::add($row->liter_dialokasikan, $liter)])->save();
    }

    public function lepas(KuotaRekomendasiBulanan $row, string $liter): void
    {
        $row->forceFill(['liter_dialokasikan' => Dec::max0(Dec::sub($row->liter_dialokasikan, $liter))])->save();
    }

    /** Alokasi (jumlah disetujui) berubah menjadi pemakaian aktual; sisanya dilepas. */
    public function realisasi(KuotaRekomendasiBulanan $row, string $disetujui, string $aktual): void
    {
        $row->forceFill([
            'liter_dialokasikan' => Dec::max0(Dec::sub($row->liter_dialokasikan, $disetujui)),
            'liter_terpakai' => Dec::add($row->liter_terpakai, $aktual),
        ])->save();
    }
}
