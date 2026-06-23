<?php
use App\Models\{Rol, Usuario, Maestro, Alumno, Carrera, Materia, CicloEscolar, Grupo, Inscripcion, Practica, EventoAgenda, TokenJuego};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grupoConAlumnos(int $n): array {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $uMaestro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'm'.rand(1,99999).'@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'E'.rand(1,99999)]);
    $mat = Materia::create(['clave' => 'M'.rand(1,99999), 'nombre' => 'Mat', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $carrera = Carrera::create(['clave' => 'C'.rand(1,99999), 'nombre' => 'Car', 'duracion_semestres' => 9]);
    for ($i = 0; $i < $n; $i++) {
        $u = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'al'.rand(1,999999).'@b.com', 'nombre' => 'Al'.$i, 'apellidos' => 'P']);
        $al = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => (string) rand(10000000,99999999), 'semestre_actual' => 3, 'generacion' => '2025']);
        Inscripcion::create(['id_alumno' => $al->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);
    }
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab']);
    $evento = EventoAgenda::create(['id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(), 'estatus' => 'programado']);
    return [$uMaestro, $grupo, $evento];
}

it('genera un magic link por alumno inscrito del grupo', function () {
    [$uMaestro, $grupo, $evento] = grupoConAlumnos(3);
    $this->actingAs($uMaestro);

    $r = $this->post(route('panel.grupos.eventos.links', [$grupo->id_grupo, $evento->id_evento]));
    $r->assertOk();
    expect(TokenJuego::where('id_evento', $evento->id_evento)->count())->toBe(3);
    expect($r->viewData('filas'))->toHaveCount(3);
});

it('un maestro no puede generar links de un grupo ajeno', function () {
    [, $grupo, $evento] = grupoConAlumnos(2);
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $otro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'otro@b.com', 'nombre' => 'O', 'apellidos' => 'T']);
    Maestro::create(['id_usuario' => $otro->id_usuario, 'numero_empleado' => 'EZZ']);
    $this->actingAs($otro);
    $this->post(route('panel.grupos.eventos.links', [$grupo->id_grupo, $evento->id_evento]))->assertForbidden();
});

it('exporta CSV con una fila por alumno', function () {
    [$uMaestro, $grupo, $evento] = grupoConAlumnos(2);
    $this->actingAs($uMaestro);
    $r = $this->get(route('panel.grupos.eventos.links.csv', [$grupo->id_grupo, $evento->id_evento]));
    $r->assertOk();
    expect($r->headers->get('content-type'))->toContain('text/csv');
    $lineas = array_filter(explode("\n", trim($r->streamedContent() ?? $r->getContent())));
    expect(count($lineas))->toBe(3); // encabezado + 2 alumnos
});
