<?php
namespace App\Lti;

use App\Models\Practica;
use GuzzleHttp\Client;
use Packback\Lti1p3\DeepLinkResources\Resource;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;

class LibreriaDeepLinkRespondedor implements DeepLinkRespondedor {
    public function construir(int $idPractica): array {
        $connector = new LtiServiceConnector(new LtiCache(), new Client(['timeout' => 10.0]));
        $launch = LtiMessageLaunch::fromCache(
            session('lti_launch_id'),
            new LtiDatabase(),
            new LtiCache(),
            new LtiCookie(),
            $connector,
        );

        $practica = Practica::findOrFail($idPractica);
        $resource = Resource::new()
            ->setUrl(route('lti.launch'))
            ->setTitle($practica->titulo)
            ->setCustomParams(['id_practica' => (string) $practica->id_practica]);

        $deepLink = $launch->getDeepLink();

        return [
            'jwt' => $deepLink->getResponseJwt([$resource]),
            'returnUrl' => $deepLink->returnUrl(),
        ];
    }
}
