<?php

namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Lti\LtiCache;
use App\Lti\LtiCookie;
use App\Lti\LtiDatabase;
use Illuminate\Http\Request;
use Packback\Lti1p3\LtiOidcLogin;

class LtiLoginController extends Controller
{
    public function login(Request $request)
    {
        $url = LtiOidcLogin::new(new LtiDatabase(), new LtiCache(), new LtiCookie())
            ->getRedirectUrl(route('lti.launch'), $request->all());

        return redirect()->away($url);
    }
}
