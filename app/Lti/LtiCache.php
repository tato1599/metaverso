<?php

namespace App\Lti;

use Illuminate\Support\Facades\Cache;
use Packback\Lti1p3\Interfaces\ICache;

class LtiCache implements ICache
{
    private const TTL = 3600; // 1 hour in seconds

    public function getLaunchData(string $key): ?array
    {
        return Cache::get('lti_launch_' . $key);
    }

    public function cacheLaunchData(string $key, array $jwtBody): void
    {
        Cache::put('lti_launch_' . $key, $jwtBody, self::TTL);
    }

    public function cacheNonce(string $nonce, string $state): void
    {
        Cache::put('lti_nonce_' . $nonce, $state, self::TTL);
    }

    public function checkNonceIsValid(string $nonce, string $state): bool
    {
        $stored = Cache::pull('lti_nonce_' . $nonce);

        return $stored === $state;
    }

    public function cacheAccessToken(string $key, string $accessToken): void
    {
        Cache::put('lti_token_' . $key, $accessToken, self::TTL);
    }

    public function getAccessToken(string $key): ?string
    {
        return Cache::get('lti_token_' . $key);
    }

    public function clearAccessToken(string $key): void
    {
        Cache::forget('lti_token_' . $key);
    }
}
