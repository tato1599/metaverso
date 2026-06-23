<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class LinkController extends Controller {
    public function __construct(private MagicLinkService $magicLink) {}

    /**
     * Generar magic link de sesión
     *
     * Genera un magic link de un solo uso para que un alumno ingrese al metaverso.
     * El link incluye una URL web (`/jugar/{token}`) y un deeplink para Unreal Engine.
     * Requiere autenticación por clave de API en el header `X-Api-Key`.
     *
     * @group Generación de links (docente)
     *
     * @header X-Api-Key string required
     *         La clave de API del sistema docente/plataforma. Ejemplo: mi-clave-secreta-api
     *
     * @bodyParam id_usuario int required El ID del usuario (alumno) para quien se genera el link. Example: 42
     * @bodyParam id_evento int required El ID del evento de agenda al que pertenece la sesión. Example: 7
     * @bodyParam plataforma string optional La plataforma destino. Por defecto: `unreal`. Example: unreal
     *
     * @response 201 scenario="Magic link generado exitosamente" {
     *   "url": "http://localhost:8000/jugar/abc123xyz456",
     *   "deeplink": "tecnm-metaverso://play?token=abc123xyz456",
     *   "expira": "2026-06-23T18:00:00+00:00"
     * }
     *
     * @response 401 scenario="Clave de API inválida o ausente" {
     *   "message": "No autorizado"
     * }
     *
     * @response 422 scenario="Datos de entrada inválidos" {
     *   "message": "The id_usuario field is required.",
     *   "errors": {
     *     "id_usuario": ["The id_usuario field is required."],
     *     "id_evento": ["The id_evento field is required."]
     *   }
     * }
     */
    public function store(Request $request) {
        $expected = config('metaverso.links_api_key');
        if (! $expected || ! hash_equals($expected, (string) $request->header('X-Api-Key'))) {
            return response()->json(['message' => 'No autorizado'], 401);
        }

        $data = $request->validate([
            'id_usuario' => 'required|integer',
            'id_evento' => 'required|integer',
            'plataforma' => 'nullable|string',
        ]);

        $res = $this->magicLink->generar(
            $data['id_usuario'], $data['id_evento'], $data['plataforma'] ?? 'unreal'
        );

        return response()->json([
            'url' => $res['url'],
            'deeplink' => $res['deeplink'],
            'expira' => $res['modelo']->fecha_expiracion->toIso8601String(),
        ], 201);
    }
}
