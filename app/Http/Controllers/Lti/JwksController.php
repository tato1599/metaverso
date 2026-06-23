<?php
namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Models\LtiKey;

class JwksController extends Controller {
    public function index() {
        $keys = LtiKey::where('activo', true)->get()->map(function (LtiKey $k) {
            $details = openssl_pkey_get_details(openssl_pkey_get_public($k->public_key));
            return [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $k->kid,
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ];
        })->values();
        return response()->json(['keys' => $keys]);
    }
}
