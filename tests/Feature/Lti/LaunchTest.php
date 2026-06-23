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
    $r->assertSee('lti_session_token=', false);

    expect(Usuario::where('lti_user_id', 'mu-77')->count())->toBe(1);
    $sesion = SesionPractica::first();
    expect($sesion->id_practica)->toBe($practica->id_practica);
    expect($sesion->id_evento)->toBeNull();
    expect($sesion->ags_lineitem_url)->not->toBeNull();
    expect($sesion->lti_platform_id)->toBe($p->id);
    expect($sesion->estatus)->toBe('en_progreso');
    expect($sesion->ags_endpoint)->toBe('http://localhost:8080/mod/lti/services.php');

    // El deeplink lleva un token de juego ligado a la sesión via cache.
    $html = $r->getContent();
    preg_match('/lti_session_token=([A-Za-z0-9]+)/', $html, $m);
    expect($m[1] ?? null)->not->toBeNull();
    expect(\Illuminate\Support\Facades\Cache::get('lti_play_'.hash('sha256', $m[1])))->toBe($sesion->id_sesion);
});

it('un deep-link launch redirige al selector y guarda lti_launch_id en sesion', function () {
    plataformaDemo();
    $datos = new DatosLaunch(
        esDeepLink: true, issuer: 'http://localhost:8080', ltiUserId: 'mu-1',
        nombre: 'M', apellidos: 'X', correo: null,
        idPractica: null, agsLineitemUrl: null, agsEndpoint: null, ltiPlatformId: null,
        launchId: 'launch-abc',
    );
    $this->app->bind(LaunchValidador::class, fn () => new class($datos) implements LaunchValidador {
        public function __construct(private $d) {}
        public function validar($request): DatosLaunch { return $this->d; }
    });
    $r = $this->post('/lti/launch');
    $r->assertRedirect(route('lti.deeplink'));
    expect(session('lti_launch_id'))->toBe('launch-abc');
});
