<?php

use App\Models\{LtiPlatform, LtiKey};
use App\Lti\LtiDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resuelve un registration desde una LtiPlatform y la LtiKey activa', function () {
    LtiKey::create([
        'kid' => 'kid-1',
        'public_key' => 'PUB',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nX\n-----END PRIVATE KEY-----",
        'activo' => true,
    ]);

    LtiPlatform::create([
        'issuer'         => 'http://localhost:8080',
        'client_id'      => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url'       => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id'  => 'DEP1',
        'activo'         => true,
    ]);

    $db = new LtiDatabase();
    $reg = $db->findRegistrationByIssuer('http://localhost:8080', 'CID');

    expect($reg)->not->toBeNull();
    expect($reg->getClientId())->toBe('CID');
    expect($reg->getAuthLoginUrl())->toBe('http://localhost:8080/mod/lti/auth.php');
    expect($reg->getKeySetUrl())->toBe('http://localhost:8080/mod/lti/certs.php');
    expect($reg->getKid())->toBe('kid-1');
    expect($reg->getToolPrivateKey())->toBe("-----BEGIN PRIVATE KEY-----\nX\n-----END PRIVATE KEY-----");

    $dep = $db->findDeployment('http://localhost:8080', 'DEP1', 'CID');
    expect($dep)->not->toBeNull();
    expect($dep->getDeploymentId())->toBe('DEP1');
});

it('retorna null cuando la plataforma no existe', function () {
    $db = new LtiDatabase();
    $reg = $db->findRegistrationByIssuer('http://no-existe.com', null);
    expect($reg)->toBeNull();
});

it('retorna null cuando el deployment no existe', function () {
    LtiPlatform::create([
        'issuer'         => 'http://localhost:8080',
        'client_id'      => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url'       => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id'  => 'DEP1',
        'activo'         => true,
    ]);

    $db = new LtiDatabase();
    $dep = $db->findDeployment('http://localhost:8080', 'DEP-INEXISTENTE', 'CID');
    expect($dep)->toBeNull();
});
