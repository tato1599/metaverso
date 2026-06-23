<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class LinkController extends Controller {
    public function __construct(private MagicLinkService $magicLink) {}

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
