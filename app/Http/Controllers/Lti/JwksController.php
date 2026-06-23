<?php
namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Models\LtiKey;

class JwksController extends Controller {
    public function index() {
        $keys = LtiKey::where('activo', true)->get()
            ->map(function (LtiKey $k) {
                $res = openssl_pkey_get_public($k->public_key);
                if ($res === false) return null;
                $details = openssl_pkey_get_details($res);
                if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) return null;
                return [
                    'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $k->kid,
                    'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                    'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
                ];
            })
            ->filter()
            ->values();
        return response()->json(['keys' => $keys]);
    }
}
