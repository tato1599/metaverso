<?php
use App\Models\LtiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('genera un par de llaves con el comando', function () {
    $this->artisan('metaverso:lti-generar-llaves')->assertSuccessful();
    $k = LtiKey::first();
    expect($k)->not->toBeNull();
    expect($k->kid)->not->toBeEmpty();
    expect($k->private_key)->toContain('PRIVATE KEY');
    expect($k->public_key)->toContain('PUBLIC KEY');
});

it('publica un JWKS valido con la llave publica', function () {
    $this->artisan('metaverso:lti-generar-llaves')->assertSuccessful();
    $r = $this->get('/lti/jwks');
    $r->assertOk()->assertJsonStructure(['keys' => [['kty','e','n','kid','alg','use']]]);
    expect($r->json('keys.0.alg'))->toBe('RS256');
    expect($r->json('keys.0.kid'))->toBe(LtiKey::first()->kid);
});
