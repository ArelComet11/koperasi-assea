<?php

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Enums\StatusAktif;
use App\Enums\StatusPembayaranCatatan;
use App\Enums\StatusTagihan;
use App\Models\Kapal;
use App\Models\KuotaRekomendasiBulanan;
use App\Models\MutasiStok;
use App\Models\Pembayaran;
use App\Models\PenyaluranBbm;
use App\Models\PenyesuaianTagihan;
use App\Models\SuratRekomendasi;
use App\Models\Tagihan;
use App\Models\User;
use App\Support\Dec;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LaporanService
{
    public const JENIS = [
        'kapal' => 'Laporan Kapal',
        'surat' => 'Laporan Surat Rekomendasi',
        'penjualan_harian' => 'Laporan Penjualan',
        'penjualan' => 'Rekap Penjualan Sektor Perikanan',
        'stok_solar' => 'Laporan Stock Produk Solar',
        'realisasi' => 'Laporan Data Realisasi Penerimaan dan Penjualan BBM',
        'keuangan' => 'Laporan Keuangan',
    ];

    public function __construct(private KuotaService $kuota)
    {
    }

    /** Jenis laporan yang boleh dibuka pengguna. */
    public static function jenisUntuk(?User $user): array
    {
        if (! $user) {
            return [];
        }
        if ($user->can('laporan.semua')) {
            return self::JENIS;
        }
        if ($user->can('laporan.keuangan')) {
            return array_intersect_key(self::JENIS, array_flip(['realisasi', 'keuangan']));
        }

        return [];
    }

    /** @return array{judul:string,periode:string,ringkasan:array,headings:array,rows:array} */
    public function bangun(string $jenis, array $f = []): array
    {
        [$dari, $sampai] = $this->rentang($f);
        $f['_dari'] = $dari;
        $f['_sampai'] = $sampai;

        $hasil = match ($jenis) {
            'kapal' => $this->kapal($f),
            'surat' => $this->surat($f),
            'penjualan_harian' => $this->penjualanHarian($f),
            'penjualan' => $this->penjualan($f),
            'stok_solar' => $this->stokSolar($f),
            'realisasi' => $this->realisasi($f),
            'keuangan' => $this->keuangan($f),
            default => throw new \InvalidArgumentException('Jenis laporan tidak dikenal.'),
        };

        $adaPeriode = in_array($jenis, ['surat', 'penjualan_harian', 'penjualan', 'stok_solar', 'realisasi', 'keuangan'], true);
        $periodeTeks = $adaPeriode ? $dari->translatedFormat('d F Y') . ' s.d. ' . $sampai->translatedFormat('d F Y') : 'Per ' . now()->translatedFormat('d F Y');
        if (in_array($jenis, ['surat', 'penjualan_harian', 'penjualan', 'stok_solar'], true) && $dari->isSameMonth($sampai)) {
            $periodeTeks = $dari->translatedFormat('F Y');
        }
        if ($jenis === 'realisasi') {
            $tahun = (int) ($hasil['realisasi']['tahun'] ?? $dari->year);
            $periodeTeks = "Januari – Desember {$tahun}";
        }

        return [
            'jenis' => $jenis,
            'judul' => self::JENIS[$jenis],
            'subjudul' => $hasil['subjudul'] ?? null,
            'periode' => $periodeTeks,
            'ringkasan' => $hasil['ringkasan'] ?? [],
            'headings' => $hasil['headings'],
            'rows' => $hasil['rows'],
            'bulanan' => $hasil['bulanan'] ?? null,
            'realisasi' => $hasil['realisasi'] ?? null,
        ];
    }

    private function rentang(array $f): array
    {
        $dari = ! empty($f['dari']) ? Carbon::parse($f['dari'])->startOfDay() : now()->startOfMonth();
        $sampai = ! empty($f['sampai']) ? Carbon::parse($f['sampai'])->endOfDay() : now()->endOfDay();

        return [$dari, $sampai];
    }

    private function kapal(array $f): array
    {
        $q = Kapal::query()->with(['anggota', 'suratRekomendasiTerbaru'])->orderBy('kode_kapal');
        if (! empty($f['anggota_id'])) $q->where('anggota_id', $f['anggota_id']);
        if (! empty($f['kapal_id'])) $q->whereKey($f['kapal_id']);
        if (! empty($f['status'])) $q->where('status', $f['status']);
        if (! empty($f['cari'])) $q->where(fn ($w) => $w->where('nama_kapal', 'like', "%{$f['cari']}%")->orWhere('kode_kapal', 'like', "%{$f['cari']}%"));
        $data = $q->get();

        $total = PenyaluranBbm::query()->join('pengajuan_pembelian', 'pengajuan_pembelian.id', '=', 'penyaluran_bbm.pengajuan_id')
            ->groupBy('pengajuan_pembelian.kapal_id')->selectRaw('pengajuan_pembelian.kapal_id as kid, SUM(penyaluran_bbm.liter_aktual) as total')
            ->pluck('total', 'kid');

        return [
            'ringkasan' => [
                'Total kapal' => $data->count(),
                'Aktif' => $data->where('status', StatusAktif::Aktif)->count(),
                'Nonaktif' => $data->where('status', StatusAktif::Nonaktif)->count(),
            ],
            'headings' => ['Kode', 'Nama Kapal', 'Pemilik (Anggota)', 'Nomor Rekomendasi', 'GT', 'Status', 'Total BBM Diterima (L)'],
            'rows' => $data->map(fn ($k) => [$k->kode_kapal, $k->nama_kapal, $k->anggota?->nama_lengkap, self::bersihkanNomorRekom($k->suratRekomendasiTerbaru?->nomor_surat) ?: ($k->no_registrasi ?: '-'), $k->ukuran_gt !== null ? (float) $k->ukuran_gt : '-', $k->status->getLabel(), (float) ($total[$k->id] ?? 0)])->all(),
        ];
    }

    private function surat(array $f): array
    {
        $q = SuratRekomendasi::query()->with(['anggota', 'kapal']);
        if (! empty($f['anggota_id'])) $q->where('anggota_id', $f['anggota_id']);
        if (! empty($f['kapal_id'])) $q->where('kapal_id', $f['kapal_id']);
        if (! empty($f['cari'])) {
            $c = $f['cari'];
            $q->where(fn ($w) => $w->where('nomor_surat', 'like', "%{$c}%")
                ->orWhereHas('anggota', fn ($a) => $a->where('nama_lengkap', 'like', "%{$c}%"))
                ->orWhereHas('kapal', fn ($k) => $k->where('nama_kapal', 'like', "%{$c}%")));
        }

        $data = $q->get();
        if (! empty($f['status'])) {
            $data = $data->filter(fn ($s) => $s->status_tampil === $f['status'])->values();
        }

        $data = $data->sortBy(fn ($s) => [
            mb_strtolower((string) ($s->anggota?->nama_lengkap ?? '')),
            mb_strtolower((string) ($s->kapal?->nama_kapal ?? '')),
            mb_strtolower((string) $s->nomor_surat),
        ])->values();

        // Susun daftar bulan dalam rentang (maksimal 24 bulan agar memori tetap aman)
        $mulaiBulan = $f['_dari']->copy()->startOfMonth();
        $akhirBulanTarget = $f['_sampai']->copy()->startOfMonth();
        $bulanList = [];
        for ($cursor = $mulaiBulan->copy(); $cursor->lte($akhirBulanTarget) && count($bulanList) < 24; $cursor->addMonth()) {
            $bulanList[] = $cursor->copy();
        }
        if ($bulanList === []) {
            $bulanList[] = now()->startOfMonth();
        }

        $sameYear = $bulanList[0]->year === end($bulanList)->year;
        $suratIds = $data->pluck('id')->all();
        $periodeStrings = array_map(fn (Carbon $m) => $m->toDateString(), $bulanList);

        // Preload batas kuota bulanan (1 query, tanpa N+1)
        $kuotaMap = [];
        if ($suratIds !== []) {
            $kuotaRows = KuotaRekomendasiBulanan::query()
                ->withoutGlobalScopes()
                ->whereIn('surat_rekomendasi_id', $suratIds)
                ->whereIn('periode', $periodeStrings)
                ->get(['surat_rekomendasi_id', 'periode', 'batas_liter']);

            foreach ($kuotaRows as $kr) {
                $pStr = Carbon::parse($kr->periode)->toDateString();
                $kuotaMap["{$kr->surat_rekomendasi_id}|{$pStr}"] = Dec::n($kr->batas_liter);
            }
        }

        // Preload penyaluran aktual dalam rentang bulan (1 query, kompatibel MySQL & SQLite)
        $harianMap = [];
        $pernahTransaksi = [];
        if ($suratIds !== []) {
            $rentangAwal = $bulanList[0]->copy()->startOfMonth()->startOfDay();
            $rentangAkhir = end($bulanList)->copy()->endOfMonth()->endOfDay();

            $salurRows = PenyaluranBbm::query()
                ->join('pengajuan_pembelian', 'pengajuan_pembelian.id', '=', 'penyaluran_bbm.pengajuan_id')
                ->whereIn('pengajuan_pembelian.surat_rekomendasi_id', $suratIds)
                ->whereBetween('penyaluran_bbm.tanggal_penyaluran', [$rentangAwal, $rentangAkhir])
                ->get([
                    'pengajuan_pembelian.surat_rekomendasi_id as surat_id',
                    'penyaluran_bbm.tanggal_penyaluran',
                    'penyaluran_bbm.liter_aktual',
                ]);

            foreach ($salurRows as $sr) {
                $tgl = Carbon::parse($sr->tanggal_penyaluran);
                $ym = $tgl->format('Y-m');
                $sid = (int) $sr->surat_id;
                $day = (int) $tgl->day;
                $harianMap[$ym][$sid][$day] = Dec::add($harianMap[$ym][$sid][$day] ?? '0', $sr->liter_aktual);
                $pernahTransaksi[$sid] = true;
            }

            // Pada tampilan 1 bulan, cek juga surat yang pernah bertransaksi di bulan lain agar surat lama yang sudah tidak aktif tidak ikut membludak
            if (count($bulanList) === 1) {
                $sudahAda = PenyaluranBbm::query()
                    ->join('pengajuan_pembelian', 'pengajuan_pembelian.id', '=', 'penyaluran_bbm.pengajuan_id')
                    ->whereIn('pengajuan_pembelian.surat_rekomendasi_id', $suratIds)
                    ->distinct()
                    ->pluck('pengajuan_pembelian.surat_rekomendasi_id');
                foreach ($sudahAda as $sid) {
                    $pernahTransaksi[(int) $sid] = true;
                }
            }
        }

        $isSingleMonth = count($bulanList) === 1;
        $bulanan = [];
        $grandVolRekom = '0.00';
        $grandPenyaluran = '0.00';
        $grandVsRekom = '0.00';
        $suratTampilUnik = [];

        foreach ($bulanList as $m) {
            $ym = $m->format('Y-m');
            $awalBulan = $m->copy()->startOfMonth();
            $akhirBulan = $m->copy()->endOfMonth();
            $periodeStr = $m->toDateString();
            $bulanLabel = $sameYear ? $m->translatedFormat('F') : $m->translatedFormat('F Y');

            $rowsBulan = [];
            $totalHarian = array_fill(1, 31, '0.00');
            $totalVolBulan = '0.00';
            $totalPenyaluranBulan = '0.00';
            $totalVsBulan = '0.00';

            foreach ($data as $s) {
                $sid = (int) $s->id;
                $adaTrxBulan = isset($harianMap[$ym][$sid]);
                $adaKuotaBulan = isset($kuotaMap["{$sid}|{$periodeStr}"]);
                $mulaiYm = $s->berlaku_mulai ? Carbon::parse($s->berlaku_mulai)->format('Y-m') : null;
                $mulaiDiBulan = $mulaiYm === $ym;
                $overlapBulan = ($s->berlaku_mulai === null || Carbon::parse($s->berlaku_mulai)->lte($akhirBulan))
                    && ($s->berlaku_sampai === null || Carbon::parse($s->berlaku_sampai)->gte($awalBulan));

                if (! $adaTrxBulan && ! $adaKuotaBulan && ! $mulaiDiBulan && ! ($isSingleMonth && empty($pernahTransaksi[$sid]) && $overlapBulan)) {
                    continue;
                }

                $suratTampilUnik[$sid] = true;
                // Vol Rekom selalu diambil murni dari kuota_bulanan_liter pada surat rekomendasi (tidak pernah tertimpa total penyaluran)
                $kuotaSurat = Dec::n($s->kuota_bulanan_liter);
                $volRekom = Dec::gt($kuotaSurat, 0)
                    ? $kuotaSurat
                    : ($kuotaMap["{$sid}|{$periodeStr}"] ?? $this->kuota->batas($s));
                $harian = [];
                $totalSurat = '0.00';

                for ($d = 1; $d <= 31; $d++) {
                    $val = $harianMap[$ym][$sid][$d] ?? null;
                    if ($val !== null && Dec::gt($val, 0)) {
                        $harian[$d] = (float) $val;
                        $totalSurat = Dec::add($totalSurat, $val);
                        $totalHarian[$d] = Dec::add($totalHarian[$d], $val);
                    } else {
                        $harian[$d] = null;
                    }
                }

                $vsRekom = Dec::sub($totalSurat, $volRekom);
                $totalVolBulan = Dec::add($totalVolBulan, $volRekom);
                $totalPenyaluranBulan = Dec::add($totalPenyaluranBulan, $totalSurat);
                $totalVsBulan = Dec::add($totalVsBulan, $vsRekom);

                $rowsBulan[] = [
                    'nama' => (string) ($s->anggota?->nama_lengkap ?? ''),
                    'nama_kapal' => (string) ($s->kapal?->nama_kapal ?? ''),
                    'no_rekom' => self::bersihkanNomorRekom((string) $s->nomor_surat),
                    'vol_rekom' => (float) $volRekom,
                    'harian' => $harian,
                    'total' => (float) $totalSurat,
                    'total_vs_rekom' => (float) $vsRekom,
                ];
            }

            $grandVolRekom = Dec::add($grandVolRekom, $totalVolBulan);
            $grandPenyaluran = Dec::add($grandPenyaluran, $totalPenyaluranBulan);
            $grandVsRekom = Dec::add($grandVsRekom, $totalVsBulan);

            $bulanan[] = [
                'bulan_label' => $bulanLabel,
                'sheet_title' => $bulanLabel,
                'tahun' => (int) $m->year,
                'bulan' => (int) $m->month,
                'rows' => $rowsBulan,
                'total_harian' => array_map(fn ($v) => (float) $v, $totalHarian),
                'total_vol_rekom' => (float) $totalVolBulan,
                'total_penyaluran' => (float) $totalPenyaluranBulan,
                'total_vs_rekom' => (float) $totalVsBulan,
            ];
        }

        return [
            'ringkasan' => [
                'Total surat' => count($suratTampilUnik),
                'Total Vol Rekom (L)' => (float) $grandVolRekom,
                'Total Penyaluran (L)' => (float) $grandPenyaluran,
                'Total Vs Rekom (L)' => (float) $grandVsRekom,
            ],
            'headings' => array_merge(['NO', 'Nama', 'Nama Kapal', 'No Rekom', 'Vol Rekom'], array_map('strval', range(1, 31)), ['Total', 'Total Vs Rekom']),
            'rows' => [],
            'bulanan' => $bulanan,
        ];
    }

    /** Laporan Penjualan Harian Produk Solar/Bio Solar (format Lampiran 6 — 13 kolom per hari kalender). */
    private function penjualanHarian(array $f): array
    {
        $mulaiBulan = $f['_dari']->copy()->startOfMonth();
        $akhirBulanTarget = $f['_sampai']->copy()->startOfMonth();
        $bulanList = [];
        for ($cursor = $mulaiBulan->copy(); $cursor->lte($akhirBulanTarget) && count($bulanList) < 24; $cursor->addMonth()) {
            $bulanList[] = $cursor->copy();
        }
        if ($bulanList === []) {
            $bulanList[] = now()->startOfMonth();
        }

        $sameYear = $bulanList[0]->year === end($bulanList)->year;
        $rentangAwal = $bulanList[0]->copy()->startOfMonth()->startOfDay();
        $rentangAkhir = end($bulanList)->copy()->endOfMonth()->endOfDay();

        // Hitung saldo stok awal & pergerakan stok harian dari MutasiStok
        $saldoBerjalan = Dec::n(MutasiStok::query()->where('tanggal', '<', $rentangAwal)->sum('delta_fisik'));
        $mutasiPerHari = MutasiStok::query()
            ->whereBetween('tanggal', [$rentangAwal, $rentangAkhir])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get(['tanggal', 'jenis', 'delta_fisik'])
            ->groupBy(fn ($m) => Carbon::parse($m->tanggal)->toDateString());

        $stockByDate = [];
        foreach ($bulanList as $m) {
            for ($d = 1; $d <= $m->daysInMonth; $d++) {
                $tglStr = $m->copy()->day($d)->toDateString();
                $items = $mutasiPerHari->get($tglStr, collect());
                $terima = '0.00';
                $jualMutasi = '0.00';
                $koreksi = '0.00';

                foreach ($items as $mut) {
                    $jenisVal = $mut->jenis instanceof JenisMutasi ? $mut->jenis->value : (string) $mut->jenis;
                    if (in_array($jenisVal, [JenisMutasi::Penerimaan->value, JenisMutasi::PembatalanPenerimaan->value, JenisMutasi::SaldoAwal->value], true)) {
                        $terima = Dec::add($terima, $mut->delta_fisik);
                    } elseif ($jenisVal === JenisMutasi::Penyaluran->value) {
                        $jualMutasi = Dec::add($jualMutasi, Dec::sub('0', $mut->delta_fisik));
                    } elseif ($jenisVal === JenisMutasi::Koreksi->value) {
                        $koreksi = Dec::add($koreksi, $mut->delta_fisik);
                    }
                }

                $stockAwal = $saldoBerjalan;
                $stockAkhir = Dec::add(Dec::sub(Dec::add($stockAwal, $terima), $jualMutasi), $koreksi);
                $saldoBerjalan = $stockAkhir;

                $stockByDate[$tglStr] = [
                    'stock_awal' => $stockAwal,
                    'penerimaan' => $terima,
                    'stock_akhir' => $stockAkhir,
                ];
            }
        }

        // Ambil transaksi penyaluran BBM dalam rentang bulan (termasuk relasi yang sudah diarsipkan)
        $q = PenyaluranBbm::query()
            ->with([
                'pengajuan',
                'pengajuan.anggota' => fn ($rel) => $rel->withTrashed(),
                'pengajuan.kapal' => fn ($rel) => $rel->withTrashed(),
                'pengajuan.suratRekomendasi' => fn ($rel) => $rel->withTrashed(),
            ])
            ->whereBetween('tanggal_penyaluran', [$rentangAwal, $rentangAkhir])
            ->orderBy('tanggal_penyaluran')
            ->orderBy('id');

        if (! empty($f['anggota_id'])) {
            $q->whereHas('pengajuan', fn ($w) => $w->where('anggota_id', $f['anggota_id']));
        }
        if (! empty($f['kapal_id'])) {
            $q->whereHas('pengajuan', fn ($w) => $w->where('kapal_id', $f['kapal_id']));
        }
        if (! empty($f['cari'])) {
            $c = $f['cari'];
            $q->where(fn ($w) => $w->where('nomor_penyaluran', 'like', "%{$c}%")
                ->orWhereHas('pengajuan', fn ($p) => $p->where('nomor_pengajuan', 'like', "%{$c}%")
                    ->orWhereHas('anggota', fn ($a) => $a->where('nama_lengkap', 'like', "%{$c}%"))
                    ->orWhereHas('kapal', fn ($k) => $k->where('nama_kapal', 'like', "%{$c}%")->orWhere('nama_pemilik', 'like', "%{$c}%"))
                    ->orWhereHas('suratRekomendasi', fn ($s) => $s->where('nomor_surat', 'like', "%{$c}%"))));
        }

        $records = $q->get();

        // Preload batas kuota bulanan (1 query, tanpa N+1)
        $suratIds = $records->map(fn ($r) => $r->pengajuan?->surat_rekomendasi_id)->filter()->unique()->values()->all();
        $periodeStrings = array_map(fn (Carbon $m) => $m->toDateString(), $bulanList);
        $kuotaMap = [];
        if ($suratIds !== []) {
            $kuotaRows = KuotaRekomendasiBulanan::query()
                ->withoutGlobalScopes()
                ->whereIn('surat_rekomendasi_id', $suratIds)
                ->whereIn('periode', $periodeStrings)
                ->get(['surat_rekomendasi_id', 'periode', 'batas_liter']);

            foreach ($kuotaRows as $kr) {
                $pStr = Carbon::parse($kr->periode)->toDateString();
                $kuotaMap["{$kr->surat_rekomendasi_id}|{$pStr}"] = Dec::n($kr->batas_liter);
            }
        }

        $salesByDate = [];
        foreach ($records as $r) {
            if (! $r->pengajuan) {
                continue;
            }
            $tglStr = Carbon::parse($r->tanggal_penyaluran)->toDateString();
            $salesByDate[$tglStr][] = $r;
        }

        $singkatBulan = [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGUS', 'SEPT', 'OKT', 'NOV', 'DES'];
        $bulanan = [];
        $flatRows = [];
        $grandTerima = '0.00';
        $grandJual = '0.00';

        foreach ($bulanList as $m) {
            $periodeStr = $m->toDateString();
            $sheetTitle = $sameYear ? $singkatBulan[(int) $m->month] : $singkatBulan[(int) $m->month] . ' ' . $m->year;
            $periodeLabel = mb_strtoupper($m->translatedFormat('F Y'));

            $rowsBulan = [];
            $no = 0;
            $totalTerimaBulan = '0.00';
            $totalJualBulan = '0.00';

            for ($d = 1; $d <= $m->daysInMonth; $d++) {
                $tgl = $m->copy()->day($d);
                $tglStr = $tgl->toDateString();
                $tglLabel = $tgl->translatedFormat('j-M-y');
                $dayStock = $stockByDate[$tglStr] ?? ['stock_awal' => '0.00', 'penerimaan' => '0.00', 'stock_akhir' => '0.00'];
                $totalTerimaBulan = Dec::add($totalTerimaBulan, $dayStock['penerimaan']);

                $daySales = $salesByDate[$tglStr] ?? [];
                if ($daySales !== []) {
                    $entries = [];
                    foreach ($daySales as $r) {
                        $p = $r->pengajuan;
                        $kapal = $p->kapal;
                        $anggota = $p->anggota;
                        $surat = $p->suratRekomendasi;
                        $sid = (int) ($p->surat_rekomendasi_id ?? 0);
                        $kuotaSurat = $surat ? Dec::n($surat->kuota_bulanan_liter) : '0.00';
                        $volRekom = Dec::gt($kuotaSurat, 0)
                            ? $kuotaSurat
                            : ($sid > 0 ? ($kuotaMap["{$sid}|{$periodeStr}"] ?? ($surat ? $this->kuota->batas($surat) : '0.00')) : '0.00');

                        $entries[] = [
                            'id' => (int) $r->id,
                            'nama_kapal' => (string) ($kapal?->nama_kapal ?? ''),
                            'nama_pemilik' => (string) ($kapal?->nama_pemilik ?: ($anggota?->nama_lengkap ?? '')),
                            'gt' => ($kapal && $kapal->ukuran_gt !== null && (float) $kapal->ukuran_gt > 0) ? (float) $kapal->ukuran_gt : null,
                            'pk' => ($kapal && $kapal->daya_pk !== null && (float) $kapal->daya_pk > 0) ? (float) $kapal->daya_pk : null,
                            'rekomendasi' => self::bersihkanNomorRekom((string) ($surat?->nomor_surat ?? '')),
                            'vol_rekom' => Dec::gt($volRekom, 0) ? (float) $volRekom : null,
                            'penjualan' => (float) $r->liter_aktual,
                            'tujuan' => trim((string) ($p->tujuan ?? '')) ?: 'Melaut',
                        ];
                        $totalJualBulan = Dec::add($totalJualBulan, $r->liter_aktual);
                    }

                    usort($entries, fn (array $a, array $b) => [
                        mb_strtolower($a['nama_pemilik']),
                        mb_strtolower($a['nama_kapal']),
                        mb_strtolower($a['rekomendasi']),
                        (float) ($a['vol_rekom'] ?? 0),
                        (float) $a['penjualan'],
                        $a['id'],
                    ] <=> [
                        mb_strtolower($b['nama_pemilik']),
                        mb_strtolower($b['nama_kapal']),
                        mb_strtolower($b['rekomendasi']),
                        (float) ($b['vol_rekom'] ?? 0),
                        (float) $b['penjualan'],
                        $b['id'],
                    ]);

                    foreach ($entries as $idx => $entry) {
                        $isFirst = $idx === 0;
                        $rowsBulan[] = [
                            'no' => ++$no,
                            'is_lanjutan' => ! $isFirst,
                            'tanggal' => $isFirst ? $tglStr : null,
                            'tanggal_label' => $isFirst ? $tglLabel : '',
                            'nama_kapal' => $entry['nama_kapal'],
                            'nama_pemilik' => $entry['nama_pemilik'],
                            'gt' => $entry['gt'],
                            'pk' => $entry['pk'],
                            'rekomendasi' => $entry['rekomendasi'],
                            'vol_rekom' => $entry['vol_rekom'],
                            'stock_awal' => $isFirst ? (float) $dayStock['stock_awal'] : null,
                            'penerimaan' => ($isFirst && Dec::gt($dayStock['penerimaan'], 0)) ? (float) $dayStock['penerimaan'] : null,
                            'penjualan' => $entry['penjualan'],
                            'stock_akhir' => $isFirst ? (float) $dayStock['stock_akhir'] : null,
                            'tujuan' => $entry['tujuan'],
                        ];
                    }
                } else {
                    $adaTerima = Dec::gt($dayStock['penerimaan'], 0);
                    $rowsBulan[] = [
                        'no' => ++$no,
                        'is_lanjutan' => false,
                        'tanggal' => $tglStr,
                        'tanggal_label' => $tglLabel,
                        'nama_kapal' => '',
                        'nama_pemilik' => '',
                        'gt' => null,
                        'pk' => null,
                        'rekomendasi' => '',
                        'vol_rekom' => null,
                        'stock_awal' => $adaTerima ? (float) $dayStock['stock_awal'] : null,
                        'penerimaan' => $adaTerima ? (float) $dayStock['penerimaan'] : null,
                        'penjualan' => null,
                        'stock_akhir' => $adaTerima ? (float) $dayStock['stock_akhir'] : null,
                        'tujuan' => '',
                    ];
                }
            }

            $grandTerima = Dec::add($grandTerima, $totalTerimaBulan);
            $grandJual = Dec::add($grandJual, $totalJualBulan);

            $bulanan[] = [
                'periode_label' => $periodeLabel,
                'sheet_title' => $sheetTitle,
                'tanggal_tanda_tangan' => 'Pangkalan Brandan, ' . $m->copy()->endOfMonth()->translatedFormat('j F Y'),
                'rows' => $rowsBulan,
                'total_penerimaan' => (float) $totalTerimaBulan,
                'total_penjualan' => (float) $totalJualBulan,
            ];
        }

        return [
            'ringkasan' => [
                'Jumlah transaksi penjualan' => $records->count(),
                'Total Penerimaan (L)' => (float) $grandTerima,
                'Total Penjualan (L)' => (float) $grandJual,
            ],
            'headings' => ['No', 'Tanggal', 'Nama Kapal', 'Nama Pemilik', 'GT', 'PK', 'Rekomendasi', 'JML LTR Yang Direkomendasikan (Liter)', 'STOCK AWAL (Liter)', 'PENERIMAAN (Liter)', 'PENJUALAN (Liter)', 'STOCK AKHIR (Liter)', 'Tujuan'],
            'rows' => [],
            'bulanan' => $bulanan,
        ];
    }

    private function penjualan(array $f): array
    {
        $mulaiBulan = $f['_dari']->copy()->startOfMonth();
        $akhirBulanTarget = $f['_sampai']->copy()->startOfMonth();
        $bulanList = [];
        for ($cursor = $mulaiBulan->copy(); $cursor->lte($akhirBulanTarget) && count($bulanList) < 24; $cursor->addMonth()) {
            $bulanList[] = $cursor->copy();
        }
        if ($bulanList === []) {
            $bulanList[] = now()->startOfMonth();
        }

        $sameYear = $bulanList[0]->year === end($bulanList)->year;
        $rentangAwal = $bulanList[0]->copy()->startOfMonth()->startOfDay();
        $rentangAkhir = end($bulanList)->copy()->endOfMonth()->endOfDay();

        // Ambil transaksi penyaluran dalam rentang bulan (hanya kapal/surat yang bertransaksi pada bulan tersebut yang tampil)
        $q = PenyaluranBbm::query()
            ->with([
                'pengajuan',
                'pengajuan.anggota' => fn ($rel) => $rel->withTrashed(),
                'pengajuan.kapal' => fn ($rel) => $rel->withTrashed(),
                'pengajuan.suratRekomendasi' => fn ($rel) => $rel->withTrashed(),
            ])
            ->whereBetween('tanggal_penyaluran', [$rentangAwal, $rentangAkhir])
            ->orderBy('tanggal_penyaluran');

        if (! empty($f['anggota_id'])) {
            $q->whereHas('pengajuan', fn ($w) => $w->where('anggota_id', $f['anggota_id']));
        }
        if (! empty($f['kapal_id'])) {
            $q->whereHas('pengajuan', fn ($w) => $w->where('kapal_id', $f['kapal_id']));
        }
        if (! empty($f['cari'])) {
            $c = $f['cari'];
            $q->where(fn ($w) => $w->where('nomor_penyaluran', 'like', "%{$c}%")
                ->orWhereHas('pengajuan', fn ($p) => $p->where('nomor_pengajuan', 'like', "%{$c}%")
                    ->orWhereHas('anggota', fn ($a) => $a->where('nama_lengkap', 'like', "%{$c}%"))
                    ->orWhereHas('kapal', fn ($k) => $k->where('nama_kapal', 'like', "%{$c}%"))
                    ->orWhereHas('suratRekomendasi', fn ($s) => $s->where('nomor_surat', 'like', "%{$c}%"))));
        }

        $records = $q->get();

        // Kelompokkan transaksi per bulan -> per surat_rekomendasi_id (atau fallback anggota|kapal) -> per hari (1..31)
        $perBulan = [];
        $suratAktifUnik = [];
        foreach ($records as $r) {
            $p = $r->pengajuan;
            if (! $p) {
                continue;
            }
            $tgl = $r->tanggal_penyaluran;
            $ym = $tgl->format('Y-m');
            $key = $p->surat_rekomendasi_id
                ? 's:' . $p->surat_rekomendasi_id
                : 'p:' . ($p->anggota_id ?? 0) . '|' . ($p->kapal_id ?? 0);
            $day = (int) $tgl->day;
            $suratAktifUnik[$key] = true;

            if (! isset($perBulan[$ym][$key])) {
                $perBulan[$ym][$key] = [
                    'nama' => (string) ($p->anggota?->nama_lengkap ?? ''),
                    'nama_kapal' => (string) ($p->kapal?->nama_kapal ?? ''),
                    'no_rekom' => self::bersihkanNomorRekom((string) ($p->suratRekomendasi?->nomor_surat ?? '')) ?: '-',
                    'harian' => [],
                ];
            }

            $perBulan[$ym][$key]['harian'][$day] = Dec::add($perBulan[$ym][$key]['harian'][$day] ?? '0', $r->liter_aktual);
        }

        $bulanan = [];
        $flatRows = [];
        $grandTotal = '0.00';

        foreach ($bulanList as $m) {
            $ym = $m->format('Y-m');
            $bulanNama = $m->translatedFormat('F');
            $sheetTitle = $sameYear ? $bulanNama : $m->translatedFormat('F Y');

            $items = array_values($perBulan[$ym] ?? []);

            usort($items, fn (array $a, array $b) => [
                mb_strtolower($a['nama']),
                mb_strtolower($a['nama_kapal']),
                mb_strtolower($a['no_rekom']),
            ] <=> [
                mb_strtolower($b['nama']),
                mb_strtolower($b['nama_kapal']),
                mb_strtolower($b['no_rekom']),
            ]);

            $rowsBulan = [];
            $totalHarian = array_fill(1, 31, '0.00');
            $totalBulan = '0.00';

            foreach ($items as $item) {
                $harian = [];
                $totalRow = '0.00';
                for ($d = 1; $d <= 31; $d++) {
                    $val = $item['harian'][$d] ?? null;
                    if ($val !== null && Dec::gt($val, 0)) {
                        $harian[$d] = (float) $val;
                        $totalRow = Dec::add($totalRow, $val);
                        $totalHarian[$d] = Dec::add($totalHarian[$d], $val);
                    } else {
                        $harian[$d] = null;
                    }
                }

                if (! Dec::gt($totalRow, 0)) {
                    continue;
                }

                $totalBulan = Dec::add($totalBulan, $totalRow);
                $rowsBulan[] = [
                    'nama' => $item['nama'],
                    'nama_kapal' => $item['nama_kapal'],
                    'no_rekom' => $item['no_rekom'],
                    'harian' => $harian,
                    'total' => (float) $totalRow,
                ];
            }

            $grandTotal = Dec::add($grandTotal, $totalBulan);

            $bulanan[] = [
                'bulan_nama' => $bulanNama,
                'tahun' => (int) $m->year,
                'sheet_title' => $sheetTitle,
                'tanggal_tanda_tangan' => $m->copy()->endOfMonth()->translatedFormat('j F Y'),
                'rows' => $rowsBulan,
                'total_harian' => array_map(fn ($v) => (float) $v, $totalHarian),
                'total_penjualan' => (float) $totalBulan,
            ];
        }

        return [
            'ringkasan' => [
                'Total surat rekomendasi' => count($suratAktifUnik),
                'Jumlah transaksi penjualan' => $records->count(),
                'Total liter terjual' => (float) $grandTotal,
            ],
            'headings' => array_merge(['No', 'Nama', 'Nama Kapal', 'No. Rekomendasi'], array_map('strval', range(1, 31)), ['Total']),
            'rows' => [],
            'bulanan' => $bulanan,
        ];
    }

    /** Laporan Stock Produk Solar/Bio Solar harian per bulan (NO, TANGGAL, STOCK AWAL, PENERIMAAN, PENJUALAN, STOCK AKHIR). */
    private function stokSolar(array $f): array
    {
        $mulaiBulan = $f['_dari']->copy()->startOfMonth();
        $akhirBulanTarget = $f['_sampai']->copy()->startOfMonth();
        $bulanList = [];
        for ($cursor = $mulaiBulan->copy(); $cursor->lte($akhirBulanTarget) && count($bulanList) < 24; $cursor->addMonth()) {
            $bulanList[] = $cursor->copy();
        }
        if ($bulanList === []) {
            $bulanList[] = now()->startOfMonth();
        }

        $sameYear = $bulanList[0]->year === end($bulanList)->year;
        $rentangAwal = $bulanList[0]->copy()->startOfMonth()->startOfDay();
        $rentangAkhir = end($bulanList)->copy()->endOfMonth()->endOfDay();

        $saldoBerjalan = Dec::n(MutasiStok::query()->where('tanggal', '<', $rentangAwal)->sum('delta_fisik'));
        $mutasiPerHari = MutasiStok::query()
            ->whereBetween('tanggal', [$rentangAwal, $rentangAkhir])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get(['tanggal', 'jenis', 'delta_fisik'])
            ->groupBy(fn ($m) => Carbon::parse($m->tanggal)->toDateString());

        $singkatBulan = [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGUS', 'SEPT', 'OKT', 'NOV', 'DES'];
        $bulanan = [];
        $flatRows = [];
        $grandTerima = '0.00';
        $grandJual = '0.00';

        foreach ($bulanList as $m) {
            $sheetTitle = $sameYear ? $singkatBulan[(int) $m->month] : $singkatBulan[(int) $m->month] . ' ' . $m->year;
            $periodeLabel = $sameYear
                ? mb_strtoupper($m->translatedFormat('F'))
                : mb_strtoupper($m->translatedFormat('F Y'));

            $rowsBulan = [];
            $totalTerimaBulan = '0.00';
            $totalJualBulan = '0.00';

            for ($d = 1; $d <= $m->daysInMonth; $d++) {
                $tgl = $m->copy()->day($d);
                $tglStr = $tgl->toDateString();
                $tglLabel = $tgl->translatedFormat('j-M-y');
                $items = $mutasiPerHari->get($tglStr, collect());

                $terima = '0.00';
                $jual = '0.00';
                $koreksi = '0.00';

                foreach ($items as $mut) {
                    $jenisVal = $mut->jenis instanceof JenisMutasi ? $mut->jenis->value : (string) $mut->jenis;
                    if (in_array($jenisVal, [JenisMutasi::Penerimaan->value, JenisMutasi::PembatalanPenerimaan->value, JenisMutasi::SaldoAwal->value], true)) {
                        $terima = Dec::add($terima, $mut->delta_fisik);
                    } elseif ($jenisVal === JenisMutasi::Penyaluran->value) {
                        $jual = Dec::add($jual, Dec::sub('0', $mut->delta_fisik));
                    } elseif ($jenisVal === JenisMutasi::Koreksi->value) {
                        $koreksi = Dec::add($koreksi, $mut->delta_fisik);
                    }
                }

                $stockAwal = $saldoBerjalan;
                $stockAkhir = Dec::add(Dec::sub(Dec::add($stockAwal, $terima), $jual), $koreksi);
                $saldoBerjalan = $stockAkhir;

                $adaAktivitas = Dec::gt($terima, 0) || Dec::gt($jual, 0) || ! Dec::eq($koreksi, 0);
                $totalTerimaBulan = Dec::add($totalTerimaBulan, $terima);
                $totalJualBulan = Dec::add($totalJualBulan, $jual);

                $rowObj = [
                    'no' => $d,
                    'tanggal' => $tglStr,
                    'tanggal_label' => $tglLabel,
                    'stock_awal' => $adaAktivitas ? (float) $stockAwal : null,
                    'penerimaan' => Dec::gt($terima, 0) ? (float) $terima : null,
                    'penjualan' => Dec::gt($jual, 0) ? (float) $jual : null,
                    'stock_akhir' => $adaAktivitas ? (float) $stockAkhir : null,
                ];
                $rowsBulan[] = $rowObj;
                $flatRows[] = [
                    $rowObj['no'],
                    $rowObj['tanggal_label'],
                    $rowObj['stock_awal'],
                    $rowObj['penerimaan'],
                    $rowObj['penjualan'],
                    $rowObj['stock_akhir'],
                ];
            }

            $grandTerima = Dec::add($grandTerima, $totalTerimaBulan);
            $grandJual = Dec::add($grandJual, $totalJualBulan);

            $bulanan[] = [
                'periode_label' => $periodeLabel,
                'sheet_title' => $sheetTitle,
                'rows' => $rowsBulan,
                'total_penerimaan' => (float) $totalTerimaBulan,
                'total_penjualan' => (float) $totalJualBulan,
            ];
        }

        return [
            'ringkasan' => [
                'Total Penerimaan (L)' => (float) $grandTerima,
                'Total Penjualan (L)' => (float) $grandJual,
                'Stock Akhir (L)' => (float) $saldoBerjalan,
            ],
            'headings' => ['NO', 'TANGGAL', 'STOCK AWAL', 'PENERIMAAN', 'PENJUALAN', 'STOCK AKHIR'],
            'rows' => $flatRows,
            'bulanan' => $bulanan,
        ];
    }

    /** Data Realisasi Penerimaan dan Penjualan BBM tahunan (Januari – Desember). */
    private function realisasi(array $f): array
    {
        $tahun = (int) ($f['_dari']->year ?? now()->year);
        $awalTahun = Carbon::create($tahun, 1, 1)->startOfDay();
        $akhirTahun = Carbon::create($tahun, 12, 31)->endOfDay();

        $saldoBerjalan = Dec::n(MutasiStok::query()->where('tanggal', '<', $awalTahun)->sum('delta_fisik'));
        $saldoAwalTahun = $saldoBerjalan;

        $mutasi = MutasiStok::query()
            ->whereBetween('tanggal', [$awalTahun, $akhirTahun])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get(['tanggal', 'jenis', 'delta_fisik'])
            ->groupBy(fn ($m) => (int) Carbon::parse($m->tanggal)->month);

        $bulanRows = [];
        $flatRows = [];
        $totalTerima = '0.00';
        $totalJual = '0.00';

        for ($b = 1; $b <= 12; $b++) {
            $bulanNama = Carbon::create($tahun, $b, 1)->translatedFormat('F');
            $bulanan = $mutasi->get($b, collect());

            $terima = '0.00';
            $jual = '0.00';
            $koreksi = '0.00';

            foreach ($bulanan as $m) {
                $jenisVal = $m->jenis instanceof JenisMutasi ? $m->jenis->value : (string) $m->jenis;
                if (in_array($jenisVal, [JenisMutasi::Penerimaan->value, JenisMutasi::PembatalanPenerimaan->value, JenisMutasi::SaldoAwal->value], true)) {
                    $terima = Dec::add($terima, $m->delta_fisik);
                } elseif ($jenisVal === JenisMutasi::Penyaluran->value) {
                    $jual = Dec::add($jual, Dec::sub('0', $m->delta_fisik));
                } elseif ($jenisVal === JenisMutasi::Koreksi->value) {
                    $koreksi = Dec::add($koreksi, $m->delta_fisik);
                }
            }

            $saldoAwal = $saldoBerjalan;
            $saldoAkhirAdm = Dec::sub(Dec::add($saldoAwal, $terima), $jual);
            $saldoAkhirFisik = Dec::add($saldoAkhirAdm, $koreksi);
            $saldoBerjalan = $saldoAkhirFisik;

            $totalTerima = Dec::add($totalTerima, $terima);
            $totalJual = Dec::add($totalJual, $jual);

            $terimaVal = Dec::gt($terima, 0) ? (float) $terima : null;
            $jualVal = Dec::gt($jual, 0) ? (float) $jual : null;

            $bulanRows[] = [
                'bulan' => $bulanNama,
                'saldo_awal' => (float) $saldoAwal,
                'penerimaan' => $terimaVal,
                'penjualan' => $jualVal,
                'koreksi' => (float) $koreksi,
                'saldo_akhir_adm' => (float) $saldoAkhirAdm,
                'saldo_akhir_fisik' => (float) $saldoAkhirFisik,
            ];

            $flatRows[] = [
                $bulanNama,
                (float) $saldoAwal,
                $terimaVal,
                $jualVal,
                (float) $saldoAkhirAdm,
                (float) $saldoAkhirFisik,
            ];
        }

        $saldoAkhirAdmTahun = Dec::sub(Dec::add($saldoAwalTahun, $totalTerima), $totalJual);

        return [
            'ringkasan' => [
                'Periode' => "Januari – Desember {$tahun}",
                'Total Penerimaan (L)' => (float) $totalTerima,
                'Total Penjualan (L)' => (float) $totalJual,
                'Saldo Akhir Fisik (L)' => (float) $saldoBerjalan,
            ],
            'headings' => ['Bulan', 'Saldo Awal', 'Penerimaan', 'Penjualan', 'Saldo Akhir Administrasi', 'Saldo Akhir Pemeriksaan Fisik'],
            'rows' => $flatRows,
            'realisasi' => [
                'tahun' => $tahun,
                'bulan' => $bulanRows,
                'total' => [
                    'saldo_awal' => (float) $saldoAwalTahun,
                    'penerimaan' => (float) $totalTerima,
                    'penjualan' => (float) $totalJual,
                    'saldo_akhir_adm' => (float) $saldoAkhirAdmTahun,
                    'saldo_akhir_fisik' => (float) $saldoBerjalan,
                ],
            ],
        ];
    }

    private function keuangan(array $f): array
    {
        $dariTgl = $f['_dari']->toDateString();
        $sampaiTgl = $f['_sampai']->toDateString();
        $tagihan = Tagihan::query()->where('status', '!=', StatusTagihan::Dibatalkan->value);
        if ($tagihan->getConnection()->getDriverName() === 'sqlite') {
            $tagihan->whereDate('tanggal_tagihan', '>=', $dariTgl)->whereDate('tanggal_tagihan', '<=', $sampaiTgl);
        } else {
            $tagihan->where('tanggal_tagihan', '>=', $dariTgl)->where('tanggal_tagihan', '<=', $sampaiTgl);
        }

        $bayar = Pembayaran::query()->where('pembayaran.status', StatusPembayaranCatatan::Sah->value)
            ->whereBetween('pembayaran.tanggal_pembayaran', [$f['_dari'], $f['_sampai']]);

        if (! empty($f['anggota_id'])) {
            $tagihan->where('anggota_id', $f['anggota_id']);
            $bayar->whereHas('tagihan', fn ($t) => $t->where('anggota_id', $f['anggota_id']));
        }
        if (! empty($f['kapal_id'])) {
            $tagihan->whereHas('pengajuan', fn ($p) => $p->where('kapal_id', $f['kapal_id']));
            $bayar->whereHas('tagihan.pengajuan', fn ($p) => $p->where('kapal_id', $f['kapal_id']));
        }
        if (! empty($f['status'])) {
            $tagihan->where('status', $f['status']);
            $bayar->whereHas('tagihan', fn ($t) => $t->where('status', $f['status']));
        }
        if (! empty($f['cari'])) {
            $c = $f['cari'];
            $tagihan->where(fn ($w) => $w->where('nomor_tagihan', 'like', "%{$c}%")
                ->orWhereHas('anggota', fn ($a) => $a->where('nama_lengkap', 'like', "%{$c}%")));
            $bayar->where(fn ($w) => $w->where('nomor_pembayaran', 'like', "%{$c}%")
                ->orWhereHas('tagihan', fn ($t) => $t->where('nomor_tagihan', 'like', "%{$c}%")
                    ->orWhereHas('anggota', fn ($a) => $a->where('nama_lengkap', 'like', "%{$c}%"))));
        }

        $ringkasanTagihan = (clone $tagihan)
            ->selectRaw(
                'COALESCE(SUM(total), 0) as total_tagihan, COALESCE(SUM(CASE WHEN status = ? THEN total ELSE 0 END), 0) as belum_lunas, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as transaksi_lunas',
                [StatusTagihan::BelumDibayar->value, StatusTagihan::Lunas->value]
            )
            ->first();

        $totalTagihan = Dec::n($ringkasanTagihan->total_tagihan ?? 0);
        $belumLunas = Dec::n($ringkasanTagihan->belum_lunas ?? 0);
        $transaksiLunas = (int) ($ringkasanTagihan->transaksi_lunas ?? 0);

        $bayarAgg = (clone $bayar)
            ->leftJoin('tagihan as t_ref', 't_ref.id', '=', 'pembayaran.tagihan_id')
            ->selectRaw('COALESCE(SUM(pembayaran.jumlah), 0) as bruto, COALESCE(SUM(CASE WHEN t_ref.total IS NOT NULL AND pembayaran.jumlah > t_ref.total THEN pembayaran.jumlah - t_ref.total ELSE 0 END), 0) as refund_selesai')
            ->first();

        $totalBayar = Dec::n($bayarAgg->bruto ?? 0);
        $refundSelesai = Dec::n($bayarAgg->refund_selesai ?? 0);

        $harian = (clone $bayar)->selectRaw('DATE(pembayaran.tanggal_pembayaran) as hari, COUNT(*) as n, SUM(pembayaran.jumlah) as total')
            ->groupBy(DB::raw('DATE(pembayaran.tanggal_pembayaran)'))->orderBy('hari')->get();

        $ringkasan = [
            'Total tagihan' => Format::rupiah($totalTagihan),
            'Total pembayaran diterima' => Format::rupiah($totalBayar),
            'Tagihan belum lunas' => Format::rupiah($belumLunas),
            'Transaksi lunas' => $transaksiLunas,
        ];

        if (Dec::gt($refundSelesai, 0)) {
            $ringkasan['Total refund selesai'] = Format::rupiah($refundSelesai);
            $ringkasan['Pemasukan neto'] = Format::rupiah(Dec::max0(Dec::sub($totalBayar, $refundSelesai)));
        }

        $refundTertunda = Dec::n(
            PenyesuaianTagihan::query()
                ->where('diterapkan', false)
                ->whereIn('tagihan_id', (clone $tagihan)->select('id'))
                ->sum('selisih')
        );
        if (Dec::lt($refundTertunda, 0)) {
            $ringkasan['Selisih pengembalian tertunda'] = Format::rupiah(Dec::sub('0', $refundTertunda));
        }

        return [
            'ringkasan' => $ringkasan,
            'headings' => ['Tanggal', 'Jumlah Pembayaran', 'Pemasukan (Rp)'],
            'rows' => $harian->map(fn ($r) => [Carbon::parse($r->hari)->format('d/m/Y'), (int) $r->n, (float) $r->total])->all(),
        ];
    }

    /** Hapus akhiran internal `#01`, `#02`, dst. yang dipakai untuk membedakan multi-kapal dengan nomor rekomendasi sama. */
    private static function bersihkanNomorRekom(?string $nomor): string
    {
        $teks = trim((string) $nomor);
        if ($teks === '') {
            return '';
        }

        return (string) preg_replace('/#\d+$/', '', $teks);
    }
}
