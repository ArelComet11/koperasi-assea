<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use App\Models\RiwayatHarga;
use App\Services\HargaService;
use App\Services\PengaturanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class PengaturanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PengaturanService::definisi() as $kunci => [$tipe, $kelompok, $label, $nilai]) {
            $baris = Pengaturan::query()->firstOrNew(['kunci' => $kunci]);
            if (! $baris->exists) {
                $baris->forceFill(['kunci' => $kunci, 'tipe' => $tipe, 'kelompok' => $kelompok, 'label' => $label, 'nilai' => $nilai]);
            } else {
                $baris->forceFill(['tipe' => $tipe, 'kelompok' => $kelompok, 'label' => $label]); // nilai tidak ditimpa
            }
            $baris->save();
        }
        Cache::forget('pengaturan.semua');

        if (! RiwayatHarga::query()->exists()) {
            RiwayatHarga::create([
                'harga_per_liter' => HargaService::HARGA_AWAL, 'berlaku_mulai' => now(), 'alasan' => 'Harga awal',
            ]);
        }
    }
}
