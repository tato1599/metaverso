<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePanelAccess {
    public function handle(Request $request, Closure $next) {
        $u = $request->user();
        abort_unless($u && $u->esStaffPanel(), 403, 'Acceso solo para personal docente.');
        return $next($request);
    }
}
