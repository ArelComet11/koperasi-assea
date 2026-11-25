<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function dropCheckConstraint(string $tabel, string $nama): void
    {
        $ada = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tabel)
            ->where('constraint_name', $nama)
            ->exists();

        if (! $ada) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `{$tabel}` DROP CONSTRAINT `{$nama}`");
        } catch (\Throwable) {
            DB::statement("ALTER TABLE `{$tabel}` DROP CHECK `{$nama}`");
        }
    }

    public function up(): void
    {
        $isMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        if ($isMysql) {
            foreach (['ck_stok_alokasi', 'ck_stok_nonneg'] as $nama) {
                $this->dropCheckConstraint('stok_bbm', $nama);
            }
        }

        if (Schema::hasColumn('stok_bbm', 'stok_dialokasikan')) {
            Schema::table('stok_bbm', function (Blueprint $table) {
                $table->dropColumn('stok_dialokasikan');
            });
        }

        if ($isMysql) {
            DB::statement('ALTER TABLE `stok_bbm` ADD CONSTRAINT `ck_stok_nonneg` CHECK (stok_fisik >= 0)');
        }

        $kolomMutasi = array_values(array_filter(
            ['delta_alokasi', 'saldo_alokasi'],
            fn (string $kol) => Schema::hasColumn('mutasi_stok', $kol)
        ));

        if ($kolomMutasi !== []) {
            Schema::table('mutasi_stok', function (Blueprint $table) use ($kolomMutasi) {
                $table->dropColumn($kolomMutasi);
            });
        }
    }

    public function down(): void
    {
        $isMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        if ($isMysql) {
            $this->dropCheckConstraint('stok_bbm', 'ck_stok_nonneg');
        }

        if (! Schema::hasColumn('stok_bbm', 'stok_dialokasikan')) {
            Schema::table('stok_bbm', function (Blueprint $table) {
                $table->decimal('stok_dialokasikan', 12, 2)->default(0)->after('stok_fisik');
            });
        }

        if ($isMysql) {
            DB::statement('ALTER TABLE `stok_bbm` ADD CONSTRAINT `ck_stok_nonneg` CHECK (stok_fisik >= 0 AND stok_dialokasikan >= 0)');
            DB::statement('ALTER TABLE `stok_bbm` ADD CONSTRAINT `ck_stok_alokasi` CHECK (stok_dialokasikan <= stok_fisik)');
        }

        Schema::table('mutasi_stok', function (Blueprint $table) {
            if (! Schema::hasColumn('mutasi_stok', 'delta_alokasi')) {
                $table->decimal('delta_alokasi', 12, 2)->default(0)->after('delta_fisik');
            }
            if (! Schema::hasColumn('mutasi_stok', 'saldo_alokasi')) {
                $table->decimal('saldo_alokasi', 12, 2)->default(0)->after('saldo_fisik');
            }
        });
    }
};
