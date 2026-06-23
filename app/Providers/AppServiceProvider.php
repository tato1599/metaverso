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
        $this->app->bind(\App\Lti\AgsCliente::class, \App\Lti\EnviarCalificacionAgs::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tolerancia de reloj para validar los JWT de LTI: el launch de Moodle
        // dura ~60s y el reloj del contenedor Docker suele desfasarse de la Mac.
        // 300s de leeway evita los errores "iat prior to"/"token expired" por skew.
        \Firebase\JWT\JWT::$leeway = 300;
    }
}
