<?php

namespace App\Providers;

use App\Services\AuditService;
use App\Services\PengaturanService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PengaturanService::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('id');

        Event::listen(Login::class, function (Login $e) {
            $e->user->forceFill(['last_login_at' => now()])->saveQuietly();
            AuditService::catat('auth.login', null, null, null, 'Login', $e->user);
        });

        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user) {
                AuditService::catat('auth.logout', null, null, null, 'Logout', $e->user);
            }
        });

        View::composer(['layouts.*', 'public.*'], function ($view) {
            $p = app(PengaturanService::class);
            $view->with('pengaturan', $p)->with('logoUrl', $p->logoUrl());
        });
    }
}
