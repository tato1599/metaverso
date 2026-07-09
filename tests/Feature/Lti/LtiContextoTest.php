<?php

use App\Lti\DatosLaunch;
use App\Lti\LaunchValidador;
use App\Models\LtiContexto;
use App\Models\LtiPlatform;
use App\Models\Materia;
use App\Models\Practica;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lticontextoPlataforma(): LtiPlatform
{
    return LtiPlatform::create([
        'issuer' => 'http://localhost:8080', 'client_id' => 'CID-CTX',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1', 'activo' => true,
    ]);
}

function lticontextoPractica(): Practica
{
    $mat = Materia::create(['clave' => 'CTX'.fake()->unique()->numberBetween(1, 99999), 'nombre' => 'Prog', 'creditos' => 5]);

    return Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab CTX']);
}

function lticontextoLaunch(array $sobrescribir = []): DatosLaunch
{
    $base = [
        'esDeepLink' => false,
        'issuer' => 'http://localhost:8080',
        'ltiUserId' => 'mu-ctx-1',
        'nombre' => 'Ana', 'apellidos' => 'Ruiz', 'correo' => 'ana@c.com',
        'idPractica' => null,
        'agsLineitemUrl' => null, 'agsEndpoint' => null,
        'ltiPlatformId' => null, 'launchId' => 'launch-ctx',
        'contextId' => 'curso-42', 'contextTitulo' => 'POO 3A',
        'nrpsUrl' => 'http://localhost:8080/mod/lti/services.php/CourseSection/42/bindings/1/memberships',
    ];

    return new DatosLaunch(...array_merge($base, $sobrescribir));
}

function lticontextoFakeValidador(DatosLaunch $datos): void
{
    app()->bind(LaunchValidador::class, fn () => new class($datos) implements LaunchValidador
    {
        public function __construct(private DatosLaunch $d) {}

        public function validar($request): DatosLaunch
        {
            return $this->d;
        }
    });
}

it('captura el contexto del resource launch en lti_contextos', function () {
    $p = lticontextoPlataforma();
    $practica = lticontextoPractica();
    lticontextoFakeValidador(lticontextoLaunch(['ltiPlatformId' => $p->id, 'idPractica' => $practica->id_practica]));

    $this->post('/lti/launch')->assertOk();

    $ctx = LtiContexto::sole();
    expect($ctx->lti_platform_id)->toBe($p->id)
        ->and($ctx->context_id)->toBe('curso-42')
        ->and($ctx->titulo)->toBe('POO 3A')
        ->and($ctx->nrps_url)->toContain('memberships');
});

it('dos launches del mismo contexto dejan UNA fila y actualizan el titulo', function () {
    $p = lticontextoPlataforma();
    $practica = lticontextoPractica();

    lticontextoFakeValidador(lticontextoLaunch(['ltiPlatformId' => $p->id, 'idPractica' => $practica->id_practica]));
    $this->post('/lti/launch')->assertOk();

    lticontextoFakeValidador(lticontextoLaunch([
        'ltiPlatformId' => $p->id, 'idPractica' => $practica->id_practica,
        'contextTitulo' => 'POO 3A (renombrado)',
    ]));
    $this->post('/lti/launch')->assertOk();

    expect(LtiContexto::count())->toBe(1)
        ->and(LtiContexto::sole()->titulo)->toBe('POO 3A (renombrado)');
});

it('un launch sin claim de contexto no truena ni crea filas', function () {
    $p = lticontextoPlataforma();
    $practica = lticontextoPractica();
    lticontextoFakeValidador(lticontextoLaunch([
        'ltiPlatformId' => $p->id, 'idPractica' => $practica->id_practica,
        'contextId' => null, 'contextTitulo' => null, 'nrpsUrl' => null,
    ]));

    $this->post('/lti/launch')->assertOk();

    expect(LtiContexto::count())->toBe(0);
});

it('un deep-link captura el contexto antes de redirigir al selector', function () {
    $p = lticontextoPlataforma();
    lticontextoFakeValidador(lticontextoLaunch(['esDeepLink' => true, 'ltiPlatformId' => $p->id, 'nrpsUrl' => null]));

    $this->post('/lti/launch')->assertRedirect(route('lti.deeplink'));

    $ctx = LtiContexto::sole();
    expect($ctx->context_id)->toBe('curso-42')
        ->and($ctx->nrps_url)->toBeNull();
});

it('un deep-link sin claim NRPS no pisa el nrps_url ya capturado', function () {
    $p = lticontextoPlataforma();
    $practica = lticontextoPractica();

    lticontextoFakeValidador(lticontextoLaunch(['ltiPlatformId' => $p->id, 'idPractica' => $practica->id_practica]));
    $this->post('/lti/launch')->assertOk();

    lticontextoFakeValidador(lticontextoLaunch([
        'esDeepLink' => true, 'ltiPlatformId' => $p->id,
        'contextTitulo' => 'POO 3A (deep-link)', 'nrpsUrl' => null,
    ]));
    $this->post('/lti/launch')->assertRedirect(route('lti.deeplink'));

    $ctx = LtiContexto::sole();
    expect($ctx->nrps_url)->toContain('memberships')
        ->and($ctx->titulo)->toBe('POO 3A (deep-link)');
});
