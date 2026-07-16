<?php
use App\Lti\{DatosLaunch, LaunchValidador};
use App\Models\{LtiPlatform, Materia, Practica};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('la pantalla de abrir juego muestra la escena de la practica', function () {
    $p = LtiPlatform::create([
        'issuer' => 'http://localhost:8080', 'client_id' => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1', 'activo' => true,
    ]);
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $practica = Practica::create([
        'id_materia' => $mat->id_materia,
        'titulo' => 'Lab LTI',
        'escena_referencia' => 'recolecta',
    ]);

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

    $respuesta = $this->post('/lti/launch');

    $respuesta->assertOk()->assertSee('recolecta');
});
