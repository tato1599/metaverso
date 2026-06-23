<?php
namespace App\Lti;

use App\Models\Practica;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Packback\Lti1p3\DeepLinkResources\Resource;
use Packback\Lti1p3\Factories\MessageFactory;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\Messages\DeepLinkingRequest;

class LibreriaDeepLinkRespondedor implements DeepLinkRespondedor {
    public function __construct(private Request $request) {}

    public function construir(int $idPractica): array {
        $connector = new LtiServiceConnector(new LtiCache(), new Client());
        $factory = new MessageFactory(new LtiDatabase(), $connector, new LtiCache(), new LtiCookie());
        $launch = $factory->create($this->request->all());

        if (!$launch instanceof DeepLinkingRequest) {
            throw new \RuntimeException('El launch no es una solicitud de Deep Linking.');
        }

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
