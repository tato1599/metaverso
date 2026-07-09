<?php

namespace App\Providers;

use App\Lti\AgsCliente;
use App\Lti\DeepLinkRespondedor;
use App\Lti\EnviarCalificacionAgs;
use App\Lti\LaunchValidador;
use App\Lti\LibreriaDeepLinkRespondedor;
use App\Lti\LibreriaLaunchValidador;
use App\Lti\RosterCliente;
use App\Lti\RosterClienteNrps;
use Firebase\JWT\JWT;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LaunchValidador::class, LibreriaLaunchValidador::class);
        $this->app->bind(DeepLinkRespondedor::class, LibreriaDeepLinkRespondedor::class);
        $this->app->bind(AgsCliente::class, EnviarCalificacionAgs::class);
        $this->app->bind(RosterCliente::class, RosterClienteNrps::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tolerancia de reloj para validar los JWT de LTI: el launch de Moodle
        // dura ~60s y el reloj del contenedor Docker suele desfasarse de la Mac.
        // 300s de leeway evita los errores "iat prior to"/"token expired" por skew.
        JWT::$leeway = 300;
    }
}
