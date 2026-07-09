<?php

use App\Lti\RosterCliente;
use App\Lti\SincronizarRosterMoodle;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\CicloEscolar;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\LtiContexto;
use App\Models\LtiPlatform;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function rosterltiUsuario(string $rol): Usuario
{
    $r = Rol::firstOrCreate(['nombre' => $rol]);

    return Usuario::create([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('x'),
        'nombre' => 'T',
        'apellidos' => 'U',
    ]);
}

function rosterltiContexto(array $atributos = []): LtiContexto
{
    $plataforma = LtiPlatform::create([
        'issuer' => 'http://localhost:8080',
        'client_id' => 'CID-'.fake()->unique()->numberBetween(1, 99999),
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1',
        'activo' => true,
    ]);

    return LtiContexto::create(array_merge([
        'lti_platform_id' => $plataforma->id,
        'context_id' => 'curso-'.fake()->unique()->numberBetween(1, 99999),
        'titulo' => 'Curso Moodle',
        'nrps_url' => 'http://localhost:8080/mod/lti/services.php/CourseSection/42/bindings/1/memberships',
    ], $atributos));
}

function rosterltiGrupo(?LtiContexto $contexto = null): Grupo
{
    $materia = Materia::create(['clave' => 'RL'.fake()->unique()->numberBetween(1, 99999), 'nombre' => 'Materia RL', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-30']);
    $maestro = Maestro::create([
        'id_usuario' => rosterltiUsuario('Maestro')->id_usuario,
        'numero_empleado' => 'RL'.fake()->unique()->numberBetween(1, 99999),
    ]);

    return Grupo::create([
        'id_materia' => $materia->id_materia,
        'id_maestro' => $maestro->id_maestro,
        'id_ciclo' => $ciclo->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 30,
        'id_lti_contexto' => $contexto?->id,
    ]);
}

function rosterltiMember(array $sobrescribir = []): array
{
    return array_merge([
        'user_id' => 'mu-'.fake()->unique()->numberBetween(1, 999999),
        'status' => 'Active',
        'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
        'name' => 'Ana Ruiz',
        'given_name' => 'Ana',
        'family_name' => 'Ruiz',
        'email' => fake()->unique()->safeEmail(),
    ], $sobrescribir);
}

function rosterltiFakeRoster(array $members): void
{
    app()->instance(RosterCliente::class, new class($members) implements RosterCliente
    {
        public function __construct(private array $members) {}

        public function getMembers(Grupo $grupo): array
        {
            return $this->members;
        }
    });
}

it('sincroniza el roster: crea usuarios, alumnos e inscripciones activas', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    rosterltiFakeRoster([
        rosterltiMember(['user_id' => 'mu-r1', 'given_name' => 'Ana', 'family_name' => 'Ruiz']),
        rosterltiMember(['user_id' => 'mu-r2', 'given_name' => 'Beto', 'family_name' => 'Diaz']),
    ]);

    $this->actingAs(rosterltiUsuario('Coordinador'))
        ->post("/admin/grupos/{$grupo->id_grupo}/sincronizar")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Usuario::where('lti_user_id', 'mu-r1')->count())->toBe(1)
        ->and(Alumno::whereHas('usuario', fn ($q) => $q->whereIn('lti_user_id', ['mu-r1', 'mu-r2']))->count())->toBe(2)
        ->and(Inscripcion::where('id_grupo', $grupo->id_grupo)->where('estatus', 'activa')->count())->toBe(2)
        ->and(session('success'))->toContain('2 alumnos nuevos');
});

it('un member Instructor crea maestro con numero_empleado LTI- completo y dos syncs no lo duplican', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    $ltiUserId = 'f1a2b3c4-5678-90ab-cdef-1234567890ab';
    rosterltiFakeRoster([
        rosterltiMember([
            'user_id' => $ltiUserId,
            'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor'],
            'given_name' => 'Rosa', 'family_name' => 'Lopez',
        ]),
    ]);
    $sincronizador = app(SincronizarRosterMoodle::class);

    $resumen = $sincronizador->sincronizar($grupo);
    expect($resumen['maestros'])->toBe(1);

    $maestro = Maestro::whereHas('usuario', fn ($q) => $q->where('lti_user_id', $ltiUserId))->sole();
    expect($maestro->numero_empleado)->toBe('LTI-'.$ltiUserId);

    app(SincronizarRosterMoodle::class)->sincronizar($grupo);
    expect(Maestro::where('numero_empleado', 'LTI-'.$ltiUserId)->count())->toBe(1)
        ->and(Usuario::where('lti_user_id', $ltiUserId)->count())->toBe(1);
});

