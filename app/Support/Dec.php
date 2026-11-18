<?php

namespace App\Support;

/** Aritmetika desimal presisi tetap (bcmath). Semua angka berupa string. */
class Dec
{
    public const SCALE = 2;

    public static function n(mixed $v): string
    {
        $v = $v === null || $v === '' ? '0' : (string) $v;
        return bcadd($v, '0', self::SCALE);
    }

    public static function add(mixed $a, mixed $b): string { return bcadd(self::n($a), self::n($b), self::SCALE); }
    public static function sub(mixed $a, mixed $b): string { return bcsub(self::n($a), self::n($b), self::SCALE); }
    public static function mul(mixed $a, mixed $b): string { return bcmul(self::n($a), self::n($b), self::SCALE); }
    public static function cmp(mixed $a, mixed $b): int { return bccomp(self::n($a), self::n($b), self::SCALE); }
    public static function gt(mixed $a, mixed $b): bool { return self::cmp($a, $b) > 0; }
    public static function gte(mixed $a, mixed $b): bool { return self::cmp($a, $b) >= 0; }
    public static function lt(mixed $a, mixed $b): bool { return self::cmp($a, $b) < 0; }
    public static function lte(mixed $a, mixed $b): bool { return self::cmp($a, $b) <= 0; }
    public static function eq(mixed $a, mixed $b): bool { return self::cmp($a, $b) === 0; }
    public static function max0(mixed $a): string { return self::lt($a, 0) ? '0.00' : self::n($a); }
}
