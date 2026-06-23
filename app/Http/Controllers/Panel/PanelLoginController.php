<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PanelLoginController extends Controller {
    // Punto unico de inicio de sesion del panel (reutilizable por LTI).
    public function establecerSesion(Usuario $usuario): void {
        Auth::login($usuario);
    }

    public function acceso(Request $request, Usuario $usuario) {
        // La firma ya fue validada por el middleware 'signed'.
        if (! $usuario->esStaffPanel()) {
            abort(403, 'Esta cuenta no tiene acceso al panel.');
        }
        $this->establecerSesion($usuario);
        return redirect()->route('panel.dashboard');
    }

    public function salir(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('panel.acceso.invalido');
    }
}
