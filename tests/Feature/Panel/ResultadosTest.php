<?php
use App\Models\{Rol, Usuario, Maestro, Alumno, Carrera, Materia, CicloEscolar, Grupo, Inscripcion, Practica, EventoAgenda, SesionPractica};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('muestra las sesiones del grupo y el detalle con telemetria', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $uMaestro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'E1']);
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    $uAlumno = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'ana@b.com', 'nombre' => 'Ana', 'apellidos' => 'R']);
    $alumno = Alumno::create(['id_usuario' => $uAlumno->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);
    $evento = EventoAgenda::create(['id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(), 'estatus' => 'programado']);
    $sesion = SesionPractica::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'id_practica' => $practica->id_practica, 'fecha_inicio' => now(), 'fecha_fin' => now(), 'estatus' => 'completada', 'calificacion' => 88.5, 'datos_resultado' => ['aciertos' => 9]]);

    $this->actingAs($uMaestro);
    $this->get(route('panel.grupos.resultados', $grupo->id_grupo))
        ->assertOk()->assertSee('Ana')->assertSee('88.5');

    $this->get(route('panel.sesiones.show', $sesion->id_sesion))
        ->assertOk()->assertSee('aciertos');
});

it('un maestro no ve resultados de un grupo ajeno', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $mat = Materia::create(['clave' => 'X', 'nombre' => 'X', 'creditos' => 1]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $uDueno = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'd@b.com', 'nombre' => 'D', 'apellidos' => 'U']);
    $dueno = Maestro::create(['id_usuario' => $uDueno->id_usuario, 'numero_empleado' => 'ED']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $dueno->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '9Z', 'cupo_maximo' => 30]);

    $uOtro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'o@b.com', 'nombre' => 'O', 'apellidos' => 'T']);
    Maestro::create(['id_usuario' => $uOtro->id_usuario, 'numero_empleado' => 'EO']);
    $this->actingAs($uOtro);
    $this->get(route('panel.grupos.resultados', $grupo->id_grupo))->assertForbidden();
});

it('un maestro no ve el detalle de una sesion de un grupo ajeno', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $mat = Materia::create(['clave' => 'X', 'nombre' => 'X', 'creditos' => 1]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);

    // Grupo del maestro A (dueño)
    $uDueno = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'd@b.com', 'nombre' => 'D', 'apellidos' => 'U']);
    $dueno = Maestro::create(['id_usuario' => $uDueno->id_usuario, 'numero_empleado' => 'ED']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $dueno->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '9Z', 'cupo_maximo' => 30]);

    // Sesión bajo el grupo de A
    $uAlumno = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'ana@b.com', 'nombre' => 'Ana', 'apellidos' => 'R']);
    $alumno = Alumno::create(['id_usuario' => $uAlumno->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);
    $evento = EventoAgenda::create(['id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(), 'estatus' => 'programado']);
    $sesion = SesionPractica::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'id_practica' => $practica->id_practica, 'fecha_inicio' => now(), 'fecha_fin' => now(), 'estatus' => 'completada', 'calificacion' => 88.5, 'datos_resultado' => ['aciertos' => 9]]);

    // Maestro B (no dueño) intenta ver la sesión
    $uOtro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'o@b.com', 'nombre' => 'O', 'apellidos' => 'T']);
    Maestro::create(['id_usuario' => $uOtro->id_usuario, 'numero_empleado' => 'EO']);
    $this->actingAs($uOtro);
    $this->get(route('panel.sesiones.show', $sesion->id_sesion))->assertForbidden();
});

it('sesion LTI sin evento retorna 404 en el panel (no 500)', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $mat = Materia::create(['clave' => 'LTI2', 'nombre' => 'LTI Mat', 'creditos' => 5]);
    $carrera = Carrera::create(['clave' => 'ISC2', 'nombre' => 'Sis2', 'duracion_semestres' => 9]);

    // Staff user (maestro)
    $uStaff = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'staff@b.com', 'nombre' => 'Staff', 'apellidos' => 'U']);
    Maestro::create(['id_usuario' => $uStaff->id_usuario, 'numero_empleado' => 'ES1']);

    // LTI alumno
    $uAlumno = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'ltialumno@b.com', 'nombre' => 'Ana', 'apellidos' => 'LTI']);
    $alumno = Alumno::create(['id_usuario' => $uAlumno->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250088', 'semestre_actual' => 2, 'generacion' => '2025']);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab LTI Panel']);

    // LTI session: id_evento is null (no grupo)
    $sesion = SesionPractica::create([
        'id_practica' => $practica->id_practica,
        'id_evento' => null,
        'id_alumno' => $alumno->id_alumno,
        'fecha_inicio' => now(),
        'estatus' => 'en_progreso',
    ]);

    $this->actingAs($uStaff);
    $this->get(route('panel.sesiones.show', $sesion->id_sesion))->assertNotFound();
});