it('un member con ambos roles cuenta como Instructor y no se inscribe como alumno', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    rosterltiFakeRoster([
        rosterltiMember([
            'user_id' => 'mu-ambos',
            'roles' => [
                'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner',
                'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor',
            ],
        ]),
    ]);

    $resumen = app(SincronizarRosterMoodle::class)->sincronizar($grupo);

    expect($resumen['maestros'])->toBe(1)
        ->and($resumen['alumnos_nuevos'])->toBe(0)
        ->and(Inscripcion::where('id_grupo', $grupo->id_grupo)->count())->toBe(0);
});

it('un member Inactive no se inscribe y va al reporte', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    rosterltiFakeRoster([
        rosterltiMember(['user_id' => 'mu-inactivo', 'status' => 'Inactive', 'name' => 'Ines Activa']),
    ]);

    $resumen = app(SincronizarRosterMoodle::class)->sincronizar($grupo);

    expect($resumen['inactivos'])->toBe(['Ines Activa'])
        ->and($resumen['alumnos_nuevos'])->toBe(0)
        ->and(Usuario::where('lti_user_id', 'mu-inactivo')->count())->toBe(0)
        ->and(Inscripcion::where('id_grupo', $grupo->id_grupo)->count())->toBe(0);
});

it('reconoce la URI completa y la forma corta de los roles', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    rosterltiFakeRoster([
        rosterltiMember(['user_id' => 'mu-uri', 'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner']]),
        rosterltiMember(['user_id' => 'mu-corto', 'roles' => ['Learner']]),
        rosterltiMember(['user_id' => 'mu-inst-corto', 'roles' => ['Instructor']]),
    ]);

    $resumen = app(SincronizarRosterMoodle::class)->sincronizar($grupo);

    expect($resumen['alumnos_nuevos'])->toBe(2)
        ->and($resumen['maestros'])->toBe(1)
        ->and(Inscripcion::where('id_grupo', $grupo->id_grupo)->where('estatus', 'activa')->count())->toBe(2);
});

it('dos syncs seguidos son idempotentes: el segundo reporta sin cambio', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    rosterltiFakeRoster([
        rosterltiMember(['user_id' => 'mu-idem-1']),
        rosterltiMember(['user_id' => 'mu-idem-2']),
    ]);
    $sincronizador = app(SincronizarRosterMoodle::class);

    $primero = $sincronizador->sincronizar($grupo);
    $segundo = app(SincronizarRosterMoodle::class)->sincronizar($grupo);

    expect($primero['alumnos_nuevos'])->toBe(2)
        ->and($segundo['alumnos_nuevos'])->toBe(0)
        ->and($segundo['sin_cambio'])->toBe(2)
        ->and($segundo['en_moodle_no_locales'])->toBe([])
        ->and(Inscripcion::where('id_grupo', $grupo->id_grupo)->count())->toBe(2)
        ->and(Alumno::count())->toBe(2);
});

it('reactiva una inscripcion en baja cuando el alumno sigue en Moodle', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    $carrera = Carrera::firstOrCreate(['clave' => 'LTI'], ['nombre' => 'Externa (LTI)', 'duracion_semestres' => 1]);
    $usuario = rosterltiUsuario('Alumno');
    $usuario->update(['lti_user_id' => 'mu-baja']);
    $alumno = Alumno::create([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'matricula' => 'LTI-mu-baja',
        'semestre_actual' => 1,
        'generacion' => '2026',
    ]);
    Inscripcion::create([
        'id_alumno' => $alumno->id_alumno,
        'id_grupo' => $grupo->id_grupo,
        'fecha_inscripcion' => '2026-01-15',
        'estatus' => 'baja',
    ]);
    rosterltiFakeRoster([rosterltiMember(['user_id' => 'mu-baja'])]);

    $resumen = app(SincronizarRosterMoodle::class)->sincronizar($grupo);

    $inscripcion = Inscripcion::where('id_alumno', $alumno->id_alumno)->where('id_grupo', $grupo->id_grupo)->sole();
    expect($resumen['alumnos_reactivados'])->toBe(1)
        ->and($inscripcion->estatus)->toBe('activa');
});

it('reporta locales que ya no estan en Moodle sin darlos de baja', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    $carrera = Carrera::firstOrCreate(['clave' => 'LTI'], ['nombre' => 'Externa (LTI)', 'duracion_semestres' => 1]);
    $usuario = rosterltiUsuario('Alumno');
    $usuario->update(['lti_user_id' => 'mu-ya-no-esta', 'nombre' => 'Sofia', 'apellidos' => 'Vega']);
    $alumno = Alumno::create([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'matricula' => 'LTI-mu-ya-no-esta',
        'semestre_actual' => 1,
        'generacion' => '2026',
    ]);
    $inscripcion = Inscripcion::create([
        'id_alumno' => $alumno->id_alumno,
        'id_grupo' => $grupo->id_grupo,
        'fecha_inscripcion' => '2026-01-15',
        'estatus' => 'activa',
    ]);
    rosterltiFakeRoster([rosterltiMember(['user_id' => 'mu-otro'])]);

    $resumen = app(SincronizarRosterMoodle::class)->sincronizar($grupo);

    expect($resumen['locales_no_en_moodle'])->toBe(['Sofia Vega'])
        ->and($inscripcion->fresh()->estatus)->toBe('activa');
});

