<?php
namespace App\Lti;

use App\Models\LtiPlatform;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;

class LibreriaLaunchValidador implements LaunchValidador {
    public function validar(Request $request): DatosLaunch {
        $connector = new LtiServiceConnector(new LtiCache(), new Client());
        $launch = LtiMessageLaunch::new(new LtiDatabase(), new LtiCache(), new LtiCookie(), $connector)
            ->validate();

        $data = $launch->getLaunchData();
        $iss = $data['iss'];
        $platform = LtiPlatform::where('issuer', $iss)->first();

        $custom = $data['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? [];
        $ags = $data['https://purl.imsglobal.org/spec/lti-ags/claim/endpoint'] ?? [];

        return new DatosLaunch(
            esDeepLink: $launch->isDeepLinkLaunch(),
            issuer: $iss,
            ltiUserId: $data['sub'] ?? '',
            nombre: $data['given_name'] ?? ($data['name'] ?? 'Alumno'),
            apellidos: $data['family_name'] ?? 'LTI',
            correo: $data['email'] ?? null,
            idPractica: isset($custom['id_practica']) ? (int) $custom['id_practica'] : null,
            agsLineitemUrl: $ags['lineitem'] ?? null,
            agsEndpoint: $ags['lineitems'] ?? ($ags['lineitem'] ?? null),
            ltiPlatformId: $platform?->id,
        );
    }
}
