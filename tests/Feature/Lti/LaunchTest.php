<?php

use App\Lti\DatosLaunch;
use App\Lti\LaunchValidador;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\CicloEscolar;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\LtiContexto;
use App\Models\LtiPlatform;
use App\Models\LtiVinculoActividad;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Practica;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\SesionPractica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function plataformaDemo(): LtiPlatform
{
    return LtiPlatform::create([
        'issuer' => 'http://localhost:8080', 'client_id' => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1', 'activo' => true,
    ]);
}

/**
 * Escenario completo de un launch de alumno: plataforma, contexto capturado,
 * grupo enlazado a ese curso, práctica y —opcionalmente— una fecha agendada.
 *
 * @return array{plataforma: LtiPlatform, practica: Practica, grupo: Grupo, evento: EventoAgenda|null}
 */
function launchConCursoEnlazado(bool $conFecha = true, bool $enlazarGrupo = true): array
{
    $p = plataformaDemo();
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab LTI']);

    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $uM = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'm@lti.test', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $uM->id_usuario, 'numero_empleado' => 'EMP-LTI']);
    $ciclo = CicloEscolar::firstOrCreate(['nombre' => '2026-1'], ['fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);

    $contexto = LtiContexto::create(['lti_platform_id' => $p->id, 'context_id' => 'curso-99', 'titulo' => 'PROG-3A']);
    $grupo = Grupo::create([
        'id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo,
        'clave' => '3A', 'cupo_maximo' => 30,
        'id_lti_contexto' => $enlazarGrupo ? $contexto->id : null,
    ]);

    $evento = $conFecha ? EventoAgenda::create([
        'id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->addDay(), 'fecha_hora_fin' => now()->addDay()->addHour(),
        'estatus' => 'programado', 'cupo_maximo' => 5,
    ]) : null;

    return ['plataforma' => $p, 'practica' => $practica, 'grupo' => $grupo, 'evento' => $evento];
}

function fingirLaunch(array $ctx, ?string $contextId = 'curso-99'): void
{
    $datos = new DatosLaunch(
        esDeepLink: false, issuer: 'http://localhost:8080',
        ltiUserId: 'mu-77', nombre: 'Ana', apellidos: 'Ruiz', correo: 'ana@c.com',
        idPractica: $ctx['practica']->id_practica,
        agsLineitemUrl: 'http://localhost:8080/mod/lti/services.php/.../lineitems/1/lineitem',
        agsEndpoint: 'http://localhost:8080/mod/lti/services.php',
        ltiPlatformId: $ctx['plataforma']->id,
        contextId: $contextId,
    );
    app()->bind(LaunchValidador::class, fn () => new class($datos) implements LaunchValidador
    {
        public function __construct(private $d) {}

        public function validar($request): DatosLaunch
        {
            return $this->d;
        }
    });
}

/*
 * La reserva es obligatoria TAMBIÉN por LTI (decisión que sustituye a la de
 * julio): el launch ya no abre el juego, aterriza en la vista de la práctica.
 * Lo que no puede perderse por el camino es el contexto AGS, porque la sesión
 * de juego se creará después y sin él la calificación no volvería a Moodle.
 */
it('el launch aterriza en la vista de la práctica en vez de abrir el juego', function () {
    $ctx = launchConCursoEnlazado();
    fingirLaunch($ctx);

    $this->post('/lti/launch')
        ->assertRedirect(route('mi.eventos.show', $ctx['evento']->id_evento));

    // Ya no se crea sesión en el launch: nace cuando el alumno entra a jugar.
    expect(SesionPractica::count())->toBe(0);
    expect(Usuario::where('lti_user_id', 'mu-77')->count())->toBe(1);
});

it('deja al alumno con sesión web y lo inscribe al grupo del curso', function () {
    $ctx = launchConCursoEnlazado();
    fingirLaunch($ctx);

    $this->post('/lti/launch');

    $usuario = Usuario::where('lti_user_id', 'mu-77')->firstOrFail();
    $this->assertAuthenticatedAs($usuario);

    // Sin esto, quien entra antes de que el maestro sincronice el roster
    // se toparía con un 403 en su propia práctica.
    expect(Inscripcion::where('id_alumno', $usuario->alumno->id_alumno)
        ->where('id_grupo', $ctx['grupo']->id_grupo)
        ->where('estatus', 'activa')
        ->exists())->toBeTrue();
});

it('guarda el vínculo AGS para que la calificación pueda volver más tarde', function () {
    $ctx = launchConCursoEnlazado();
    fingirLaunch($ctx);

    $this->post('/lti/launch');

    $alumno = Usuario::where('lti_user_id', 'mu-77')->firstOrFail()->alumno;
    $vinculo = LtiVinculoActividad::where('id_alumno', $alumno->id_alumno)
        ->where('id_practica', $ctx['practica']->id_practica)
        ->firstOrFail();

    expect($vinculo->ags_lineitem_url)->not->toBeNull()
        ->and($vinculo->ags_endpoint)->toBe('http://localhost:8080/mod/lti/services.php')
        ->and($vinculo->lti_platform_id)->toBe($ctx['plataforma']->id);

    // Y un segundo launch lo refresca en lugar de duplicarlo.
    $this->post('/lti/launch');
    expect(LtiVinculoActividad::count())->toBe(1);
});

it('explica el problema cuando el curso de Moodle no está enlazado a un grupo', function () {
    $ctx = launchConCursoEnlazado(enlazarGrupo: false);
    fingirLaunch($ctx);

    $this->post('/lti/launch')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Mi/PracticaNoDisponible')
            ->where('motivo', 'curso-sin-grupo')
        );
});

