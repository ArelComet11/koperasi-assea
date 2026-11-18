<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\KuotaPasokanBulanan;
use App\Support\Dec;
use App\Support\Format;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Batas pasokan bulanan (default 160.000 L). Dua aturan:
 *  (a) total penerimaan sebulan <= batas (kecuali override tercatat);
 *  (b) alokasi aktif + penyaluran sebulan <= batas.
 */
class PasokanService
{
    public function __construct(private PengaturanService $pengaturan)
    {
    }

    public function periode(?CarbonInterface $tanggal = null): Carbon
    {
        return Carbon::instance($tanggal ?? now())->startOfMonth()->startOfDay();
    }

    public function batas(): string
    {
        return $this->pengaturan->angka('pasokan.batas_bulanan');
    }

    public function baris(CarbonInterface $periode): KuotaPasokanBulanan
    {
        $p = $this->periode($periode)->toDateString();

        DB::table('kuota_pasokan_bulanan')->insertOrIgnore([
            'periode' => $p, 'batas_liter' => $this->batas(), 'total_diterima' => 0,
            'total_dialokasikan' => 0, 'total_disalurkan' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return KuotaPasokanBulanan::query()->where('periode', $p)->lockForUpdate()->firstOrFail();
    }

    public function sisaAlokasi(KuotaPasokanBulanan $row): string
    {
        return Dec::sub($row->batas_liter, Dec::add($row->total_dialokasikan, $row->total_disalurkan));
    }

    public function sisaPenerimaan(KuotaPasokanBulanan $row): string
    {
        return Dec::sub($row->batas_liter, $row->total_diterima);
    }

    public function tambahPenerimaan(KuotaPasokanBulanan $row, string $liter, bool $override): void
    {
        $baru = Dec::add($row->total_diterima, $liter);
        if (! $override && Dec::gt($baru, $row->batas_liter)) {
            throw new BisnisException('Penerimaan melebihi batas pasokan bulanan (sisa ' . Format::liter(Dec::max0($this->sisaPenerimaan($row))) . ').');
        }
        $row->forceFill(['total_diterima' => $baru])->save();
    }

    public function kurangiPenerimaan(KuotaPasokanBulanan $row, string $liter): void
    {
        $row->forceFill(['total_diterima' => Dec::max0(Dec::sub($row->total_diterima, $liter))])->save();
    }

    public function alokasi(KuotaPasokanBulanan $row, string $liter): void
    {
        if (Dec::gt($liter, $this->sisaAlokasi($row))) {
            throw new BisnisException('Batas pasokan bulanan tidak mencukupi untuk pengajuan ini (sisa ' . Format::liter(Dec::max0($this->sisaAlokasi($row))) . ').');
        }
        $row->forceFill(['total_dialokasikan' => Dec::add($row->total_dialokasikan, $liter)])->save();
    }

    public function lepas(KuotaPasokanBulanan $row, string $liter): void
    {
        $row->forceFill(['total_dialokasikan' => Dec::max0(Dec::sub($row->total_dialokasikan, $liter))])->save();
    }

    public function realisasi(KuotaPasokanBulanan $row, string $disetujui, string $aktual): void
    {
        $row->forceFill([
            'total_dialokasikan' => Dec::max0(Dec::sub($row->total_dialokasikan, $disetujui)),
            'total_disalurkan' => Dec::add($row->total_disalurkan, $aktual),
        ])->save();
    }

    /** Pembacaan tanpa kunci untuk monitoring. */
    public function ringkasan(?CarbonInterface $periode = null): array
    {
        $p = $this->periode($periode)->toDateString();
        $r = KuotaPasokanBulanan::query()->where('periode', $p)->first();
        $batas = Dec::n($r?->batas_liter ?? $this->batas());
        $diterima = Dec::n($r?->total_diterima);
        $dialokasi = Dec::n($r?->total_dialokasikan);
        $disalur = Dec::n($r?->total_disalurkan);
        $digunakan = Dec::add($dialokasi, $disalur);

        return [
            'periode' => $p, 'batas' => $batas, 'diterima' => $diterima, 'dialokasikan' => $dialokasi,
            'disalurkan' => $disalur, 'digunakan' => $digunakan,
            'sisa_penerimaan' => Dec::max0(Dec::sub($batas, $diterima)),
            'sisa_alokasi' => Dec::max0(Dec::sub($batas, $digunakan)),
            'persen' => Format::persen($digunakan, $batas),
        ];
    }

    /** Batas baru berlaku untuk bulan berjalan dan seterusnya, selama tidak di bawah pemakaian yang sudah ada. */
    public function terapkanBatasBaru(): void
    {
        $batas = $this->batas();
        KuotaPasokanBulanan::query()
            ->where('periode', '>=', $this->periode()->toDateString())
            ->whereRaw('(total_dialokasikan + total_disalurkan) <= ?', [$batas])
            ->update(['batas_liter' => $batas]);
    }
}
