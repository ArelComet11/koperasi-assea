<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kapal', function (Blueprint $table) {
            if (! Schema::hasColumn('kapal', 'daya_pk')) {
                $table->decimal('daya_pk', 8, 2)->nullable()->after('ukuran_gt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kapal', function (Blueprint $table) {
            if (Schema::hasColumn('kapal', 'daya_pk')) {
                $table->dropColumn('daya_pk');
            }
        });
    }
};
