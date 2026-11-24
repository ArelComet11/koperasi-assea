<?php

namespace App\Services;

use App\Enums\StatusAktif;
use App\Enums\StatusPembayaranCatatan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTagihan;
use App\Models\Kapal;
use App\Models\KuotaRekomendasiBulanan;
use App\Models\Pembayaran;
use App\Models\PengajuanPembelian;
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
        'penjualan' => 'Laporan Penjualan',
        'transaksi' => 'Laporan Transaksi',
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
            return array_intersect_key(self::JENIS, array_flip(['transaksi', 'keuangan']));
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
            'penyaluran' => $this->penyaluran($f),
            'transaksi' => $this->transaksi($f),
            'keuangan' => $this->keuangan($f),
            default => throw new \InvalidArgumentException('Jenis laporan tidak dikenal.'),
        };

        $adaPeriode = in_array($jenis, ['surat', 'penyaluran', 'transaksi', 'keuangan'], true);
        $periodeTeks = $adaPeriode ? $dari->translatedFormat('d F Y') . ' s.d. ' . $sampai->translatedFormat('d F Y') : 'Per ' . now()->translatedFormat('d F Y');
        if ($jenis === 'surat' && $dari->isSameMonth($sampai)) {
            $periodeTeks = $dari->translatedFormat('F Y');
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
        $q = Kapal::query()->with('anggota')->orderBy('kode_kapal');
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
            'headings' => ['Kode', 'Nama Kapal', 'Pemilik (Anggota)', 'No. Registrasi', 'Jenis', 'Status', 'Total BBM Diterima (L)'],
            'rows' => $data->map(fn ($k) => [$k->kode_kapal, $k->nama_kapal, $k->anggota?->nama_lengkap, $k->no_registrasi ?: '-', $k->jenis_kapal ?: '-', $k->status->getLabel(), (float) ($total[$k->id] ?? 0)])->all(),
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
            }
        }

        $bulanan = [];
        $flatRows = [];
        $grandVolRekom = '0.00';
        $grandPenyaluran = '0.00';
        $grandVsRekom = '0.00';
        $singleMonth = count($bulanList) === 1;

        foreach ($bulanList as $m) {
            $ym = $m->format('Y-m');
            $periodeStr = $m->toDateString();
            $awalBulan = $m->copy()->startOfMonth()->startOfDay();
            $akhirBulan = $m->copy()->endOfMonth()->endOfDay();
            $bulanLabel = $sameYear ? $m->translatedFormat('F') : $m->translatedFormat('F Y');

            $rowsBulan = [];
            $totalHarian = array_fill(1, 31, '0.00');
            $totalVolBulan = '0.00';
            $totalPenyaluranBulan = '0.00';
            $totalVsBulan = '0.00';

            foreach ($data as $s) {
                $sid = (int) $s->id;
                $adaSalur = ! empty($harianMap[$ym][$sid]);
                $berlakuDiBulan = $s->berlaku_mulai->copy()->startOfDay()->lte($akhirBulan)
                    && $s->berlaku_sampai->copy()->endOfDay()->gte($awalBulan);

                if (! $adaSalur && ! $berlakuDiBulan && ! ($singleMonth && ! empty($f['status']))) {
                    continue;
                }

                $volRekom = $kuotaMap["{$sid}|{$periodeStr}"] ?? $this->kuota->batas($s);
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

                $rowObj = [
                    'nama' => (string) ($s->anggota?->nama_lengkap ?? ''),
                    'nama_kapal' => (string) ($s->kapal?->nama_kapal ?? ''),
                    'no_rekom' => (string) $s->nomor_surat,
                    'vol_rekom' => (float) $volRekom,
                    'harian' => $harian,
                    'total' => (float) $totalSurat,
                    'total_vs_rekom' => (float) $vsRekom,
                ];
                $rowsBulan[] = $rowObj;

                $flatRows[] = array_merge(
                    [count($rowsBulan), $rowObj['nama'], $rowObj['nama_kapal'], $rowObj['no_rekom'], $rowObj['vol_rekom']],
                    array_values($harian),
                    [$rowObj['total'], $rowObj['total_vs_rekom']]
                );
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
                'Total surat' => $data->count(),
                'Total Vol Rekom (L)' => (float) $grandVolRekom,
                'Total Penyaluran (L)' => (float) $grandPenyaluran,
                'Total Vs Rekom (L)' => (float) $grandVsRekom,
            ],
            'headings' => array_merge(['NO', 'Nama', 'Nama Kapal', 'No Rekom', 'Vol Rekom'], array_map('strval', range(1, 31)), ['Total', 'Total Vs Rekom']),
            'rows' => $flatRows,
            'bulanan' => $bulanan,
        ];
    }

    private function penyaluran(array $f): array
    {
        $q = PenyaluranBbm::query()->with(['pengajuan.anggota', 'pengajuan.kapal', 'pengajuan.suratRekomendasi', 'petugas'])
            ->whereBetween('tanggal_penyaluran', [$f['_dari'], $f['_sampai']])->orderBy('tanggal_penyaluran');
        if (! empty($f['anggota_id'])) $q->whereHas('pengajuan', fn ($w) => $w->where('anggota_id', $f['anggota_id']));
        if (! empty($f['kapal_id'])) $q->whereHas('pengajuan', fn ($w) => $w->where('kapal_id', $f['kapal_id']));
        if (! empty($f['cari'])) {
            $c = $f['cari'];
            $q->where(fn ($w) => $w->where('nomor_penyaluran', 'like', "%{$c}%")
                ->orWhereHas('pengajuan', fn ($p) => $p->where('nomor_pengajuan', 'like', "%{$c}%")
                    ->orWhereHas('anggota', fn ($a) => $a->where('nama_lengkap', 'like', "%{$c}%"))
                    ->orWhereHas('kapal', fn ($k) => $k->where('nama_kapal', 'like', "%{$c}%"))));
        }
        $data = $q->get();

        return [
            'ringkasan' => ['Jumlah penyaluran' => $data->count(), 'Total liter disalurkan' => (float) $data->sum('liter_aktual')],
            'headings' => ['No. Penyaluran', 'Tanggal', 'No. Pengajuan', 'Anggota', 'Kapal', 'No. Surat', 'Disetujui (L)', 'Aktual (L)', 'Petugas'],
            'rows' => $data->map(fn ($r) => [$r->nomor_penyaluran, $r->tanggal_penyaluran->format('d/m/Y H:i'), $r->pengajuan->nomor_pengajuan, $r->pengajuan->anggota?->nama_lengkap,
                $r->pengajuan->kapal?->nama_kapal, $r->pengajuan->suratRekomendasi?->nomor_surat, (float) $r->liter_disetujui, (float) $r->liter_aktual, $r->petugas?->name])->all(),
        ];
    }

    private function transaksi(array $f): array
    {
        $q = PengajuanPembelian::query()->with(['anggota', 'kapal', 'suratRekomendasi'])
            ->whereBetween('created_at', [$f['_dari'], $f['_sampai']])->orderBy('created_at');
        if (! empty($f['anggota_id'])) $q->where('anggota_id', $f['anggota_id']);
        if (! empty($f['kapal_id'])) $q->where('kapal_id', $f['kapal_id']);
        if (! empty($f['status'])) $q->where('status', $f['status']);
        if (! empty($f['cari'])) {
            $c = $f['cari'];
            $q->where(fn ($w) => $w->where('nomor_pengajuan', 'like', "%{$c}%")
                ->orWhereHas('anggota', fn ($a) => $a->where('nama_lengkap', 'like', "%{$c}%"))
                ->orWhereHas('kapal', fn ($k) => $k->where('nama_kapal', 'like', "%{$c}%")));
        }
        $data = $q->get();

        $selesai = $data->where('status', StatusPengajuan::Selesai);

        return [
            'ringkasan' => [
                'Jumlah transaksi' => $data->count(),
                'Selesai' => $selesai->count(),
                'Total tagihan transaksi selesai' => Format::rupiah($selesai->sum('total_tagihan')),
            ],
            'headings' => ['No. Pengajuan', 'Tanggal', 'Anggota', 'Kapal', 'Liter Diminta', 'Harga/Liter', 'Total Tagihan', 'Status'],
            'rows' => $data->map(fn ($p) => [$p->nomor_pengajuan, $p->created_at->format('d/m/Y'), $p->anggota?->nama_lengkap, $p->kapal?->nama_kapal,
                (float) $p->liter_diminta, (float) ($p->harga_per_liter ?? $p->harga_estimasi_per_liter), (float) ($p->total_tagihan ?? $p->total_estimasi), $p->statusTampil()[0]])->all(),
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
}
