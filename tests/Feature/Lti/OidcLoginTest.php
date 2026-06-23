<?php
use App\Models\{LtiPlatform, LtiKey};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    LtiKey::create(['kid' => 'k1', 'public_key' => 'PUB', 'private_key' => 'PRIV', 'activo' => true]);
    LtiPlatform::create([
        'issuer' => 'http://localhost:8080', 'client_id' => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1', 'activo' => true,
    ]);
});

it('redirige el OIDC login a la plataforma con los parametros requeridos', function () {
    $params = [
        'iss' => 'http://localhost:8080',
        'login_hint' => 'user-123',
        'target_link_uri' => route('lti.launch'),
        'client_id' => 'CID',
        'lti_deployment_id' => 'DEP1',
    ];
    $r = $this->get('/lti/login?'.http_build_query($params));
    $r->assertRedirect();
    expect($r->headers->get('Location'))->toContain('http://localhost:8080/mod/lti/auth.php');
    expect($r->headers->get('Location'))->toContain('redirect_uri=');
    expect($r->headers->get('Location'))->toContain('client_id=CID');
});

it('redirige el OIDC login por POST tambien', function () {
    $params = [
        'iss' => 'http://localhost:8080',
        'login_hint' => 'user-123',
        'target_link_uri' => route('lti.launch'),
        'client_id' => 'CID',
        'lti_deployment_id' => 'DEP1',
    ];
    $r = $this->post('/lti/login', $params);
    $r->assertRedirect();
    expect($r->headers->get('Location'))->toContain('http://localhost:8080/mod/lti/auth.php');
    expect($r->headers->get('Location'))->toContain('client_id=CID');
});
