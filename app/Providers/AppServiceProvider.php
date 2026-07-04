<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <-- WAJIB TAMBAHKAN INI

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
        // Cek apakah request datang dari proxy ngrok (biasanya ditandai dengan header X-Forwarded-Proto)
        if (str_contains(request()->headers->get('X-Forwarded-Proto') ?? '', 'https') || 
            str_contains(request()->server('HTTP_X_FORWARDED_PROTO') ?? '', 'https')) {
            
            URL::forceScheme('https');
        }
    }
}