<?php

namespace App\Providers;

use App\Models\JadwalPembelian;
use App\Models\PengajuanPembelian;
use App\Models\PenyesuaianTagihan;
use App\Models\SuratRekomendasi;
use App\Models\Tagihan;
use App\Services\AuditService;
use App\Services\PengaturanService;
use App\Support\LencanaCache;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Console\ServeCommand;
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

        // Di Windows, `php artisan serve` menyaring env proses anak sehingga TMP/TEMP hilang
        // dan PHP fallback ke C:\Windows yang tidak bisa ditulis saat upload file.
        if ($this->app->runningInConsole()) {
            ServeCommand::$passthroughVariables = array_values(array_unique([
                ...ServeCommand::$passthroughVariables,
                'TMP',
                'TEMP',
                'USERPROFILE',
            ]));
        }

        Event::listen(Login::class, function (Login $e) {
            $e->user->forceFill(['last_login_at' => now()])->saveQuietly();
            AuditService::catat('auth.login', null, null, null, 'Login', $e->user);
        });

        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user) {
                AuditService::catat('auth.logout', null, null, null, 'Logout', $e->user);
            }
        });

        foreach ([PengajuanPembelian::class, Tagihan::class, PenyesuaianTagihan::class, JadwalPembelian::class, SuratRekomendasi::class] as $model) {
            $model::saved(fn () => LencanaCache::hapus());
            $model::deleted(fn () => LencanaCache::hapus());
        }

        View::composer(['layouts.*', 'public.*'], function ($view) {
            $p = app(PengaturanService::class);
            $view->with('pengaturan', $p)->with('logoUrl', $p->logoUrl());
        });
    }
}
