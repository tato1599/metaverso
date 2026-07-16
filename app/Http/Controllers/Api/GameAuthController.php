<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SesionPractica;
use App\Models\TokenJuego;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GameAuthController extends Controller {
    public function __construct(private MagicLinkService $magicLink) {}

    /**
     * Canjear magic link (redeem)
     *
     * Canjea un magic link de un solo uso y devuelve un token de acceso Bearer de Sanctum
     * junto con los datos del alumno, la práctica y el evento. El token queda marcado como
     * usado de forma atómica, por lo que no puede reutilizarse.
     *
     * @group Flujo de juego (Unreal)
     *
     * @bodyParam token string required El token del magic link generado por `POST /api/links`. Example: abc123xyz456
     *
     * @response 200 scenario="Token canjeado exitosamente" {
     *   "access_token": "1|abcdefghijklmnopqrstuvwxyz1234567890",
     *   "token_type": "Bearer",
     *   "alumno": {
     *     "id_alumno": 15,
     *     "numero_control": "21TI0001",
     *     "nombre": "Juan Pérez López",
     *     "semestre": 5,
     *     "id_grupo": 3
     *   },
     *   "practica": {
     *     "id_practica": 2,
     *     "titulo": "Práctica 1 – Redes LAN virtuales",
     *     "descripcion": "Configuración de switches y VLANs en entorno virtual",
     *     "escena_referencia": "recolecta",
     *     "config": {"meta_objetos": 10, "tiempo_limite_seg": 120, "dificultad": "media"}
     *   },
     *   "evento": {
     *     "id_evento": 7,
     *     "fecha_hora_inicio": "2026-06-23T10:00:00+00:00",
     *     "fecha_hora_fin": "2026-06-23T12:00:00+00:00",
     *     "estatus": "activo"
     *   }
     * }
     *
     * @response 401 scenario="Token inválido (no encontrado)" {
     *   "message": "Token inválido"
     * }
     *
     * @response 410 scenario="Token expirado o ya utilizado" {
     *   "message": "Token expirado o ya utilizado"
     * }
     *
     * @response 422 scenario="Falta el campo token" {
     *   "message": "The token field is required.",
     *   "errors": {
     *     "token": ["The token field is required."]
     *   }
     * }
     */
    public function redeem(Request $request) {
        $data = $request->validate(['token' => 'required|string']);

        $tokenJuego = TokenJuego::where('token_hash', $this->magicLink->hash($data['token']))->first();
        if (! $tokenJuego) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $claimed = TokenJuego::where('id_token', $tokenJuego->id_token)
            ->where('usado', false)
            ->where('fecha_expiracion', '>', now())
            ->update(['usado' => true, 'fecha_uso' => now(), 'ip_origen' => $request->ip()]);
        if (! $claimed) {
            return response()->json(['message' => 'Token expirado o ya utilizado'], 410);
        }
        $tokenJuego->refresh();

        $tokenJuego->load(['usuario.alumno', 'evento.practica']);
        $usuario = $tokenJuego->usuario;
        $evento = $tokenJuego->evento;
        if (! $usuario || ! $evento) {
            return response()->json(['message' => 'Token inválido'], 410);
        }

        // La ability evento:{id} liga el bearer al evento del magic link: es la única
        // fuente confiable para enlazar sesión↔reserva (el id_evento del body es del cliente).
        $abilities = $tokenJuego->id_evento ? ['game', 'evento:'.$tokenJuego->id_evento] : ['game'];
        $accessToken = $usuario->createToken('unreal-session', $abilities)->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'alumno' => $usuario->alumno,
            'practica' => $evento->practica,
            'evento' => $evento->only(['id_evento','fecha_hora_inicio','fecha_hora_fin','estatus']),
        ]);
    }

    /**
     * Canjear token de sesión LTI (ltiRedeem)
     *
     * Canjea el token de un solo uso generado en el launch LTI y devuelve un
     * Bearer Sanctum para que Unreal Engine acceda al resto de la API de juego.
     *
     * @group Flujo de juego (Unreal)
     *
     * @bodyParam lti_session_token string required El token generado en el deep-link de la vista lti.abrir-juego. Example: abc123xyz456
     *
     * @response 200 scenario="Token canjeado exitosamente" {
     *   "access_token": "1|abcdefghijklmnopqrstuvwxyz1234567890",
     *   "token_type": "Bearer",
     *   "alumno": {"id_alumno": 15},
     *   "practica": {"id_practica": 2, "titulo": "Lab LTI"},
     *   "id_sesion": 42
     * }
     *
     * @response 410 scenario="Token inválido, expirado o ya usado" {
     *   "message": "Token inválido o expirado"
     * }
     */
    public function ltiRedeem(Request $request) {
        $data = $request->validate(['lti_session_token' => 'required|string']);

        $idSesion = Cache::pull('lti_play_'.hash('sha256', $data['lti_session_token']));
        if (! $idSesion) {
            return response()->json(['message' => 'Token inválido o expirado'], 410);
        }

        $sesion = SesionPractica::with(['alumno.usuario', 'practica'])->find($idSesion);
        if (! $sesion || ! $sesion->alumno || ! $sesion->alumno->usuario) {
            return response()->json(['message' => 'Sesión no encontrada'], 410);
        }

        $usuario = $sesion->alumno->usuario;
        $accessToken = $usuario->createToken('unreal-session', ['game'])->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'alumno' => $sesion->alumno,
            'practica' => $sesion->practica,
            'id_sesion' => $sesion->id_sesion,
        ]);
    }
}
