<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Login;
use App\Filament\Support\AvatarLokal;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AnggotaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('anggota')
            ->path('anggota')
            ->login(Login::class)
            ->brandName('KSU Assea Lorgan Mandiri')
            ->brandLogo(fn () => view('components.logo-brand'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/logo-koperasi.png'))
            ->defaultAvatarProvider(AvatarLokal::class)
            ->spa()
            ->spaUrlExceptions(fn (): array => [url('/dokumen/*'), url('/cetak/*'), url('/laporan/*')])
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::hex('#078B4B'),
                'success' => Color::hex('#078B4B'),
                'warning' => Color::hex('#E5B51B'),
                'danger' => Color::hex('#D71920'),
                'info' => Color::hex('#0B6FA4'),
            ])
            
            ->discoverResources(in: app_path('Filament/Anggota/Resources'), for: 'App\\Filament\\Anggota\\Resources')
            ->discoverPages(in: app_path('Filament/Anggota/Pages'), for: 'App\\Filament\\Anggota\\Pages')
            ->discoverWidgets(in: app_path('Filament/Anggota/Widgets'), for: 'App\\Filament\\Anggota\\Widgets')
            ->pages([Dashboard::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
