<?php

use App\Http\Middleware\EnsureAlumno;
use App\Http\Middleware\EnsurePanelAccess;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // La única entrada en despliegue es Caddy (red interna del compose); sin esto
        // $request->ip() sería siempre la IP del proxy y el rate limit de login degrada a solo-correo.
        $middleware->trustProxies(at: '*');
        $middleware->redirectUsersTo(
            fn ($request) => $request->user()->esStaffPanel() ? route('panel.dashboard') : route('mi.calendario')
        );
        $middleware->validateCsrfTokens(except: ['lti/*']);
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'panel' => EnsurePanelAccess::class,
            'alumno' => EnsureAlumno::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
