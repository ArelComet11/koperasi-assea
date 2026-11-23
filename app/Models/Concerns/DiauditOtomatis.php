<?php

namespace App\Models\Concerns;

use App\Services\AuditService;

/** Mencatat pembuatan dan perubahan data induk/konten ke audit log. */
trait DiauditOtomatis
{
    public static function bootDiauditOtomatis(): void
    {
        static::created(function ($m) {
            AuditService::catat($m->getTable() . '.dibuat', $m, null, self::ringkasAudit($m->getAttributes()), self::labelAudit($m));
        });

        static::updated(function ($m) {
            $berubah = array_diff_key($m->getChanges(), ['updated_at' => 1]);
            if ($berubah) {
                AuditService::catat(
                    $m->getTable() . '.diubah',
                    $m,
                    self::ringkasAudit(array_intersect_key($m->getOriginal(), $berubah)),
                    self::ringkasAudit($berubah),
                    self::labelAudit($m),
                );
            }
        });
    }

    private static function ringkasAudit(array $data): array
    {
        return array_map(fn ($v) => is_string($v) && mb_strlen($v) > 200 ? mb_substr($v, 0, 200) . '…' : $v, $data);
    }

    private static function labelAudit($m): ?string
    {
        return $m->nama_kapal ?? $m->nomor_surat ?? $m->judul ?? $m->nama ?? null;
    }
}
