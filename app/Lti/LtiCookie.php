<?php

namespace App\Lti;

use Packback\Lti1p3\Interfaces\ICookie;

class LtiCookie implements ICookie
{
    public function getCookie(string $name): ?string
    {
        return $_COOKIE[$name] ?? null;
    }

    public function setCookie(string $name, string $value, int $exp = 3600, array $options = []): void
    {
        setcookie($name, $value, array_merge([
            'expires'  => time() + $exp,
            'path'     => '/',
            'samesite' => 'None',
            'secure'   => true,
            'httponly' => true,
        ], $options));

        // Mirror to superglobal so getCookie works within the same request
        $_COOKIE[$name] = $value;
    }
}