it('explica el problema cuando la práctica todavía no tiene fechas', function () {
    $ctx = launchConCursoEnlazado(conFecha: false);
    fingirLaunch($ctx);

    $this->post('/lti/launch')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Mi/PracticaNoDisponible')
            ->where('motivo', 'sin-fechas')
            ->where('curso', $ctx['grupo']->clave)
        );
});

it('un deep-link launch redirige al selector y guarda lti_launch_id en sesion', function () {
    plataformaDemo();
    $datos = new DatosLaunch(
        esDeepLink: true, issuer: 'http://localhost:8080', ltiUserId: 'mu-1',
        nombre: 'M', apellidos: 'X', correo: null,
        idPractica: null, agsLineitemUrl: null, agsEndpoint: null, ltiPlatformId: null,
        launchId: 'launch-abc',
    );
    $this->app->bind(LaunchValidador::class, fn () => new class($datos) implements LaunchValidador
    {
        public function __construct(private $d) {}

        public function validar($request): DatosLaunch
        {
            return $this->d;
        }
    });
    $r = $this->post('/lti/launch');
    $r->assertRedirect(route('lti.deeplink'));
    expect(session('lti_launch_id'))->toBe('launch-abc');
});

/*
 * La prueba que cierra el circuito. Con la reserva de por medio, la sesión de
 * juego nace días después del launch y desde otra ruta (la API del juego). Si
 * el contexto AGS no viajara en el vínculo de la actividad, la calificación se
 * quedaría sin destino y no volvería nunca al libro de Moodle.
 */
it('una sesión creada tras reservar hereda el AGS del launch', function () {
    $ctx = launchConCursoEnlazado();
    fingirLaunch($ctx);
    $this->post('/lti/launch');

    $usuario = Usuario::where('lti_user_id', 'mu-77')->firstOrFail();
    $alumno = $usuario->alumno;

    // El juego solo abre sesión dentro de la ventana del evento.
    $ctx['evento']->update([
        'fecha_hora_inicio' => now()->subMinutes(5),
        'fecha_hora_fin' => now()->addHour(),
    ]);

    // El alumno reserva y, más tarde, el juego abre la sesión con su bearer.
    Reserva::create([
        'id_evento' => $ctx['evento']->id_evento,
        'id_alumno' => $alumno->id_alumno,
        'inicio_slot' => $ctx['evento']->fresh()->fecha_hora_inicio,
    ]);

    Sanctum::actingAs($usuario, ['game', 'evento:'.$ctx['evento']->id_evento]);
    $this->postJson('/api/game/sessions', ['id_evento' => $ctx['evento']->id_evento])
        ->assertCreated();

    $sesion = SesionPractica::firstOrFail();

    expect($sesion->ags_lineitem_url)->not->toBeNull()
        ->and($sesion->ags_endpoint)->toBe('http://localhost:8080/mod/lti/services.php')
        ->and($sesion->lti_platform_id)->toBe($ctx['plataforma']->id)
        // Y ahora sí queda ligada a su evento, cosa que el camino viejo no hacía.
        ->and($sesion->id_evento)->toBe($ctx['evento']->id_evento);
});

it('una sesión sin launch previo no inventa un destino AGS', function () {
    $ctx = launchConCursoEnlazado();
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'solo.web@tecnm.mx', 'nombre' => 'Web', 'apellidos' => 'Only']);
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);
    $alumno = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '2025X', 'semestre_actual' => 1, 'generacion' => '2025']);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $ctx['grupo']->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);
    $ctx['evento']->update(['fecha_hora_inicio' => now()->subMinutes(5), 'fecha_hora_fin' => now()->addHour()]);

    Sanctum::actingAs($u, ['game']);
    $this->postJson('/api/game/sessions', ['id_evento' => $ctx['evento']->id_evento])->assertCreated();

    expect(SesionPractica::firstOrFail()->ags_lineitem_url)->toBeNull();
});
