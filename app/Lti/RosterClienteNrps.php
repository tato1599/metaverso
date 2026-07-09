<?php

namespace App\Lti;

use App\Models\Grupo;
use GuzzleHttp\Client;
use Packback\Lti1p3\LtiNamesRolesProvisioningService;
use Packback\Lti1p3\LtiServiceConnector;

class RosterClienteNrps implements RosterCliente
{
    public function getMembers(Grupo $grupo): array
    {
        $contexto = $grupo->ltiContexto;
        $platform = $contexto?->plataforma;
        if ($contexto === null || $platform === null) {
            throw new \RuntimeException('El grupo no tiene un contexto LTI con plataforma.');
        }

        // Registration resuelta con issuer + client_id de la plataforma EXACTA del
        // contexto: dos plataformas pueden compartir issuer (enmienda F7c).
        $registration = (new LtiDatabase)->findRegistrationByIssuer($platform->issuer, $platform->client_id);
        if ($registration === null) {
            throw new \RuntimeException("No hay registro LTI activo para {$platform->issuer}.");
        }

        // Connector con timeouts (patrón de EnviarCalificacionAgs): una consulta a
        // Moodle colgada no debe congelar la petición del admin.
        $connector = new LtiServiceConnector(new LtiCache, new Client([
            'timeout' => 10.0,
            'connect_timeout' => 5.0,
        ]));

        $nrps = new LtiNamesRolesProvisioningService($connector, $registration, [
            'context_memberships_url' => $contexto->nrps_url,
            'service_versions' => ['2.0'],
        ]);

        // Sin `limit`: Moodle regresa todo en una página y así no dependemos del
        // parseo del header Link de la paginación (enmienda cliente-nrps-hardening).
        return $nrps->getMembers();
    }
}
