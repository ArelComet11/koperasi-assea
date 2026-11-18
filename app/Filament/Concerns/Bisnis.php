<?php

namespace App\Filament\Concerns;

use App\Exceptions\BisnisException;
use Filament\Notifications\Notification;

class Bisnis
{
    /** Menjalankan aksi service; pelanggaran aturan bisnis ditampilkan sebagai notifikasi, bukan error 500. */
    public static function jalankan(callable $aksi, ?string $sukses = null): mixed
    {
        try {
            $hasil = $aksi();
        } catch (BisnisException $e) {
            Notification::make()->title('Tindakan tidak dapat dilakukan')->body($e->getMessage())->danger()->persistent()->send();

            return null;
        }

        if ($sukses) {
            Notification::make()->title($sukses)->success()->send();
        }

        return $hasil ?? true;
    }
}
