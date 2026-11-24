<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengurus', function (Blueprint $table) {
            if (! Schema::hasColumn('pengurus', 'level')) {
                $table->unsignedTinyInteger('level')->default(4)->after('jabatan');
            }
            if (! Schema::hasColumn('pengurus', 'tema_pangkat')) {
                $table->string('tema_pangkat', 30)->default('otomatis')->after('level');
            }
            if (! Schema::hasColumn('pengurus', 'foto_transparan')) {
                $table->boolean('foto_transparan')->default(false)->after('foto');
            }
            $table->index(['tampil', 'level', 'urutan'], 'pengurus_tampil_level_urutan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pengurus', function (Blueprint $table) {
            $table->dropIndex('pengurus_tampil_level_urutan_idx');
            foreach (['foto_transparan', 'tema_pangkat', 'level'] as $col) {
                if (Schema::hasColumn('pengurus', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
