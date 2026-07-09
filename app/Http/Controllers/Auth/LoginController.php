<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $llave = mb_strtolower($datos['correo']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw ValidationException::withMessages([
                'correo' => 'Demasiados intentos. Espera un minuto.',
            ]);
        }

        $credenciales = ['correo' => $datos['correo'], 'password' => $datos['password'], 'activo' => true];
        if (! Auth::attempt($credenciales)) {
            RateLimiter::hit($llave, 60);
            throw ValidationException::withMessages([
                'correo' => 'Credenciales incorrectas.',
            ]);
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();

        // Redirect puro por rol: intended() de otro contexto mandaría a un alumno a un 403.
        // Inertia::location fuerza una visita top-level tras el login (recarga limpia del
        // contexto de sesión), válida venga la petición de Inertia o de un POST plano.
        $u = $request->user();

        return Inertia::location($u->esStaffPanel() ? route('panel.dashboard') : route('mi.calendario'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
