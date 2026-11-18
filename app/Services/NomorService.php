<?php

namespace App\Services;

use App\Models\Penomoran;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Penerbit nomor dokumen berurutan per jenis per bulan, aman terhadap proses bersamaan. */
class NomorService
{
    private const PREFIX = [
        'pengajuan' => 'PGJ', 'jadwal' => 'JDW', 'penerimaan' => 'TRM',
        'penyaluran' => 'SLR', 'tagihan' => 'TGH', 'pembayaran' => 'BYR',
    ];

    public function terbitkan(string $jenis, ?CarbonInterface $tanggal = null): string
    {
        $periode = ($tanggal ?? now())->format('Ym');

        return DB::transaction(function () use ($jenis, $periode) {
            DB::table('penomoran')->insertOrIgnore([
                'jenis' => $jenis, 'periode' => $periode, 'nomor_terakhir' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $baris = Penomoran::query()->where('jenis', $jenis)->where('periode', $periode)->lockForUpdate()->firstOrFail();
            $baris->nomor_terakhir = $baris->nomor_terakhir + 1;
            $baris->save();

            return sprintf('%s-%s-%04d', self::PREFIX[$jenis] ?? strtoupper($jenis), $periode, $baris->nomor_terakhir);
        });
    }
}
