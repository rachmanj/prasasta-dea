<?php

namespace App\Providers;

use App\Models\CashAdvance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
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
        Route::bind('advance', fn (string $value) => CashAdvance::findOrFail($value));

        Vite::prefetch(concurrency: 3);
    }
}
