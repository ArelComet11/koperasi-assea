<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('kode_anggota', 30)->unique();
            $table->string('nama_lengkap', 150);
            $table->text('alamat')->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('status', 20)->default('aktif')->index();
            $table->date('tanggal_bergabung')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('kapal', function (Blueprint $table) {
            $table->id();
            $table->string('kode_kapal', 30)->unique();
            $table->foreignId('anggota_id')->constrained('anggota')->restrictOnDelete();
            $table->string('nama_kapal', 150);
            $table->string('nama_pemilik', 150)->nullable();
            $table->string('no_registrasi', 60)->nullable()->unique();
            $table->string('jenis_kapal', 60)->nullable();
            $table->decimal('ukuran_gt', 8, 2)->nullable();
            $table->string('status', 20)->default('aktif')->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // Target composite FK: menjamin kapal selalu milik anggota yang sama di tabel lain.
            $table->unique(['id', 'anggota_id'], 'kapal_id_anggota_unique');
        });

        Schema::create('surat_rekomendasi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat', 80)->unique();
            $table->foreignId('anggota_id')->constrained('anggota')->restrictOnDelete();
            $table->unsignedBigInteger('kapal_id');
            $table->date('tanggal_surat')->nullable();
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai');
            $table->decimal('kuota_bulanan_liter', 12, 2)->default(1000);
            $table->string('status', 20)->default('aktif')->index();
            $table->string('file_path')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign(['kapal_id', 'anggota_id'], 'fk_surat_kapal_anggota')
                ->references(['id', 'anggota_id'])->on('kapal')->restrictOnDelete();
            $table->unique(['id', 'kapal_id'], 'surat_id_kapal_unique');
        });

        Schema::create('kuota_rekomendasi_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_rekomendasi_id')->constrained('surat_rekomendasi')->restrictOnDelete();
            $table->date('periode');
            $table->decimal('batas_liter', 12, 2);
            $table->decimal('liter_dialokasikan', 12, 2)->default(0);
            $table->decimal('liter_terpakai', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['surat_rekomendasi_id', 'periode'], 'kuota_surat_periode_unique');
        });

        Schema::create('kuota_pasokan_bulanan', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->unique();
            $table->decimal('batas_liter', 12, 2);
            $table->decimal('total_diterima', 12, 2)->default(0);
            $table->decimal('total_dialokasikan', 12, 2)->default(0);
            $table->decimal('total_disalurkan', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 100)->unique();
            $table->longText('nilai')->nullable();
            $table->string('tipe', 20)->default('string');
            $table->string('kelompok', 40)->index();
            $table->string('label', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('penomoran', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20);
            $table->string('periode', 6);
            $table->unsignedInteger('nomor_terakhir')->default(0);
            $table->timestamps();
            $table->unique(['jenis', 'periode']);
        });

        Schema::create('riwayat_harga', function (Blueprint $table) {
            $table->id();
            $table->decimal('harga_per_liter', 15, 2);
            $table->dateTime('berlaku_mulai')->index();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('alasan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['riwayat_harga', 'penomoran', 'pengaturan', 'kuota_pasokan_bulanan', 'kuota_rekomendasi_bulanan', 'surat_rekomendasi', 'kapal', 'anggota'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
