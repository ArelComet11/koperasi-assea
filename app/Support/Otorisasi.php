<?php

namespace App\Support;

use App\Exceptions\BisnisException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class Otorisasi
{
    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function userWajib(): User
    {
        $user = self::user();
        if (! $user) {
            throw new BisnisException('Sesi login tidak ditemukan.');
        }

        return $user;
    }

    public static function bisa(string $izin): bool
    {
        return (bool) self::user()?->can($izin);
    }

    /** Lapisan kedua di service: izin diperiksa lagi walaupun UI sudah menyembunyikan tombolnya. */
    public static function pastikan(?User $user, string $izin): void
    {
        if (! $user || ! $user->is_active || ! $user->can($izin)) {
            throw new BisnisException('Anda tidak memiliki izin untuk tindakan ini.');
        }
    }
}
