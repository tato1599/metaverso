<?php
namespace App\Lti;

use App\Models\LtiPlatform;
use App\Models\SesionPractica;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Packback\Lti1p3\LtiAssignmentsGradesService;
use Packback\Lti1p3\LtiGrade;
use Packback\Lti1p3\LtiLineitem;
use Packback\Lti1p3\LtiServiceConnector;

class EnviarCalificacionAgs implements AgsCliente
{
    public function enviar(SesionPractica $sesion): void
    {
        $sesion->loadMissing('alumno.usuario');
        $ltiUserId = optional(optional($sesion->alumno)->usuario)->lti_user_id;

        if (! $ltiUserId || ! $sesion->ags_lineitem_url) {
            return;
        }

        try {
            $platform = LtiPlatform::find($sesion->lti_platform_id);
            if (! $platform) {
                Log::warning('AGS: plataforma LTI no encontrada', ['lti_platform_id' => $sesion->lti_platform_id]);
                return;
            }

            $db = new LtiDatabase();
            $registration = $db->findRegistrationByIssuer($platform->issuer);

            if (! $registration) {
                Log::warning('AGS: registro LTI no encontrado', ['issuer' => $platform->issuer]);
                return;
            }

            $connector = new LtiServiceConnector(new LtiCache(), new Client([
                'timeout'         => 10.0,
                'connect_timeout' => 5.0,
            ]));

            $ags = new LtiAssignmentsGradesService($connector, $registration, [
                'lineitem'  => $sesion->ags_lineitem_url,
                'lineitems' => $sesion->ags_endpoint,
                'scope'     => [
                    'https://purl.imsglobal.org/spec/lti-ags/scope/score',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                ],
            ]);

            $grade = LtiGrade::new()
                ->setScoreGiven((float) $sesion->calificacion)
                ->setScoreMaximum(100.0)
                ->setActivityProgress('Completed')
                ->setGradingProgress('FullyGraded')
                ->setUserId($ltiUserId)
                ->setTimestamp(now()->toIso8601String());

            $lineitem = LtiLineitem::new()->setId($sesion->ags_lineitem_url);

            $ags->putGrade($grade, $lineitem);
        } catch (\Throwable $e) {
            Log::warning('AGS: error al enviar calificacion a Moodle', [
                'id_sesion'       => $sesion->id_sesion,
                'ags_lineitem_url' => $sesion->ags_lineitem_url,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
