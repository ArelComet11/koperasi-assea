<?php

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Enums\StatusPenerimaan;
use App\Exceptions\BisnisException;
use App\Models\PenerimaanBbm;
use App\Models\User;
use App\Support\Dec;
use App\Support\Format;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PenerimaanService
{
    public function __construct(private StokService $stok, private PasokanService $pasokan, private NomorService $nomor)
    {
    }

    public function catat(array $data, User $user): PenerimaanBbm
    {
        Otorisasi::pastikan($user, 'penerimaan.manage');

        $liter = Dec::n($data['liter'] ?? 0);
        if (Dec::lte($liter, 0)) {
            throw new BisnisException('Jumlah liter yang diterima harus lebih dari 0.');
        }
        if (trim((string) ($data['nama_pemasok'] ?? '')) === '') {
            throw new BisnisException('Nama pemasok atau sumber pasokan wajib diisi.');
        }

        $tanggal = Carbon::parse($data['tanggal_penerimaan'] ?? today())->startOfDay();
        $override = (bool) ($data['override_pasokan'] ?? false);
        $harga = isset($data['harga_per_liter']) && $data['harga_per_liter'] !== '' ? Dec::n($data['harga_per_liter']) : null;

        return DB::transaction(function () use ($data, $user, $liter, $tanggal, $override, $harga) {
            $pas = $this->pasokan->baris($tanggal);
            $melebihi = Dec::gt(Dec::add($pas->total_diterima, $liter), $pas->batas_liter);

            if ($melebihi) {
                if (! $override) {
                    throw new BisnisException('Penerimaan melebihi batas pasokan bulanan ' . Format::liter($pas->batas_liter)
                        . ' (sisa ' . Format::liter(Dec::max0($this->pasokan->sisaPenerimaan($pas))) . ').');
                }
                Otorisasi::pastikan($user, 'pasokan.override');
                if (mb_strlen(trim((string) ($data['alasan_override'] ?? ''))) < 5) {
                    throw new BisnisException('Alasan override batas pasokan wajib diisi.');
                }
            }

            $p = PenerimaanBbm::create([
                'nomor_penerimaan' => $this->nomor->terbitkan('penerimaan', $tanggal),
                'tanggal_penerimaan' => $tanggal->toDateString(),
                'periode' => $tanggal->copy()->startOfMonth()->toDateString(),
                'nama_pemasok' => trim($data['nama_pemasok']),
                'liter' => $liter,
                'harga_per_liter' => $harga,
                'total_nilai' => $harga !== null ? Dec::mul($liter, $harga) : null,
                'nomor_dokumen' => $data['nomor_dokumen'] ?? null,
                'status' => StatusPenerimaan::Terkonfirmasi->value,
                'override_pasokan' => $melebihi && $override,
                'alasan_override' => $melebihi && $override ? trim($data['alasan_override']) : null,
                'override_oleh' => $melebihi && $override ? $user->id : null,
                'dicatat_oleh' => $user->id,
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $this->pasokan->tambahPenerimaan($pas, $liter, $melebihi && $override);
            $waktuMutasi = $tanggal->isSameDay(today()) ? now() : $tanggal->copy()->setTimeFrom(now());
            $this->stok->catat(JenisMutasi::Penerimaan, $liter, $p, 'Penerimaan ' . $p->nomor_penerimaan, $user, $waktuMutasi);

            AuditService::catat('penerimaan.dicatat', $p, null, [
                'liter' => $liter, 'pemasok' => $p->nama_pemasok, 'override' => $p->override_pasokan,
            ], $p->nomor_penerimaan, $user);
            if ($p->override_pasokan) {
                AuditService::catat('pasokan.override', $p, null, ['alasan' => $p->alasan_override], $p->nomor_penerimaan, $user);
            }

            return $p;
        });
    }

    /** Koreksi = batalkan (ledger pembalik) lalu catat ulang. Ledger tetap append-only. */
    public function batalkan(PenerimaanBbm $penerimaan, User $user, string $alasan): PenerimaanBbm
    {
        Otorisasi::pastikan($user, 'penerimaan.manage');
        if (mb_strlen(trim($alasan)) < 3) {
            throw new BisnisException('Alasan pembatalan penerimaan wajib diisi.');
        }

        return DB::transaction(function () use ($penerimaan, $user, $alasan) {
            $p = PenerimaanBbm::query()->lockForUpdate()->findOrFail($penerimaan->id);
            if ($p->status !== StatusPenerimaan::Terkonfirmasi) {
                throw new BisnisException('Penerimaan ini sudah dibatalkan.');
            }

            $pas = $this->pasokan->baris($p->periode);
            $this->stok->saldo();
            if (Dec::gt($p->liter, $this->stok->tersedia())) {
                throw new BisnisException('Penerimaan tidak dapat dibatalkan karena sebagian BBM sudah disalurkan (stok fisik ' . Format::liter($this->stok->tersedia()) . ').');
            }

            $this->stok->catat(JenisMutasi::PembatalanPenerimaan, Dec::sub('0', $p->liter), $p, 'Pembatalan ' . $p->nomor_penerimaan, $user);
            $this->pasokan->kurangiPenerimaan($pas, $p->liter);

            $p->forceFill([
                'status' => StatusPenerimaan::Dibatalkan, 'dibatalkan_oleh' => $user->id,
                'dibatalkan_pada' => now(), 'alasan_pembatalan' => trim($alasan),
            ])->save();

            AuditService::catat('penerimaan.dibatalkan', $p, null, ['alasan' => trim($alasan)], $p->nomor_penerimaan, $user);

            return $p;
        });
    }
}
