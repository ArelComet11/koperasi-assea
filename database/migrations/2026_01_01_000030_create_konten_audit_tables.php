<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_berita', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        Schema::create('berita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_berita_id')->nullable()->constrained('kategori_berita')->nullOnDelete();
            $table->string('judul', 200);
            $table->string('slug', 220)->unique();
            $table->text('ringkasan')->nullable();
            $table->longText('konten')->nullable();
            $table->string('gambar')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('penulis_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('galeri', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 150);
            $table->string('gambar');
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_kegiatan')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('tampil')->default(true);
            $table->timestamps();
        });

        Schema::create('pengurus', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('jabatan', 100);
            $table->string('foto')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('tampil')->default(true);
            $table->timestamps();
        });

        Schema::create('aktivitas_admin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_pengguna', 150)->nullable();
            $table->string('jenis', 60);
            // Polymorphic TANPA foreign key: log tidak hilang saat data terkait dinonaktifkan/dihapus.
            $table->string('subjek_type')->nullable();
            $table->unsignedBigInteger('subjek_id')->nullable();
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['jenis', 'created_at']);
            $table->index(['subjek_type', 'subjek_id']);
        });
    }

    public function down(): void
    {
        foreach (['aktivitas_admin', 'pengurus', 'galeri', 'berita', 'kategori_berita'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
