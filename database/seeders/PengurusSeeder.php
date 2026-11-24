<?php

namespace Database\Seeders;

use App\Models\Pengurus;
use Illuminate\Database\Seeder;

class PengurusSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // Level 1 — Pucuk Pimpinan
            ['nama' => 'H. Radian Salman, S.E., M.M.', 'jabatan' => 'Ketua Koperasi', 'level' => 1, 'urutan' => 1],

            // Level 2 — Pengurus Inti
            ['nama' => 'Agustine Prihatin Kadir, S.E., Ak.', 'jabatan' => 'Bendahara Koperasi', 'level' => 2, 'urutan' => 1],
            ['nama' => 'Tri Suksmono Asung R., S.H., M.H.', 'jabatan' => 'Sekretaris Koperasi', 'level' => 2, 'urutan' => 2],

            // Level 3 — Manajer
            ['nama' => 'Hendro Gunarto, S.E., M.PSDM.', 'jabatan' => 'Manajer Operasional', 'level' => 3, 'urutan' => 1],
            ['nama' => 'Ali Sahab, S.IP., M.Si.', 'jabatan' => 'Manajer Usaha & BBM', 'level' => 3, 'urutan' => 2],
            ['nama' => 'Rian Kurnia Aqdami, S.Stat.', 'jabatan' => 'Administrasi & IT', 'level' => 3, 'urutan' => 3],

            // Level 4 — Seksi / Pelaksana
            ['nama' => 'Novendra Dwi Yusnanto, S.E.', 'jabatan' => 'Seksi Keanggotaan', 'level' => 4, 'urutan' => 1],
            ['nama' => 'Ayunda Nirmala, S.M., MM.', 'jabatan' => 'Seksi Keuangan & Kasir', 'level' => 4, 'urutan' => 2],
            ['nama' => 'Risdiana Nissa, S.Hum.', 'jabatan' => 'Seksi Penerimaan BBM', 'level' => 4, 'urutan' => 3],
            ['nama' => 'Johan Wahyudi, S.H.', 'jabatan' => 'Seksi Penyaluran', 'level' => 4, 'urutan' => 4],
            ['nama' => 'Elli Handayani Rahmat, S.E.', 'jabatan' => 'Seksi Umum & Humas', 'level' => 4, 'urutan' => 5],
        ];

        foreach ($data as $row) {
            Pengurus::query()->firstOrCreate(
                ['nama' => $row['nama'], 'jabatan' => $row['jabatan']],
                [
                    'level' => $row['level'],
                    'tema_pangkat' => 'otomatis',
                    'urutan' => $row['urutan'],
                    'tampil' => true,
                    'foto_transparan' => false,
                ]
            );
        }
    }
}
