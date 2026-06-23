<?php
use App\Lti\{DatosLaunch, LaunchValidador};
use App\Models\{LtiPlatform, Materia, Practica, SesionPractica, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function plataformaDemo(): LtiPlatform {
    return LtiPlatform::create([
        'issuer' => 'http://localhost:8080', 'client_id' => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1', 'activo' => true,
    ]);
}

it('un resource launch crea sesion LTI y muestra Abrir juego', function () {
    $p = plataformaDemo();
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab LTI']);

    $datos = new DatosLaunch(
        esDeepLink: false, issuer: 'http://localhost:8080',
        ltiUserId: 'mu-77', nombre: 'Ana', apellidos: 'Ruiz', correo: 'ana@c.com',
        idPractica: $practica->id_practica,
        agsLineitemUrl: 'http://localhost:8080/mod/lti/services.php/.../lineitems/1/lineitem',
        agsEndpoint: 'http://localhost:8080/mod/lti/services.php', ltiPlatformId: $p->id,
    );
    $this->app->bind(LaunchValidador::class, fn () => new class($datos) implements LaunchValidador {
        public function __construct(private $d) {}
        public function validar($request): DatosLaunch { return $this->d; }
    });

    $r = $this->post('/lti/launch');
    $r->assertOk()->assertSee('Abrir juego');

    expect(Usuario::where('lti_user_id', 'mu-77')->count())->toBe(1);
    $sesion = SesionPractica::first();
    expect($sesion->id_practica)->toBe($practica->id_practica);
    expect($sesion->id_evento)->toBeNull();
    expect($sesion->ags_lineitem_url)->not->toBeNull();
    expect($sesion->lti_platform_id)->toBe($p->id);
});

it('un deep-link launch redirige al selector', function () {
    plataformaDemo();
    $datos = new DatosLaunch(true, 'http://localhost:8080', 'mu-1', 'M', 'X', null, null, null, null, null);
    $this->app->bind(LaunchValidador::class, fn () => new class($datos) implements LaunchValidador {
        public function __construct(private $d) {}
        public function validar($request): DatosLaunch { return $this->d; }
    });
    $this->post('/lti/launch')->assertRedirect(route('lti.deeplink'));
});
