<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pagar terakhir di level database (MySQL 8.0.16+ / MariaDB 10.2+).
 * Pada SQLite (dipakai tes) constraint ini dilewati; service tetap memvalidasi semuanya.
 */
return new class extends Migration
{
    private array $checks = [
        ['surat_rekomendasi', 'ck_surat_tanggal', 'berlaku_sampai >= berlaku_mulai'],
        ['kuota_rekomendasi_bulanan', 'ck_kuota_surat_nonneg', 'liter_dialokasikan >= 0 AND liter_terpakai >= 0'],
        ['kuota_rekomendasi_bulanan', 'ck_kuota_surat_batas', 'liter_dialokasikan + liter_terpakai <= batas_liter'],
        ['kuota_pasokan_bulanan', 'ck_pasokan_nonneg', 'total_diterima >= 0 AND total_dialokasikan >= 0 AND total_disalurkan >= 0'],
        ['kuota_pasokan_bulanan', 'ck_pasokan_batas', 'total_dialokasikan + total_disalurkan <= batas_liter'],
        ['stok_bbm', 'ck_stok_nonneg', 'stok_fisik >= 0 AND stok_dialokasikan >= 0'],
        ['stok_bbm', 'ck_stok_alokasi', 'stok_dialokasikan <= stok_fisik'],
        ['pengajuan_pembelian', 'ck_pengajuan_liter', 'liter_diminta > 0'],
        ['pengajuan_pembelian', 'ck_pengajuan_disetujui', "status <> 'disetujui' OR (harga_per_liter IS NOT NULL AND periode_kuota IS NOT NULL AND disetujui_oleh IS NOT NULL)"],
        ['pengajuan_pembelian', 'ck_pengajuan_ditolak', "status <> 'ditolak' OR alasan_penolakan IS NOT NULL"],
        ['pengajuan_pembelian', 'ck_pengajuan_dibatalkan', "status <> 'dibatalkan' OR alasan_pembatalan IS NOT NULL"],
        ['alokasi_bbm', 'ck_alokasi_liter', 'liter_disetujui > 0'],
        ['alokasi_bbm', 'ck_alokasi_sisa', 'liter_tersalurkan + liter_dilepas <= liter_disetujui'],
        ['penyaluran_bbm', 'ck_penyaluran_liter', 'liter_aktual > 0 AND liter_aktual <= liter_disetujui'],
        ['penerimaan_bbm', 'ck_penerimaan_liter', 'liter > 0'],
        ['tagihan', 'ck_tagihan_total', 'total >= 0'],
    ];

    private function aktif(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function up(): void
    {
        if (! $this->aktif()) {
            return;
        }
        foreach ($this->checks as [$tabel, $nama, $ekspresi]) {
            DB::statement("ALTER TABLE `{$tabel}` ADD CONSTRAINT `{$nama}` CHECK ({$ekspresi})");
        }
    }

    public function down(): void
    {
        if (! $this->aktif()) {
            return;
        }
        foreach (array_reverse($this->checks) as [$tabel, $nama]) {
            DB::statement("ALTER TABLE `{$tabel}` DROP CONSTRAINT `{$nama}`");
        }
    }
};
