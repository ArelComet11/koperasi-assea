<?php

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Enums\StatusAlokasi;
use App\Enums\StatusJadwal;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenyaluran;
use App\Enums\StatusTagihan;
use App\Exceptions\BisnisException;
use App\Models\AlokasiBbm;
use App\Models\JadwalPembelian;
use App\Models\PengajuanPembelian;
use App\Models\PenyaluranBbm;
use App\Models\PenyesuaianTagihan;
use App\Models\SuratRekomendasi;
use App\Models\Tagihan;
use App\Models\User;
use App\Support\Dec;
use App\Support\Format;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PenyaluranService
{
    public function __construct(
        private StokService $stok,
        private KuotaService $kuota,
        private PasokanService $pasokan,
        private NomorService $nomor,
        private PengajuanService $pengajuanService,
    ) {
    }

    public function catat(PengajuanPembelian $pengajuan, string|int|float $literAktual, User $petugas, ?Carbon $tanggal = null, ?string $keterangan = null): PenyaluranBbm
    {
        Otorisasi::pastikan($petugas, 'penyaluran.manage');
        $aktual = Dec::n($literAktual);
        if (Dec::lte($aktual, 0)) {
            throw new BisnisException('Jumlah liter aktual harus lebih dari 0.');
        }

        return DB::transaction(function () use ($pengajuan, $aktual, $petugas, $tanggal, $keterangan) {
            // Urutan kunci: pengajuan -> alokasi -> kuota -> pasokan -> stok -> tagihan
            $p = PengajuanPembelian::withoutGlobalScopes()->lockForUpdate()->findOrFail($pengajuan->id);

            if ($p->status !== StatusPengajuan::Disetujui) {
                throw new BisnisException('Hanya pengajuan berstatus "Disetujui" yang dapat disalurkan.');
            }
            if ($p->status_penyaluran === StatusPenyaluran::Selesai) {
                throw new BisnisException('Pengajuan ini sudah disalurkan.');
            }

            $alokasi = AlokasiBbm::query()->lockForUpdate()->where('pengajuan_id', $p->id)->firstOrFail();
            if ($alokasi->status !== StatusAlokasi::Aktif) {
                throw new BisnisException('Alokasi stok untuk pengajuan ini tidak aktif.');
            }
            if (Dec::gt($aktual, $alokasi->liter_disetujui)) {
                throw new BisnisException('Jumlah aktual tidak boleh melebihi jumlah yang disetujui (' . Format::liter($alokasi->liter_disetujui) . ').');
            }

            $surat = SuratRekomendasi::withoutGlobalScopes()->findOrFail($p->surat_rekomendasi_id);
            $kuota = $this->kuota->baris($surat, $p->periode_kuota);
            $pas = $this->pasokan->baris($p->periode_kuota);
            $this->stok->saldo();
            $tagihan = Tagihan::query()->withoutGlobalScopes()->lockForUpdate()->where('pengajuan_id', $p->id)->firstOrFail();

            $disetujui = Dec::n($alokasi->liter_disetujui);
            $sisa = Dec::sub($disetujui, $aktual);

            $penyaluran = PenyaluranBbm::create([
                'nomor_penyaluran' => $this->nomor->terbitkan('penyaluran', $tanggal),
                'pengajuan_id' => $p->id, 'alokasi_id' => $alokasi->id,
                'liter_disetujui' => $disetujui, 'liter_aktual' => $aktual,
                'tanggal_penyaluran' => $tanggal ?? now(), 'petugas_id' => $petugas->id, 'keterangan' => $keterangan,
            ]);

            $alokasi->forceFill([
                'liter_tersalurkan' => $aktual, 'liter_dilepas' => $sisa, 'status' => StatusAlokasi::Selesai->value,
            ])->save();

            $this->kuota->realisasi($kuota, $disetujui, $aktual);
            $this->pasokan->realisasi($pas, $disetujui, $aktual);

            $this->stok->catat(JenisMutasi::Penyaluran, Dec::sub('0', $aktual), Dec::sub('0', $aktual), $penyaluran, 'Penyaluran ' . $p->nomor_pengajuan, $petugas);
            if (Dec::gt($sisa, 0)) {
                $this->stok->catat(JenisMutasi::LepasAlokasi, '0', Dec::sub('0', $sisa), $alokasi, 'Sisa alokasi dilepas ' . $p->nomor_pengajuan, $petugas);
                $this->sesuaikanTagihan($p, $tagihan, $aktual, $petugas);
            }

            $p->forceFill(['status_penyaluran' => StatusPenyaluran::Selesai])->save();

            JadwalPembelian::query()->withoutGlobalScopes()->where('pengajuan_id', $p->id)
                ->whereIn('status', [StatusJadwal::Terjadwal->value, StatusJadwal::Ditunda->value])
                ->update(['status' => StatusJadwal::Selesai->value]);

            AuditService::catat('penyaluran.dicatat', $penyaluran, null, [
                'pengajuan' => $p->nomor_pengajuan, 'disetujui' => $disetujui, 'aktual' => $aktual,
            ], $penyaluran->nomor_penyaluran, $petugas);

            $this->pengajuanService->selesaikanJikaLengkap($p);

            return $penyaluran;
        });
    }

    /** Keputusan 2: penyaluran < disetujui. Tagihan belum lunas disesuaikan otomatis; yang sudah lunas dicatat sebagai koreksi tertunda. */
    private function sesuaikanTagihan(PengajuanPembelian $p, Tagihan $tagihan, string $aktual, User $user): void
    {
        $baru = Dec::mul($aktual, $p->harga_per_liter);
        $lama = Dec::n($tagihan->total);

        if ($tagihan->status === StatusTagihan::BelumDibayar) {
            PenyesuaianTagihan::create([
                'tagihan_id' => $tagihan->id, 'total_lama' => $lama, 'total_baru' => $baru, 'selisih' => Dec::sub($baru, $lama),
                'alasan' => 'Penyaluran aktual kurang dari jumlah yang disetujui', 'diterapkan' => true, 'dibuat_oleh' => $user->id,
            ]);
            $tagihan->forceFill(['liter' => $aktual, 'total' => $baru])->save();
            $p->forceFill(['total_tagihan' => $baru])->save();
        } elseif ($tagihan->status === StatusTagihan::Lunas) {
            PenyesuaianTagihan::create([
                'tagihan_id' => $tagihan->id, 'total_lama' => $lama, 'total_baru' => $baru, 'selisih' => Dec::sub($baru, $lama),
                'alasan' => 'Tagihan sudah lunas sebelum penyaluran; selisih perlu dikoreksi/dikembalikan secara manual oleh admin',
                'diterapkan' => false, 'dibuat_oleh' => $user->id,
            ]);
        }
    }
}
