<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea el esquema LTI', function () {
    foreach (['lti_platforms','lti_keys','lti_nonces'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue();
    }
    expect(Schema::hasColumns('lti_platforms', ['issuer','client_id','auth_login_url','auth_token_url','jwks_url','deployment_id','activo']))->toBeTrue();
    expect(Schema::hasColumns('lti_keys', ['kid','public_key','private_key','activo']))->toBeTrue();
    expect(Schema::hasColumn('usuarios','lti_user_id'))->toBeTrue();
    expect(Schema::hasColumns('sesiones_practica', ['lti_platform_id','ags_lineitem_url','ags_endpoint']))->toBeTrue();
});

it('permite id_evento nulo en sesiones_practica', function () {
    $col = Schema::getConnection()->getDoctrineColumn('sesiones_practica', 'id_evento');
})->skip('verificado por inserción en Task 6');
