<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pengajuan', 40)->unique();
            $table->foreignId('anggota_id')->constrained('anggota')->restrictOnDelete();
            $table->unsignedBigInteger('kapal_id');
            $table->unsignedBigInteger('surat_rekomendasi_id');
            $table->decimal('liter_diminta', 12, 2);
            $table->decimal('harga_estimasi_per_liter', 15, 2);
            $table->decimal('total_estimasi', 15, 2);
            $table->date('tanggal_rencana')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status', 30)->default('menunggu_persetujuan');
            $table->string('status_pembayaran', 20)->default('belum_dibayar');
            $table->string('status_penyaluran', 20)->default('belum');
            // Snapshot saat disetujui
            $table->decimal('harga_per_liter', 15, 2)->nullable();
            $table->decimal('total_tagihan', 15, 2)->nullable();
            $table->date('periode_kuota')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->foreignId('ditolak_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('ditolak_pada')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->foreign(['kapal_id', 'anggota_id'], 'fk_pengajuan_kapal_anggota')
                ->references(['id', 'anggota_id'])->on('kapal')->restrictOnDelete();
            $table->foreign(['surat_rekomendasi_id', 'kapal_id'], 'fk_pengajuan_surat_kapal')
                ->references(['id', 'kapal_id'])->on('surat_rekomendasi')->restrictOnDelete();
            $table->index(['anggota_id', 'status']);
            $table->index(['surat_rekomendasi_id', 'periode_kuota']);
            $table->index(['status', 'created_at']);
            $table->index('periode_kuota');
        });

        Schema::create('riwayat_status_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_pembelian')->restrictOnDelete();
            $table->string('dari_status', 30)->nullable();
            $table->string('ke_status', 30);
            $table->text('alasan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['pengajuan_id', 'created_at']);
        });

        Schema::create('alokasi_bbm', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_pembelian')->restrictOnDelete();
            $table->date('periode')->index();
            $table->decimal('liter_disetujui', 12, 2);
            $table->decimal('liter_tersalurkan', 12, 2)->default(0);
            $table->decimal('liter_dilepas', 12, 2)->default(0);
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
        });

        Schema::create('jadwal_pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_jadwal', 40)->unique();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_pembelian')->restrictOnDelete();
            $table->dateTime('tanggal_waktu')->index();
            $table->decimal('liter_disetujui', 12, 2);
            $table->string('status', 20)->default('terjadwal');
            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('penerimaan_bbm', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_penerimaan', 40)->unique();
            $table->date('tanggal_penerimaan')->index();
            $table->date('periode')->index();
            $table->string('nama_pemasok', 150);
            $table->decimal('liter', 12, 2);
            $table->decimal('harga_per_liter', 15, 2)->nullable();
            $table->decimal('total_nilai', 15, 2)->nullable();
            $table->string('nomor_dokumen', 80)->nullable();
            $table->string('status', 20)->default('terkonfirmasi');
            $table->boolean('override_pasokan')->default(false);
            $table->text('alasan_override')->nullable();
            $table->foreignId('override_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('dicatat_oleh')->constrained('users')->restrictOnDelete();
            $table->text('keterangan')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamps();
        });

        Schema::create('penyaluran_bbm', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_penyaluran', 40)->unique();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_pembelian')->restrictOnDelete();
            $table->foreignId('alokasi_id')->constrained('alokasi_bbm')->restrictOnDelete();
            $table->decimal('liter_disetujui', 12, 2);
            $table->decimal('liter_aktual', 12, 2);
            $table->dateTime('tanggal_penyaluran')->index();
            $table->foreignId('petugas_id')->constrained('users')->restrictOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('stok_bbm', function (Blueprint $table) {
            $table->id();
            $table->decimal('stok_fisik', 12, 2)->default(0);
            $table->decimal('stok_dialokasikan', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id();
            $table->dateTime('tanggal')->index();
            $table->string('jenis', 30);
            $table->decimal('delta_fisik', 12, 2)->default(0);
            $table->decimal('delta_alokasi', 12, 2)->default(0);
            $table->decimal('saldo_fisik', 12, 2);
            $table->decimal('saldo_alokasi', 12, 2);
            $table->string('referensi_type')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['referensi_type', 'referensi_id']);
            $table->index('jenis');
        });

        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tagihan', 40)->unique();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_pembelian')->restrictOnDelete();
            $table->foreignId('anggota_id')->constrained('anggota')->restrictOnDelete();
            $table->decimal('liter', 12, 2);
            $table->decimal('harga_per_liter', 15, 2);
            $table->decimal('total', 15, 2);
            $table->string('status', 20)->default('belum_dibayar')->index();
            $table->date('tanggal_tagihan')->index();
            $table->timestamps();
        });

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pembayaran', 40)->unique();
            $table->foreignId('tagihan_id')->constrained('tagihan')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->dateTime('tanggal_pembayaran')->index();
            $table->string('metode', 20);
            $table->string('nomor_bukti', 80)->nullable();
            $table->string('status', 20)->default('sah');
            // Berisi tagihan_id hanya saat status sah. UNIQUE + NULL = satu pembayaran sah per tagihan.
            $table->unsignedBigInteger('tagihan_aktif_id')->nullable()->unique();
            $table->foreignId('diterima_oleh')->constrained('users')->restrictOnDelete();
            $table->text('alasan_pembatalan')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('penyesuaian_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->restrictOnDelete();
            $table->decimal('total_lama', 15, 2);
            $table->decimal('total_baru', 15, 2);
            $table->decimal('selisih', 15, 2);
            $table->string('alasan');
            $table->boolean('diterapkan')->default(true);
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['penyesuaian_tagihan', 'pembayaran', 'tagihan', 'mutasi_stok', 'stok_bbm', 'penyaluran_bbm', 'penerimaan_bbm', 'jadwal_pembelian', 'alokasi_bbm', 'riwayat_status_pengajuan', 'pengajuan_pembelian'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
