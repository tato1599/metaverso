<?php
namespace App\Lti;

class DatosLaunch {
    public function __construct(
        public bool $esDeepLink,
        public string $issuer,
        public string $ltiUserId,
        public string $nombre,
        public string $apellidos,
        public ?string $correo,
        public ?int $idPractica,
        public ?string $agsLineitemUrl,
        public ?string $agsEndpoint,
        public ?int $ltiPlatformId,
        public ?string $launchId = null,
    ) {}
}
