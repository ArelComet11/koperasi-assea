<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pengajuan_pembelian', 'tujuan')) {
            Schema::table('pengajuan_pembelian', function (Blueprint $table) {
                $table->string('tujuan', 100)->default('Melaut')->after('tanggal_rencana');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pengajuan_pembelian', 'tujuan')) {
            Schema::table('pengajuan_pembelian', function (Blueprint $table) {
                $table->dropColumn('tujuan');
            });
        }
    }
};
