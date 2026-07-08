<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAlumno
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user() && $request->user()->esAlumno(), 403, 'Acceso solo para alumnos.');

        return $next($request);
    }
}
