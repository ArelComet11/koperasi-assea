<?php

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Exceptions\BisnisException;
use App\Models\MutasiStok;
use App\Models\StokBbm;
use App\Models\User;
use App\Support\Dec;
use App\Support\Otorisasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan stok. Setiap perubahan = satu baris ledger (mutasi_stok) + pembaruan saldo,
 * dalam transaksi pemanggil, dengan baris saldo terkunci.
 */
class StokService
{
    public function saldo(): StokBbm
    {
        $this->pastikanTransaksi();

        DB::table('stok_bbm')->insertOrIgnore([
            'id' => 1, 'stok_fisik' => 0, 'stok_dialokasikan' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return StokBbm::query()->lockForUpdate()->findOrFail(1);
    }

    public function catat(JenisMutasi $jenis, string $deltaFisik, string $deltaAlokasi, ?Model $referensi, ?string $keterangan, ?User $user): MutasiStok
    {
        $s = $this->saldo();
        $fisik = Dec::add($s->stok_fisik, $deltaFisik);
        $alokasi = Dec::add($s->stok_dialokasikan, $deltaAlokasi);

        if (Dec::lt($fisik, 0)) {
            throw new BisnisException('Stok fisik tidak mencukupi untuk tindakan ini.');
        }
        if (Dec::lt($alokasi, 0)) {
            throw new BisnisException('Stok yang dialokasikan tidak boleh bernilai negatif.');
        }
        if (Dec::gt($alokasi, $fisik)) {
            throw new BisnisException('Stok yang dialokasikan tidak boleh melebihi stok fisik.');
        }

        $s->forceFill(['stok_fisik' => $fisik, 'stok_dialokasikan' => $alokasi])->save();

        return MutasiStok::create([
            'tanggal' => now(),
            'jenis' => $jenis->value,
            'delta_fisik' => Dec::n($deltaFisik),
            'delta_alokasi' => Dec::n($deltaAlokasi),
            'saldo_fisik' => $fisik,
            'saldo_alokasi' => $alokasi,
            'referensi_type' => $referensi?->getMorphClass(),
            'referensi_id' => $referensi?->getKey(),
            'keterangan' => $keterangan ? mb_substr($keterangan, 0, 255) : null,
            'dibuat_oleh' => $user?->id,
        ]);
    }

    public function saldoAwal(string|int|float $liter, User $user): MutasiStok
    {
        Otorisasi::pastikan($user, 'stok.saldo_awal');
        $liter = Dec::n($liter);

        if (Dec::lte($liter, 0)) {
            throw new BisnisException('Saldo awal harus lebih dari 0 liter.');
        }

        return DB::transaction(function () use ($liter, $user) {
            $this->saldo();
            if (MutasiStok::query()->where('jenis', JenisMutasi::SaldoAwal->value)->exists()) {
                throw new BisnisException('Saldo awal sudah pernah dicatat dan tidak dapat diubah. Gunakan penerimaan atau koreksi.');
            }
            $m = $this->catat(JenisMutasi::SaldoAwal, $liter, '0', null, 'Saldo awal stok', $user);
            AuditService::catat('stok.saldo_awal', $m, null, ['liter' => $liter], 'Saldo awal stok dicatat', $user);

            return $m;
        });
    }

    /** Pembacaan tanpa kunci untuk tampilan dan validasi awal. */
    public function ringkasan(): array
    {
        $s = StokBbm::query()->first();
        $fisik = Dec::n($s?->stok_fisik);
        $alokasi = Dec::n($s?->stok_dialokasikan);
        $jumlah = fn (array $jenis) => Dec::n(MutasiStok::query()->whereIn('jenis', $jenis)->sum('delta_fisik'));

        return [
            'fisik' => $fisik,
            'dialokasikan' => $alokasi,
            'tersedia' => Dec::sub($fisik, $alokasi),
            'awal' => $jumlah([JenisMutasi::SaldoAwal->value]),
            'penerimaan' => $jumlah([JenisMutasi::Penerimaan->value, JenisMutasi::PembatalanPenerimaan->value]),
            'penyaluran' => Dec::sub('0', $jumlah([JenisMutasi::Penyaluran->value])),
        ];
    }

    public function tersedia(): string
    {
        return $this->ringkasan()['tersedia'];
    }

    /** Pemeriksaan konsistensi saldo terhadap ledger. */
    public function verifikasi(): array
    {
        $s = StokBbm::query()->first();
        $sumFisik = Dec::n(MutasiStok::query()->sum('delta_fisik'));
        $sumAlokasi = Dec::n(MutasiStok::query()->sum('delta_alokasi'));

        return [
            'fisik_saldo' => Dec::n($s?->stok_fisik), 'fisik_ledger' => $sumFisik,
            'alokasi_saldo' => Dec::n($s?->stok_dialokasikan), 'alokasi_ledger' => $sumAlokasi,
            'konsisten' => Dec::eq($s?->stok_fisik, $sumFisik) && Dec::eq($s?->stok_dialokasikan, $sumAlokasi),
        ];
    }

    private function pastikanTransaksi(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('StokService harus dipanggil di dalam DB::transaction.');
        }
    }
}
