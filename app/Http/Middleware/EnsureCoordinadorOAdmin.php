<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCoordinadorOAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        abort_unless($u && $u->activo && $u->esCoordinadorOAdmin(), 403, 'Acceso solo para coordinación.');

        return $next($request);
    }
}
