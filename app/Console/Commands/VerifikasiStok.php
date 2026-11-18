<?php

namespace App\Console\Commands;

use App\Services\StokService;
use Illuminate\Console\Command;

class VerifikasiStok extends Command
{
    protected $signature = 'stok:verifikasi';

    protected $description = 'Memeriksa konsistensi saldo stok terhadap ledger mutasi_stok';

    public function handle(StokService $stok): int
    {
        $v = $stok->verifikasi();
        $this->table(['', 'Saldo', 'Ledger'], [
            ['Stok fisik', $v['fisik_saldo'], $v['fisik_ledger']],
            ['Stok dialokasikan', $v['alokasi_saldo'], $v['alokasi_ledger']],
        ]);

        if ($v['konsisten']) {
            $this->info('Konsisten.');

            return self::SUCCESS;
        }

        $this->error('TIDAK konsisten: saldo tidak sama dengan jumlah ledger.');

        return self::FAILURE;
    }
}
