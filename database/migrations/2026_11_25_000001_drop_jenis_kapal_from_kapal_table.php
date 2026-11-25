<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kapal', 'jenis_kapal')) {
            Schema::table('kapal', function (Blueprint $table) {
                $table->dropColumn('jenis_kapal');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kapal', 'jenis_kapal')) {
            Schema::table('kapal', function (Blueprint $table) {
                $table->string('jenis_kapal', 60)->nullable()->after('no_registrasi');
            });
        }
    }
};
