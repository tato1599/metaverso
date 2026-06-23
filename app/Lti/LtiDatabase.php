<?php

namespace App\Lti;

use App\Models\LtiKey;
use App\Models\LtiPlatform;
use Packback\Lti1p3\Interfaces\IDatabase;
use Packback\Lti1p3\Interfaces\ILtiDeployment;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\LtiDeployment;
use Packback\Lti1p3\LtiRegistration;

class LtiDatabase implements IDatabase
{
    public function findRegistrationByIssuer(string $iss, ?string $clientId = null): ?ILtiRegistration
    {
        $query = LtiPlatform::where('issuer', $iss)->where('activo', true);
        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }
        $platform = $query->first();

        if (! $platform) {
            return null;
        }

        $key = LtiKey::where('activo', true)->orderByDesc('id')->first();
        if (! $key) {
            return null;
        }

        return LtiRegistration::new()
            ->setIssuer($platform->issuer)
            ->setClientId($platform->client_id)
            ->setAuthLoginUrl($platform->auth_login_url)
            ->setAuthTokenUrl($platform->auth_token_url)
            ->setKeySetUrl($platform->jwks_url)
            ->setKid($key->kid)
            ->setToolPrivateKey($key->private_key);
    }

    public function findDeployment(string $iss, string $deploymentId, ?string $clientId = null): ?ILtiDeployment
    {
        $query = LtiPlatform::where('issuer', $iss)
            ->where('deployment_id', $deploymentId)
            ->where('activo', true);

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        if (! $query->exists()) {
            return null;
        }

        return LtiDeployment::new($deploymentId);
    }
}
