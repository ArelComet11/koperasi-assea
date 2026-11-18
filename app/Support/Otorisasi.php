<?php

namespace App\Support;

use App\Exceptions\BisnisException;
use App\Models\User;

class Otorisasi
{
    /** Lapisan kedua di service: izin diperiksa lagi walaupun UI sudah menyembunyikan tombolnya. */
    public static function pastikan(?User $user, string $izin): void
    {
        if (! $user || ! $user->is_active || ! $user->can($izin)) {
            throw new BisnisException('Anda tidak memiliki izin untuk tindakan ini.');
        }
    }
}
