<?php
use App\Models\LtiPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registra (upsert) una plataforma', function () {
    $this->artisan('metaverso:lti-registrar-plataforma', [
        '--issuer' => 'http://localhost:8080',
        '--client-id' => 'CID',
        '--deployment-id' => 'DEP1',
        '--auth-login-url' => 'http://localhost:8080/mod/lti/auth.php',
        '--auth-token-url' => 'http://localhost:8080/mod/lti/token.php',
        '--jwks-url' => 'http://localhost:8080/mod/lti/certs.php',
    ])->assertSuccessful();

    $p = LtiPlatform::where('issuer', 'http://localhost:8080')->where('client_id', 'CID')->first();
    expect($p)->not->toBeNull();
    expect($p->deployment_id)->toBe('DEP1');

    // upsert: segunda llamada no duplica
    $this->artisan('metaverso:lti-registrar-plataforma', [
        '--issuer' => 'http://localhost:8080', '--client-id' => 'CID', '--deployment-id' => 'DEP2',
        '--auth-login-url' => 'x', '--auth-token-url' => 'y', '--jwks-url' => 'z',
    ])->assertSuccessful();
    expect(LtiPlatform::where('issuer', 'http://localhost:8080')->where('client_id', 'CID')->count())->toBe(1);
    expect(LtiPlatform::where('client_id', 'CID')->first()->deployment_id)->toBe('DEP2');
});
