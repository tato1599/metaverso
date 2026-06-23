<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TokenJuego;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class GameAuthController extends Controller {
    public function __construct(private MagicLinkService $magicLink) {}

    public function redeem(Request $request) {
        $data = $request->validate(['token' => 'required|string']);

        $tokenJuego = TokenJuego::where('token_hash', $this->magicLink->hash($data['token']))->first();
        if (! $tokenJuego) {
            return response()->json(['message' => 'Token inválido'], 401);
        }
        if ($tokenJuego->usado || $tokenJuego->fecha_expiracion->isPast()) {
            return response()->json(['message' => 'Token expirado o ya utilizado'], 410);
        }

        $tokenJuego->update([
            'usado' => true,
            'fecha_uso' => now(),
            'ip_origen' => $request->ip(),
        ]);

        $usuario = $tokenJuego->usuario()->with('alumno')->first();
        $evento = $tokenJuego->evento()->with('practica')->first();

        $accessToken = $usuario->createToken('unreal-session', ['game'])->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'alumno' => $usuario->alumno,
            'practica' => $evento->practica,
            'evento' => $evento->only(['id_evento','fecha_hora_inicio','fecha_hora_fin','estatus']),
        ]);
    }
}
