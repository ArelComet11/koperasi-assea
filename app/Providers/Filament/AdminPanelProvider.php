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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('KSU Assea Lorgan Mandiri')
            ->brandLogo(fn () => view('components.logo-brand'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/logo-koperasi.png'))
            ->defaultAvatarProvider(AvatarLokal::class)
            ->spa()
            ->spaUrlExceptions(fn (): array => [url('/dokumen/*'), url('/cetak/*'), url('/laporan/*'), url('/template/*')])
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::hex('#078B4B'),
                'success' => Color::hex('#078B4B'),
                'warning' => Color::hex('#E5B51B'),
                'danger' => Color::hex('#D71920'),
                'info' => Color::hex('#0B6FA4'),
            ])
            ->navigationGroups(['Data Induk', 'Transaksi', 'Operasional BBM', 'Keuangan', 'Laporan', 'Konten Website', 'Sistem'])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
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
