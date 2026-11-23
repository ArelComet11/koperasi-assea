<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Avatar inisial SVG lokal (data URI) agar panel tidak bergantung pada request eksternal ui-avatars.com. */
class AvatarLokal implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $inisial = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(function (string $kata): string {
                $huruf = preg_replace('/^[^\p{L}\p{N}]+/u', '', $kata);

                return filled($huruf) ? mb_strtoupper(mb_substr($huruf, 0, 1)) : '';
            })
            ->implode('') ?: 'U';

        $teks = htmlspecialchars($inisial, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            . '<rect width="64" height="64" rx="32" fill="#075B37"/>'
            . '<text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" fill="#FFFFFF" font-family="system-ui,sans-serif" font-size="24" font-weight="700">'
            . $teks
            . '</text></svg>';

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
