<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PanelLoginController extends Controller
{
    /**
     * Punto único de inicio de sesión del panel (reutilizable por el launch de LTI).
     * OJO: este método NO valida el rol. Todo caller debe verificar antes
     * $usuario->esStaffPanel() (como hace acceso()), o un Alumno entraría al panel.
     */
    public function establecerSesion(Usuario $usuario): void
    {
        Auth::login($usuario);
    }

    public function acceso(Request $request, Usuario $usuario)
    {
        // La firma ya fue validada por el middleware 'signed'.
        if (! $usuario->esStaffPanel()) {
            abort(403, 'Esta cuenta no tiene acceso al panel.');
        }
        $this->establecerSesion($usuario);

        return redirect()->route('panel.dashboard');
    }

    public function salir(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
