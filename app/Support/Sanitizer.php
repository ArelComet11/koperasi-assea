<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/** Membersihkan HTML dari editor teks sebelum ditampilkan di halaman publik. */
class Sanitizer
{
    public static function html(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $dir = storage_path('app/purifier');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $dir);
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,ul,ol,li,blockquote,h2,h3,h4,a[href|title],img[src|alt],hr,table,thead,tbody,tr,th,td');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);

        return (new HTMLPurifier($config))->purify($html);
    }
}
