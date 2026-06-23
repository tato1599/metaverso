<?php
namespace App\Services;

use App\Models\TokenJuego;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class MagicLinkService {
    public function hash(string $token): string {
        return hash('sha256', $token);
    }

    public function generar(int $idUsuario, int $idEvento, string $plataforma = 'unreal'): array {
        $token = Str::random(64);
        $ttl = (int) config('metaverso.magic_link_ttl_minutes', 120);

        $modelo = TokenJuego::create([
            'id_usuario' => $idUsuario,
            'id_evento' => $idEvento,
            'token_hash' => $this->hash($token),
            'plataforma' => $plataforma,
            'fecha_expiracion' => now()->addMinutes($ttl),
            'usado' => false,
        ]);

        $scheme = config('metaverso.deeplink_scheme', 'tecnm-metaverso');

        return [
            'token' => $token,
            'modelo' => $modelo,
            'url' => URL::to('/jugar/'.$token),
            'deeplink' => "{$scheme}://play?token={$token}",
        ];
    }
}
