<?php

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Enums\StatusAktif;
use App\Enums\StatusPenerimaan;
use App\Enums\StatusPembayaranCatatan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTagihan;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\MutasiStok;
use App\Models\PenerimaanBbm;
use App\Models\Pembayaran;
use App\Models\PengajuanPembelian;
use App\Models\PenyaluranBbm;
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
        'anggota' => 'Laporan Anggota',
        'kapal' => 'Laporan Kapal',
        'surat' => 'Laporan Surat Rekomendasi',
        'penerimaan' => 'Laporan Penerimaan BBM',
        'penyaluran' => 'Laporan Penyaluran BBM',
        'persediaan' => 'Laporan Persediaan',
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
            'anggota' => $this->anggota($f),
            'kapal' => $this->kapal($f),
            'surat' => $this->surat($f),
            'penerimaan' => $this->penerimaan($f),
            'penyaluran' => $this->penyaluran($f),
            'persediaan' => $this->persediaan($f),
            'transaksi' => $this->transaksi($f),
            'keuangan' => $this->keuangan($f),
            default => throw new \InvalidArgumentException('Jenis laporan tidak dikenal.'),
        };

        $adaPeriode = in_array($jenis, ['penerimaan', 'penyaluran', 'persediaan', 'transaksi', 'keuangan'], true);

        return [
            'judul' => self::JENIS[$jenis],
            'periode' => $adaPeriode ? $dari->translatedFormat('d F Y') . ' s.d. ' . $sampai->translatedFormat('d F Y') : 'Per ' . now()->translatedFormat('d F Y'),
            'ringkasan' => $hasil['ringkasan'] ?? [],
            'headings' => $hasil['headings'],
            'rows' => $hasil['rows'],
        ];
    }

    private function rentang(array $f): array
    {
        $dari = ! empty($f['dari']) ? Carbon::parse($f['dari'])->startOfDay() : now()->startOfMonth();
        $sampai = ! empty($f['sampai']) ? Carbon::parse($f['sampai'])->endOfDay() : now()->endOfDay();

        return [$dari, $sampai];
    }

    private function anggota(array $f): array
    {
        $q = Anggota::query()->withCount('kapal')->orderBy('kode_anggota');
        if (! empty($f['status'])) $q->where('status', $f['status']);
        if (! empty($f['anggota_id'])) $q->whereKey($f['anggota_id']);
        if (! empty($f['cari'])) $q->where(fn ($w) => $w->where('nama_lengkap', 'like', "%{$f['cari']}%")->orWhere('kode_anggota', 'like', "%{$f['cari']}%"));
        $data = $q->get();

        return [
            'ringkasan' => ['Total anggota' => $data->count(), 'Aktif' => $data->where('status', StatusAktif::Aktif)->count(), 'Nonaktif' => $data->where('status', StatusAktif::Nonaktif)->count()],
            'headings' => ['Kode', 'Nama', 'Telepon', 'Status', 'Jumlah Kapal', 'Tanggal Bergabung'],
            'rows' => $data->map(fn ($a) => [$a->kode_anggota, $a->nama_lengkap, $a->telepon, $a->status->getLabel(), $a->kapal_count, $a->tanggal_bergabung?->format('d/m/Y')])->all(),
        ];
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
            'ringkasan' => ['Total kapal' => $data->count()],
            'headings' => ['Kode', 'Nama Kapal', 'Pemilik (Anggota)', 'No. Registrasi', 'Jenis', 'Status', 'Total BBM Diterima (L)'],
            'rows' => $data->map(fn ($k) => [$k->kode_kapal, $k->nama_kapal, $k->anggota?->nama_lengkap, $k->no_registrasi, $k->jenis_kapal, $k->status->getLabel(), (float) ($total[$k->id] ?? 0)])->all(),
        ];
    }

    private function surat(array $f): array
    {
        $q = SuratRekomendasi::query()->with(['anggota', 'kapal'])->orderBy('nomor_surat');
        if (! empty($f['anggota_id'])) $q->where('anggota_id', $f['anggota_id']);
        if (! empty($f['kapal_id'])) $q->where('kapal_id', $f['kapal_id']);
        $data = $q->get();
        if (! empty($f['status'])) $data = $data->filter(fn ($s) => $s->status_tampil === $f['status'])->values();

        return [
            'ringkasan' => [
                'Total surat' => $data->count(),
                'Aktif' => $data->where('status_tampil', 'aktif')->count(),
                'Kedaluwarsa' => $data->where('status_tampil', 'kedaluwarsa')->count(),
            ],
            'headings' => ['Nomor Surat', 'Anggota', 'Kapal', 'Berlaku Mulai', 'Berlaku Sampai', 'Status', 'Batas Kuota/Bulan (L)', 'Terpakai+Alokasi Bulan Ini (L)', 'Sisa Kuota (L)'],
            'rows' => $data->map(function ($s) {
                $batas = $this->kuota->batas($s);
                $sisa = $this->kuota->sisa($s);

                return [$s->nomor_surat, $s->anggota?->nama_lengkap, $s->kapal?->nama_kapal, $s->berlaku_mulai->format('d/m/Y'), $s->berlaku_sampai->format('d/m/Y'),
                    ucfirst($s->status_tampil), (float) $batas, (float) Dec::sub($batas, $sisa), (float) $sisa];
            })->all(),
        ];
    }

    private function penerimaan(array $f): array
    {
        $q = PenerimaanBbm::query()->where('status', StatusPenerimaan::Terkonfirmasi->value)
            ->whereDate('tanggal_penerimaan', '>=', $f['_dari']->toDateString())->whereDate('tanggal_penerimaan', '<=', $f['_sampai']->toDateString())
            ->orderBy('tanggal_penerimaan')->orderBy('id');
        if (! empty($f['cari'])) $q->where('nama_pemasok', 'like', "%{$f['cari']}%");
        $data = $q->get();

        $ringkasan = ['Total penerimaan (liter)' => (float) $data->sum('liter')];
        foreach ($data->groupBy(fn ($r) => $r->periode->translatedFormat('F Y')) as $bulan => $g) {
            $ringkasan["Total {$bulan} (liter)"] = (float) $g->sum('liter');
        }

        return [
            'ringkasan' => $ringkasan,
            'headings' => ['No. Penerimaan', 'Tanggal', 'Pemasok / Sumber', 'Liter', 'Harga/Liter', 'Total Nilai', 'No. Dokumen', 'Override Pasokan'],
            'rows' => $data->map(fn ($r) => [$r->nomor_penerimaan, $r->tanggal_penerimaan->format('d/m/Y'), $r->nama_pemasok, (float) $r->liter,
                $r->harga_per_liter !== null ? (float) $r->harga_per_liter : null, $r->total_nilai !== null ? (float) $r->total_nilai : null, $r->nomor_dokumen, $r->override_pasokan ? 'Ya' : 'Tidak'])->all(),
        ];
    }

    private function penyaluran(array $f): array
    {
        $q = PenyaluranBbm::query()->with(['pengajuan.anggota', 'pengajuan.kapal', 'pengajuan.suratRekomendasi', 'petugas'])
            ->whereBetween('tanggal_penyaluran', [$f['_dari'], $f['_sampai']])->orderBy('tanggal_penyaluran');
        if (! empty($f['anggota_id'])) $q->whereHas('pengajuan', fn ($w) => $w->where('anggota_id', $f['anggota_id']));
        if (! empty($f['kapal_id'])) $q->whereHas('pengajuan', fn ($w) => $w->where('kapal_id', $f['kapal_id']));
        $data = $q->get();

        return [
            'ringkasan' => ['Jumlah penyaluran' => $data->count(), 'Total liter disalurkan' => (float) $data->sum('liter_aktual')],
            'headings' => ['No. Penyaluran', 'Tanggal', 'No. Pengajuan', 'Anggota', 'Kapal', 'No. Surat', 'Disetujui (L)', 'Aktual (L)', 'Petugas'],
            'rows' => $data->map(fn ($r) => [$r->nomor_penyaluran, $r->tanggal_penyaluran->format('d/m/Y H:i'), $r->pengajuan->nomor_pengajuan, $r->pengajuan->anggota?->nama_lengkap,
                $r->pengajuan->kapal?->nama_kapal, $r->pengajuan->suratRekomendasi?->nomor_surat, (float) $r->liter_disetujui, (float) $r->liter_aktual, $r->petugas?->name])->all(),
        ];
    }

    private function persediaan(array $f): array
    {
        $dari = $f['_dari'];
        $sampai = $f['_sampai'];

        $sebelum = MutasiStok::query()->where('tanggal', '<', $dari)->orderByDesc('tanggal')->orderByDesc('id')->first();
        $akhir = MutasiStok::query()->where('tanggal', '<=', $sampai)->orderByDesc('tanggal')->orderByDesc('id')->first();
        $dalam = MutasiStok::query()->whereBetween('tanggal', [$dari, $sampai]);
        $jumlah = fn (array $j) => Dec::n((clone $dalam)->whereIn('jenis', $j)->sum('delta_fisik'));

        $awal = Dec::n($sebelum?->saldo_fisik);
        $terima = $jumlah([JenisMutasi::Penerimaan->value, JenisMutasi::PembatalanPenerimaan->value, JenisMutasi::SaldoAwal->value]);
        $salur = Dec::sub('0', $jumlah([JenisMutasi::Penyaluran->value]));
        $koreksi = $jumlah([JenisMutasi::Koreksi->value]);
        $fisikAkhir = Dec::n($akhir?->saldo_fisik);
        $alokasi = Dec::n($akhir?->saldo_alokasi);

        return [
            'headings' => ['Keterangan', 'Liter'],
            'rows' => [
                ['Stok awal periode', (float) $awal],
                ['Penerimaan (termasuk saldo awal, neto pembatalan)', (float) $terima],
                ['Penyaluran aktual', (float) $salur],
                ['Koreksi', (float) $koreksi],
                ['Stok fisik akhir', (float) $fisikAkhir],
                ['Stok dialokasikan (akhir)', (float) $alokasi],
                ['Stok tersedia (akhir)', (float) Dec::sub($fisikAkhir, $alokasi)],
            ],
        ];
    }

    private function transaksi(array $f): array
    {
        $q = PengajuanPembelian::query()->with(['anggota', 'kapal', 'suratRekomendasi'])
            ->whereBetween('created_at', [$f['_dari'], $f['_sampai']])->orderBy('created_at');
        if (! empty($f['anggota_id'])) $q->where('anggota_id', $f['anggota_id']);
        if (! empty($f['kapal_id'])) $q->where('kapal_id', $f['kapal_id']);
        if (! empty($f['status'])) $q->where('status', $f['status']);
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
        $tagihan = Tagihan::query()->where('status', '!=', StatusTagihan::Dibatalkan->value)
            ->whereDate('tanggal_tagihan', '>=', $f['_dari']->toDateString())->whereDate('tanggal_tagihan', '<=', $f['_sampai']->toDateString());
        $bayar = Pembayaran::query()->where('status', StatusPembayaranCatatan::Sah->value)->whereBetween('tanggal_pembayaran', [$f['_dari'], $f['_sampai']]);

        $totalTagihan = Dec::n((clone $tagihan)->sum('total'));
        $belumLunas = Dec::n((clone $tagihan)->where('status', StatusTagihan::BelumDibayar->value)->sum('total'));
        $totalBayar = Dec::n((clone $bayar)->sum('jumlah'));

        $harian = (clone $bayar)->selectRaw('DATE(tanggal_pembayaran) as hari, COUNT(*) as n, SUM(jumlah) as total')
            ->groupBy(DB::raw('DATE(tanggal_pembayaran)'))->orderBy('hari')->get();

        return [
            'ringkasan' => [
                'Total tagihan' => Format::rupiah($totalTagihan),
                'Total pembayaran diterima' => Format::rupiah($totalBayar),
                'Tagihan belum lunas' => Format::rupiah($belumLunas),
                'Transaksi lunas' => (clone $tagihan)->where('status', StatusTagihan::Lunas->value)->count(),
            ],
            'headings' => ['Tanggal', 'Jumlah Pembayaran', 'Pemasukan (Rp)'],
            'rows' => $harian->map(fn ($r) => [Carbon::parse($r->hari)->format('d/m/Y'), (int) $r->n, (float) $r->total])->all(),
        ];
    }
}
