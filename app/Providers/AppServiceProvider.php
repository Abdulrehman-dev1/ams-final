<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
   
public function boot(): void
{
    Paginator::useBootstrap();   // or useBootstrap() for BS4 projects

    // Fallback: neutralize @vite directive in Blade for Mix-based setup
    Blade::directive('vite', function () {
        return '';
    });

        // Ensure Vite uses our manifest path so lookups succeed
        try {
            Vite::useBuildDirectory('build');
            Vite::useManifest(public_path('build/manifest.json'));
        } catch (\Throwable $e) {
            // ignore if not supported in this framework version
        }
}
}
