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
    }
}
