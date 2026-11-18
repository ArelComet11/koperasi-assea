<?php

namespace App\Support;

class Format
{
    public static function rupiah(mixed $v): string
    {
        return 'Rp' . number_format((float) Dec::n($v), 0, ',', '.');
    }

    public static function liter(mixed $v): string
    {
        $s = number_format((float) Dec::n($v), 2, ',', '.');
        return rtrim(rtrim($s, '0'), ',') . ' L';
    }

    public static function persen(mixed $bagian, mixed $total): float
    {
        return Dec::gt($total, 0) ? round((float) $bagian / (float) $total * 100, 1) : 0.0;
    }
}
