<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        // Nama bulan/hari di dokumen (kontrak, adendum) pakai Bahasa Indonesia. translatedFormat()
        // sebelumnya ikut APP_LOCALE=en -> "01 January 1998" di Kontrak 2/3 & Adendum.
        \Carbon\Carbon::setLocale('id');

        // Register policies
        Gate::policy(\App\Models\Mitra::class, \App\Policies\MitraPolicy::class);
        Gate::policy(\App\Models\Klien::class, \App\Policies\KlienPolicy::class);
        Gate::policy(\App\Models\Order::class, \App\Policies\OrderPolicy::class);
    }
}
