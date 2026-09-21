<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep generated links under /Financial_Literacy (no /public)
        if ($root = config('app.url')) {
            URL::forceRootUrl(rtrim($root, '/'));

            // Avoid Secure cookies / HTTPS redirects when serving over plain HTTP
            if (str_starts_with($root, 'http://')) {
                URL::forceScheme('http');
            }
        }

        // Keep PHP GC from deleting this app's session files before Laravel lifetime.
        $lifetimeMinutes = max(10, (int) config('session.lifetime', 120));
        ini_set('session.gc_maxlifetime', (string) ($lifetimeMinutes * 60));
    }
}
