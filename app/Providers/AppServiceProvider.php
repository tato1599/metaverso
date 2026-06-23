<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Lti\LaunchValidador::class, \App\Lti\LibreriaLaunchValidador::class);
        $this->app->bind(\App\Lti\DeepLinkRespondedor::class, \App\Lti\LibreriaDeepLinkRespondedor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
