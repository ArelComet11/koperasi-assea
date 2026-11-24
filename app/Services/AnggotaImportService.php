<?php

namespace App\Services;

use App\Exceptions\BisnisException;
use App\Models\Anggota;
use App\Models\User;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AnggotaImportService
{
    private const ALIAS_KOLOM = [
        'kode_anggota' => ['kode_anggota', 'kode', 'no_anggota', 'nomor_anggota'],
        'nama_lengkap' => ['nama_lengkap', 'nama', 'nama_anggota'],
        'username' => ['username', 'user', 'akun', 'nama_pengguna'],
        'password' => ['password', 'kata_sandi', 'sandi', 'pass'],
        'telepon' => ['telepon', 'no_hp', 'hp', 'no_telepon', 'telp', 'wa', 'whatsapp'],
        'email' => ['email', 'surel', 'alamat_email'],
        'tanggal_bergabung' => ['tanggal_bergabung', 'tgl_bergabung', 'tanggal_gabung', 'bergabung'],
        'alamat' => ['alamat'],
        'keterangan' => ['keterangan', 'catatan'],
    ];

    public function __construct(private AnggotaService $anggotaService)
    {
    }

    /**
     * Mengimpor banyak anggota dari file Excel (.xlsx/.xls/.csv).
     *
     * @return array{berhasil:int, gagal:array<int, array{baris:int, alasan:string}>}
     */
    public function impor(string $fullPath, User $oleh, ?string $passwordBawaan = null): array
    {
        Otorisasi::pastikan($oleh, 'anggota.create');

        $passwordBawaan = $passwordBawaan !== null && trim($passwordBawaan) !== '' ? trim($passwordBawaan) : null;
        if ($passwordBawaan !== null && mb_strlen($passwordBawaan) < 8) {
            throw new BisnisException('Kata sandi bawaan minimal 8 karakter.');
        }

        if (! is_file($fullPath)) {
            throw new BisnisException('File Excel tidak ditemukan.');
        }

        $sheets = Excel::toArray(new class {}, $fullPath);
        $rows = $sheets[0] ?? [];
        if (count($rows) < 2) {
            throw new BisnisException('File Excel kosong atau tidak memiliki baris data di bawah header.');
        }

        $petaKolom = $this->petakanHeader($rows[0]);
        if (! isset($petaKolom['nama_lengkap']) || ! isset($petaKolom['username'])) {
            throw new BisnisException('Header wajib memuat kolom "nama_lengkap" dan "username". Unduh template Excel untuk format yang benar.');
        }

        $berhasil = 0;
        $gagal = [];
        $urutKode = (int) (Anggota::withTrashed()->max('id') ?? 0);

        foreach (array_slice($rows, 1) as $idx => $row) {
            $nomorBaris = $idx + 2;
            $nilai = $this->ekstrakBaris($row, $petaKolom);

            // Lewati baris kosong
            if (implode('', array_map(fn ($v) => trim((string) $v), $nilai)) === '') {
                continue;
            }

            try {
                $nama = trim((string) ($nilai['nama_lengkap'] ?? ''));
                $username = trim((string) ($nilai['username'] ?? ''));
                $kode = trim((string) ($nilai['kode_anggota'] ?? ''));
                $password = trim((string) ($nilai['password'] ?? ''));
                if ($password === '' && $passwordBawaan !== null) {
                    $password = $passwordBawaan;
                }
                $email = trim((string) ($nilai['email'] ?? ''));
                $telepon = trim((string) ($nilai['telepon'] ?? ''));

                if ($nama === '') {
                    throw new BisnisException('Kolom nama_lengkap wajib diisi.');
                }
                if (mb_strlen($nama) > 150) {
                    throw new BisnisException('Nama lengkap maksimal 150 karakter.');
                }
                if ($username === '') {
                    throw new BisnisException('Kolom username wajib diisi.');
                }
                if (mb_strlen($username) > 50 || preg_match('/^[A-Za-z0-9_-]+$/', $username) !== 1) {
                    throw new BisnisException("Username '{$username}' hanya boleh huruf, angka, tanda hubung (-), atau garis bawah (_).");
                }
                if (User::query()->where('username', $username)->exists()) {
                    throw new BisnisException("Username '{$username}' sudah terdaftar.");
                }
                if ($password === '' || mb_strlen($password) < 8) {
                    throw new BisnisException('Kata sandi kosong atau kurang dari 8 karakter (isi kolom password atau isi kata sandi bawaan).');
                }
                if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new BisnisException("Format email '{$email}' tidak valid.");
                }

                if ($kode === '') {
                    do {
                        $urutKode++;
                        $kode = 'ANG-' . str_pad((string) $urutKode, 4, '0', STR_PAD_LEFT);
                    } while (Anggota::withTrashed()->where('kode_anggota', $kode)->exists());
                } else {
                    if (mb_strlen($kode) > 30) {
                        throw new BisnisException("Kode anggota '{$kode}' maksimal 30 karakter.");
                    }
                    if (Anggota::withTrashed()->where('kode_anggota', $kode)->exists()) {
                        throw new BisnisException("Kode anggota '{$kode}' sudah terdaftar.");
                    }
                }

                $tanggal = $this->parseTanggal($nilai['tanggal_bergabung'] ?? null);

                $this->anggotaService->buat([
                    'kode_anggota' => $kode,
                    'nama_lengkap' => $nama,
                    'username' => $username,
                    'password' => $password,
                    'telepon' => $telepon !== '' ? mb_substr($telepon, 0, 30) : null,
                    'email' => $email !== '' ? $email : null,
                    'tanggal_bergabung' => $tanggal,
                    'alamat' => trim((string) ($nilai['alamat'] ?? '')) ?: null,
                    'keterangan' => trim((string) ($nilai['keterangan'] ?? '')) ?: null,
                ], $oleh);

                $berhasil++;
            } catch (BisnisException $e) {
                $gagal[] = ['baris' => $nomorBaris, 'alasan' => $e->getMessage()];
            } catch (\Throwable $e) {
                $gagal[] = ['baris' => $nomorBaris, 'alasan' => 'Data tidak valid atau terjadi duplikasi.'];
            }
        }

        if ($berhasil > 0) {
            AuditService::catat('anggota.diimpor', null, null, [
                'berhasil' => $berhasil,
                'gagal' => count($gagal),
            ], "Import Excel anggota ({$berhasil} berhasil, " . count($gagal) . ' gagal)', $oleh);
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal];
    }

    /** @return array<string, int> */
    private function petakanHeader(array $headerRow): array
    {
        $peta = [];
        foreach ($headerRow as $colIdx => $raw) {
            $norm = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $raw), '_'));
            foreach (self::ALIAS_KOLOM as $field => $aliases) {
                if (! isset($peta[$field]) && in_array($norm, $aliases, true)) {
                    $peta[$field] = (int) $colIdx;
                }
            }
        }

        return $peta;
    }

    /** @param array<string, int> $peta */
    private function ekstrakBaris(array $row, array $peta): array
    {
        $hasil = [];
        foreach ($peta as $field => $colIdx) {
            $hasil[$field] = $row[$colIdx] ?? null;
        }

        return $hasil;
    }

    private function parseTanggal(mixed $nilai): string
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return today()->toDateString();
        }
        if (is_numeric($nilai)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $nilai))->toDateString();
        }

        $teks = trim((string) $nilai);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $teks);
                if ($dt !== false) {
                    return $dt->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($teks)->toDateString();
        } catch (\Throwable) {
            throw new BisnisException("Format tanggal bergabung '{$teks}' tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY).");
        }
    }
}
