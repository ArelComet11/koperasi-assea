<?php

namespace App\Services;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPembayaranCatatan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenyaluran;
use App\Enums\StatusTagihan;
use App\Exceptions\BisnisException;
use App\Models\Pembayaran;
use App\Models\PengajuanPembelian;
use App\Models\Tagihan;
use App\Models\User;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Pembayaran selalu penuh (tanpa cicilan). Jumlah = total tagihan, tidak bisa diisi bebas. */
class PembayaranService
{
    public function __construct(private NomorService $nomor, private PengajuanService $pengajuanService)
    {
    }

    public function catat(Tagihan $tagihan, User $user, MetodePembayaran|string $metode, ?string $nomorBukti = null, ?Carbon $tanggal = null): Pembayaran
    {
        Otorisasi::pastikan($user, 'pembayaran.create');
        $metode = $metode instanceof MetodePembayaran ? $metode : MetodePembayaran::from($metode);

        return DB::transaction(function () use ($tagihan, $user, $metode, $nomorBukti, $tanggal) {
            $idPengajuan = Tagihan::query()->withoutGlobalScopes()->findOrFail($tagihan->id)->pengajuan_id;
            $p = PengajuanPembelian::withoutGlobalScopes()->lockForUpdate()->findOrFail($idPengajuan);
            $t = Tagihan::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($tagihan->id);

            if ($p->status !== StatusPengajuan::Disetujui) {
                throw new BisnisException('Pembayaran hanya dapat dicatat untuk pengajuan yang berstatus "Disetujui".');
            }
            if ($t->status !== StatusTagihan::BelumDibayar) {
                throw new BisnisException('Tagihan ini sudah lunas atau dibatalkan.');
            }

            $bayar = Pembayaran::create([
                'nomor_pembayaran' => $this->nomor->terbitkan('pembayaran', $tanggal),
                'tagihan_id' => $t->id, 'jumlah' => $t->total,
                'tanggal_pembayaran' => $tanggal ?? now(), 'metode' => $metode->value,
                'nomor_bukti' => $nomorBukti ?: null, 'status' => StatusPembayaranCatatan::Sah->value,
                'tagihan_aktif_id' => $t->id, 'diterima_oleh' => $user->id,
            ]);

            $t->forceFill(['status' => StatusTagihan::Lunas])->save();
            $p->forceFill(['status_pembayaran' => StatusPembayaran::Lunas])->save();

            AuditService::catat('pembayaran.dicatat', $bayar, null, [
                'tagihan' => $t->nomor_tagihan, 'jumlah' => $t->total, 'metode' => $metode->value,
            ], $bayar->nomor_pembayaran, $user);

            $this->pengajuanService->selesaikanJikaLengkap($p);

            return $bayar;
        });
    }

    public function batalkan(Pembayaran $pembayaran, User $user, string $alasan): Pembayaran
    {
        Otorisasi::pastikan($user, 'pembayaran.cancel');
        if (mb_strlen(trim($alasan)) < 3) {
            throw new BisnisException('Alasan pembatalan pembayaran wajib diisi.');
        }

        return DB::transaction(function () use ($pembayaran, $user, $alasan) {
            $awal = Pembayaran::query()->withoutGlobalScopes()->findOrFail($pembayaran->id);
            $t0 = Tagihan::query()->withoutGlobalScopes()->findOrFail($awal->tagihan_id);
            $p = PengajuanPembelian::withoutGlobalScopes()->lockForUpdate()->findOrFail($t0->pengajuan_id);
            $t = Tagihan::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($t0->id);
            $b = Pembayaran::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($awal->id);

            if ($b->status !== StatusPembayaranCatatan::Sah) {
                throw new BisnisException('Pembayaran ini sudah dibatalkan.');
            }
            if ($p->status_penyaluran === StatusPenyaluran::Selesai || $p->status === StatusPengajuan::Selesai) {
                throw new BisnisException('Pembayaran tidak dapat dibatalkan karena BBM sudah disalurkan dan transaksi selesai.');
            }

            $b->forceFill([
                'status' => StatusPembayaranCatatan::Dibatalkan, 'tagihan_aktif_id' => null,
                'alasan_pembatalan' => trim($alasan), 'dibatalkan_oleh' => $user->id, 'dibatalkan_pada' => now(),
            ])->save();
            $t->forceFill(['status' => StatusTagihan::BelumDibayar])->save();
            $p->forceFill(['status_pembayaran' => StatusPembayaran::BelumDibayar])->save();

            AuditService::catat('pembayaran.dibatalkan', $b, null, ['alasan' => trim($alasan)], $b->nomor_pembayaran, $user);

            return $b;
        });
    }
}