it('un member sin email no truena y genera correo deterministico', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    $member = rosterltiMember(['user_id' => 'mu-sin-mail']);
    unset($member['email']);
    rosterltiFakeRoster([$member]);

    $resumen = app(SincronizarRosterMoodle::class)->sincronizar($grupo);

    expect($resumen['alumnos_nuevos'])->toBe(1)
        ->and(Usuario::where('lti_user_id', 'mu-sin-mail')->sole()->correo)->toContain('lti+mu-sin-mail@');
});

it('regresa 422 legible si el grupo no tiene contexto o el contexto no tiene NRPS', function () {
    rosterltiFakeRoster([]);
    $coordinador = rosterltiUsuario('Coordinador');

    $sinContexto = rosterltiGrupo();
    $this->actingAs($coordinador)
        ->postJson("/admin/grupos/{$sinContexto->id_grupo}/sincronizar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sincronizar');

    $sinNrps = rosterltiGrupo(rosterltiContexto(['nrps_url' => null]));
    $this->actingAs($coordinador)
        ->postJson("/admin/grupos/{$sinNrps->id_grupo}/sincronizar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sincronizar');
});

it('protege el endpoint de sincronizacion para coordinador o admin', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());

    $this->post("/admin/grupos/{$grupo->id_grupo}/sincronizar")->assertRedirect('/login');
    $this->actingAs(rosterltiUsuario('Maestro'))
        ->post("/admin/grupos/{$grupo->id_grupo}/sincronizar")
        ->assertForbidden();
});

it('el comando artisan sincroniza y reporta el resumen', function () {
    $grupo = rosterltiGrupo(rosterltiContexto());
    rosterltiFakeRoster([rosterltiMember(['user_id' => 'mu-cli'])]);

    $this->artisan('metaverso:sincronizar-roster', ['id_grupo' => $grupo->id_grupo])
        ->expectsOutputToContain('Alumnos nuevos: 1')
        ->assertSuccessful();

    expect(Inscripcion::where('id_grupo', $grupo->id_grupo)->where('estatus', 'activa')->count())->toBe(1);
});

it('el comando falla legible con grupo inexistente o sin contexto', function () {
    rosterltiFakeRoster([]);

    $this->artisan('metaverso:sincronizar-roster', ['id_grupo' => 999999])
        ->expectsOutputToContain('Grupo no encontrado.')
        ->assertFailed();

    $sinContexto = rosterltiGrupo();
    $this->artisan('metaverso:sincronizar-roster', ['id_grupo' => $sinContexto->id_grupo])
        ->expectsOutputToContain('El grupo no tiene un curso de Moodle vinculado.')
        ->assertFailed();
});

it('acepta id_lti_contexto nullable en crear y editar grupo, y valida que exista', function () {
    $coordinador = rosterltiUsuario('Coordinador');
    $contexto = rosterltiContexto();
    $base = rosterltiGrupo();

    $this->actingAs($coordinador)->post('/admin/grupos', [
        'id_materia' => $base->id_materia,
        'id_maestro' => $base->id_maestro,
        'id_ciclo' => $base->id_ciclo,
        'clave' => '7C',
        'cupo_maximo' => 20,
        'id_lti_contexto' => $contexto->id,
    ])->assertRedirect();
    expect(Grupo::where('clave', '7C')->sole()->id_lti_contexto)->toBe($contexto->id);

    $this->actingAs($coordinador)->put("/admin/grupos/{$base->id_grupo}", [
        'id_materia' => $base->id_materia,
        'id_maestro' => $base->id_maestro,
        'id_ciclo' => $base->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 30,
        'id_lti_contexto' => $contexto->id,
    ])->assertRedirect();
    expect($base->fresh()->id_lti_contexto)->toBe($contexto->id);

    $this->actingAs($coordinador)->put("/admin/grupos/{$base->id_grupo}", [
        'id_materia' => $base->id_materia,
        'id_maestro' => $base->id_maestro,
        'id_ciclo' => $base->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 30,
        'id_lti_contexto' => '',
    ])->assertRedirect();
    expect($base->fresh()->id_lti_contexto)->toBeNull();

    $this->actingAs($coordinador)->putJson("/admin/grupos/{$base->id_grupo}", [
        'id_materia' => $base->id_materia,
        'id_maestro' => $base->id_maestro,
        'id_ciclo' => $base->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 30,
        'id_lti_contexto' => 999999,
    ])->assertUnprocessable()->assertJsonValidationErrors('id_lti_contexto');
});
