<?php

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Enums\StatusAktif;
use App\Enums\StatusAlokasi;
use App\Enums\StatusJadwal;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenyaluran;
use App\Enums\StatusTagihan;
use App\Exceptions\BisnisException;
use App\Models\AlokasiBbm;
use App\Models\Anggota;
use App\Models\JadwalPembelian;
use App\Models\Kapal;
use App\Models\PengajuanPembelian;
use App\Models\RiwayatStatusPengajuan;
use App\Models\SuratRekomendasi;
use App\Models\Tagihan;
use App\Models\User;
use App\Support\Dec;
use App\Support\Format;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PengajuanService
{
    public function __construct(
        private StokService $stok,
        private KuotaService $kuota,
        private PasokanService $pasokan,
        private HargaService $harga,
        private NomorService $nomor,
    ) {
    }

    /** Pengajuan oleh anggota. Validasi awal; kuota & stok belum dikunci sampai disetujui. */
    public function ajukan(Anggota $anggota, array $data): PengajuanPembelian
    {
        $liter = Dec::n($data['liter_diminta'] ?? 0);
        if (Dec::lte($liter, 0)) {
            throw new BisnisException('Jumlah liter harus lebih dari 0.');
        }

        $tanggal = ! empty($data['tanggal_rencana']) ? Carbon::parse($data['tanggal_rencana'])->startOfDay() : today();
        if ($tanggal->lt(today())) {
            throw new BisnisException('Tanggal rencana pembelian tidak boleh di masa lalu.');
        }

        return DB::transaction(function () use ($anggota, $data, $liter, $tanggal) {
            $anggota = Anggota::query()->findOrFail($anggota->id);
            if (! $anggota->isAktif() || ! $anggota->user?->is_active) {
                throw new BisnisException('Akun anggota tidak aktif.');
            }

            $kapal = Kapal::withoutGlobalScopes()->where('anggota_id', $anggota->id)->find($data['kapal_id'] ?? 0);
            if (! $kapal) {
                throw new BisnisException('Kapal tidak ditemukan atau bukan milik Anda.');
            }
            if ($kapal->status !== StatusAktif::Aktif) {
                throw new BisnisException('Kapal ini berstatus nonaktif.');
            }

            $surat = SuratRekomendasi::withoutGlobalScopes()
                ->where('anggota_id', $anggota->id)->where('kapal_id', $kapal->id)
                ->find($data['surat_rekomendasi_id'] ?? 0);
            if (! $surat) {
                throw new BisnisException('Surat rekomendasi tidak ditemukan, bukan milik Anda, atau tidak sesuai dengan kapal yang dipilih.');
            }
            if (! $surat->berlakuPada(today())) {
                throw new BisnisException('Surat rekomendasi tidak aktif atau sudah di luar masa berlaku.');
            }
            if (! $surat->berlakuPada($tanggal)) {
                throw new BisnisException('Tanggal rencana pembelian berada di luar masa berlaku surat rekomendasi.');
            }

            $sisaKuota = $this->kuota->sisa($surat);
            if (Dec::gt($liter, $sisaKuota)) {
                throw new BisnisException('Jumlah melebihi sisa kuota bulan ini untuk surat rekomendasi tersebut (sisa ' . Format::liter($sisaKuota) . ').');
            }
            if (Dec::gt($liter, $this->stok->tersedia())) {
                throw new BisnisException('Stok BBM yang tersedia saat ini belum mencukupi untuk jumlah yang diminta.');
            }
            if (Dec::gt($liter, $this->pasokan->ringkasan()['sisa_alokasi'])) {
                throw new BisnisException('Batas pasokan BBM bulan ini tidak mencukupi untuk jumlah yang diminta.');
            }

            $harga = $this->harga->aktif();

            $p = new PengajuanPembelian();
            $p->forceFill([
                'nomor_pengajuan' => $this->nomor->terbitkan('pengajuan'),
                'anggota_id' => $anggota->id,
                'kapal_id' => $kapal->id,
                'surat_rekomendasi_id' => $surat->id,
                'liter_diminta' => $liter,
                'harga_estimasi_per_liter' => $harga,
                'total_estimasi' => Dec::mul($liter, $harga),
                'tanggal_rencana' => $tanggal->toDateString(),
                'catatan' => $data['catatan'] ?? null,
                'status' => StatusPengajuan::MenungguPersetujuan,
                'status_pembayaran' => StatusPembayaran::BelumDibayar,
                'status_penyaluran' => StatusPenyaluran::Belum,
            ])->save();

            $this->catatStatus($p, null, StatusPengajuan::MenungguPersetujuan, null, auth()->user());
            AuditService::catat('pengajuan.dibuat', $p, null, ['nomor' => $p->nomor_pengajuan, 'liter' => $liter], null);

            return $p;
        });
    }

    public function setujui(PengajuanPembelian $pengajuan, User $user): PengajuanPembelian
    {
        Otorisasi::pastikan($user, 'pengajuan.approve');

        return DB::transaction(function () use ($pengajuan, $user) {
            // Urutan kunci selalu: pengajuan -> kuota surat -> pasokan -> stok (-> tagihan). Jangan diubah.
            $p = PengajuanPembelian::withoutGlobalScopes()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($p->status !== StatusPengajuan::MenungguPersetujuan) {
                throw new BisnisException('Pengajuan ini sudah diproses (status: ' . $p->status->getLabel() . ').');
            }

            $anggota = Anggota::query()->findOrFail($p->anggota_id);
            if (! $anggota->isAktif() || ! $anggota->user?->is_active) {
                throw new BisnisException('Anggota tidak aktif.');
            }

            $kapal = Kapal::withoutGlobalScopes()->findOrFail($p->kapal_id);
            if ($kapal->anggota_id !== $anggota->id || $kapal->status !== StatusAktif::Aktif) {
                throw new BisnisException('Kapal tidak sesuai dengan anggota atau tidak aktif.');
            }

            $surat = SuratRekomendasi::withoutGlobalScopes()->findOrFail($p->surat_rekomendasi_id);
            if ($surat->kapal_id !== $kapal->id || ! $surat->berlakuPada(today())) {
                throw new BisnisException('Surat rekomendasi tidak berlaku atau tidak sesuai dengan kapal.');
            }

            $liter = Dec::n($p->liter_diminta);
            $periode = $this->kuota->periode();

            $kuota = $this->kuota->baris($surat, $periode);
            $pas = $this->pasokan->baris($periode);
            $this->stok->saldo();

            $sisaKuota = Dec::sub(Dec::sub($kuota->batas_liter, $kuota->liter_terpakai), $kuota->liter_dialokasikan);
            if (Dec::gt($liter, $sisaKuota)) {
                throw new BisnisException('Kuota bulanan surat rekomendasi tidak mencukupi (sisa ' . Format::liter(Dec::max0($sisaKuota)) . ').');
            }
            if (Dec::gt($liter, $this->stok->tersedia())) {
                throw new BisnisException('Stok BBM tersedia tidak mencukupi (tersedia ' . Format::liter($this->stok->tersedia()) . ').');
            }
            if (Dec::gt($liter, $this->pasokan->sisaAlokasi($pas))) {
                throw new BisnisException('Batas pasokan bulanan tidak mencukupi (sisa ' . Format::liter(Dec::max0($this->pasokan->sisaAlokasi($pas))) . ').');
            }

            $harga = $this->harga->aktif();
            $total = Dec::mul($liter, $harga);
            $dari = $p->status;

            $p->forceFill([
                'status' => StatusPengajuan::Disetujui,
                'harga_per_liter' => $harga,
                'total_tagihan' => $total,
                'periode_kuota' => $periode->toDateString(),
                'disetujui_oleh' => $user->id,
                'disetujui_pada' => now(),
            ])->save();

            $alokasi = AlokasiBbm::create([
                'pengajuan_id' => $p->id, 'periode' => $periode->toDateString(),
                'liter_disetujui' => $liter, 'liter_tersalurkan' => 0, 'liter_dilepas' => 0, 'status' => StatusAlokasi::Aktif->value,
            ]);

            $this->kuota->alokasi($kuota, $liter);
            $this->pasokan->alokasi($pas, $liter);
            $this->stok->catat(JenisMutasi::Alokasi, '0', $liter, $alokasi, 'Alokasi pengajuan ' . $p->nomor_pengajuan, $user);

            Tagihan::create([
                'nomor_tagihan' => $this->nomor->terbitkan('tagihan'),
                'pengajuan_id' => $p->id, 'anggota_id' => $p->anggota_id,
                'liter' => $liter, 'harga_per_liter' => $harga, 'total' => $total,
                'status' => StatusTagihan::BelumDibayar->value, 'tanggal_tagihan' => today()->toDateString(),
            ]);

            $this->catatStatus($p, $dari, StatusPengajuan::Disetujui, null, $user);
            AuditService::catat('pengajuan.disetujui', $p, ['status' => $dari->value], [
                'status' => 'disetujui', 'liter' => $liter, 'harga_per_liter' => $harga, 'periode_kuota' => $periode->toDateString(),
            ], $p->nomor_pengajuan, $user);

            return $p;
        });
    }

    public function tolak(PengajuanPembelian $pengajuan, User $user, string $alasan): PengajuanPembelian
    {
        Otorisasi::pastikan($user, 'pengajuan.reject');
        $alasan = $this->alasanWajib($alasan, 'penolakan');

        return DB::transaction(function () use ($pengajuan, $user, $alasan) {
            $p = PengajuanPembelian::withoutGlobalScopes()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($p->status !== StatusPengajuan::MenungguPersetujuan) {
                throw new BisnisException('Hanya pengajuan berstatus "Menunggu persetujuan" yang dapat ditolak.');
            }

            $p->forceFill([
                'status' => StatusPengajuan::Ditolak, 'ditolak_oleh' => $user->id,
                'ditolak_pada' => now(), 'alasan_penolakan' => $alasan,
            ])->save();

            $this->catatStatus($p, StatusPengajuan::MenungguPersetujuan, StatusPengajuan::Ditolak, $alasan, $user);
            AuditService::catat('pengajuan.ditolak', $p, null, ['alasan' => $alasan], $p->nomor_pengajuan, $user);

            return $p;
        });
    }

    /** Pembatalan pengajuan yang sudah disetujui oleh admin: alokasi dilepas, stok fisik TIDAK berubah. */
    public function batalkan(PengajuanPembelian $pengajuan, User $user, string $alasan): PengajuanPembelian
    {
        Otorisasi::pastikan($user, 'pengajuan.cancel');
        $alasan = $this->alasanWajib($alasan, 'pembatalan');

        return DB::transaction(function () use ($pengajuan, $user, $alasan) {
            $p = PengajuanPembelian::withoutGlobalScopes()->lockForUpdate()->findOrFail($pengajuan->id);

            if ($p->status !== StatusPengajuan::Disetujui) {
                throw new BisnisException('Hanya pengajuan berstatus "Disetujui" yang dapat dibatalkan oleh admin.');
            }
            if ($p->status_penyaluran === StatusPenyaluran::Selesai) {
                throw new BisnisException('Pengajuan tidak dapat dibatalkan karena BBM sudah disalurkan.');
            }
            if ($p->status_pembayaran === StatusPembayaran::Lunas) {
                throw new BisnisException('Pembayaran sudah lunas. Batalkan pembayaran terlebih dahulu sebelum membatalkan pengajuan.');
            }

            $surat = SuratRekomendasi::withoutGlobalScopes()->findOrFail($p->surat_rekomendasi_id);
            $kuota = $this->kuota->baris($surat, $p->periode_kuota);
            $pas = $this->pasokan->baris($p->periode_kuota);
            $alokasi = AlokasiBbm::query()->lockForUpdate()->where('pengajuan_id', $p->id)->firstOrFail();
            $sisa = $alokasi->liter_aktif;

            if (Dec::gt($sisa, 0)) {
                $this->kuota->lepas($kuota, $sisa);
                $this->pasokan->lepas($pas, $sisa);
                $this->stok->catat(JenisMutasi::LepasAlokasi, '0', Dec::sub('0', $sisa), $alokasi, 'Pembatalan ' . $p->nomor_pengajuan, $user);
            }

            $alokasi->forceFill([
                'liter_dilepas' => Dec::add($alokasi->liter_dilepas, $sisa), 'status' => StatusAlokasi::Dilepas->value,
            ])->save();

            Tagihan::query()->withoutGlobalScopes()->where('pengajuan_id', $p->id)->update(['status' => StatusTagihan::Dibatalkan->value]);
            JadwalPembelian::query()->withoutGlobalScopes()->where('pengajuan_id', $p->id)
                ->whereIn('status', [StatusJadwal::Terjadwal->value, StatusJadwal::Ditunda->value])
                ->update(['status' => StatusJadwal::Dibatalkan->value]);

            $p->forceFill([
                'status' => StatusPengajuan::Dibatalkan, 'dibatalkan_oleh' => $user->id,
                'dibatalkan_pada' => now(), 'alasan_pembatalan' => $alasan,
            ])->save();

            $this->catatStatus($p, StatusPengajuan::Disetujui, StatusPengajuan::Dibatalkan, $alasan, $user);
            AuditService::catat('pengajuan.dibatalkan', $p, null, ['alasan' => $alasan, 'liter_dilepas' => $sisa], $p->nomor_pengajuan, $user);

            return $p;
        });
    }

    /** Anggota membatalkan pengajuannya sendiri selama belum diproses admin. */
    public function batalkanOlehAnggota(PengajuanPembelian $pengajuan, Anggota $anggota, ?string $alasan = null): PengajuanPembelian
    {
        return DB::transaction(function () use ($pengajuan, $anggota, $alasan) {
            $p = PengajuanPembelian::withoutGlobalScopes()->lockForUpdate()->findOrFail($pengajuan->id);

            if ($p->anggota_id !== $anggota->id) {
                throw new BisnisException('Pengajuan tidak ditemukan.');
            }
            if ($p->status !== StatusPengajuan::MenungguPersetujuan) {
                throw new BisnisException('Pengajuan yang sudah diproses tidak dapat dibatalkan sendiri. Hubungi admin koperasi.');
            }

            $alasan = trim((string) $alasan) !== '' ? trim($alasan) : 'Dibatalkan oleh anggota';
            $p->forceFill([
                'status' => StatusPengajuan::Dibatalkan, 'dibatalkan_oleh' => auth()->id(),
                'dibatalkan_pada' => now(), 'alasan_pembatalan' => $alasan,
            ])->save();

            $this->catatStatus($p, StatusPengajuan::MenungguPersetujuan, StatusPengajuan::Dibatalkan, $alasan, auth()->user());
            AuditService::catat('pengajuan.dibatalkan_anggota', $p, null, ['alasan' => $alasan], $p->nomor_pengajuan);

            return $p;
        });
    }

    /** Transaksi selesai hanya bila pembayaran lunas DAN penyaluran tercatat. Dipanggil di dalam transaksi pemanggil. */
    public function selesaikanJikaLengkap(PengajuanPembelian $p): bool
    {
        if ($p->status === StatusPengajuan::Disetujui
            && $p->status_pembayaran === StatusPembayaran::Lunas
            && $p->status_penyaluran === StatusPenyaluran::Selesai) {
            $p->forceFill(['status' => StatusPengajuan::Selesai, 'selesai_pada' => now()])->save();
            $this->catatStatus($p, StatusPengajuan::Disetujui, StatusPengajuan::Selesai, null, auth()->user());
            AuditService::catat('pengajuan.selesai', $p, null, null, $p->nomor_pengajuan);

            return true;
        }

        return false;
    }

    public function catatStatus(PengajuanPembelian $p, ?StatusPengajuan $dari, StatusPengajuan $ke, ?string $alasan, ?User $user): void
    {
        RiwayatStatusPengajuan::create([
            'pengajuan_id' => $p->id, 'dari_status' => $dari?->value, 'ke_status' => $ke->value,
            'alasan' => $alasan, 'user_id' => $user?->id,
        ]);
    }

    private function alasanWajib(string $alasan, string $jenis): string
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) {
            throw new BisnisException("Alasan {$jenis} wajib diisi.");
        }

        return $alasan;
    }
}
