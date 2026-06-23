<?php

use App\Models\{Rol, Usuario, Materia, Maestro, CicloEscolar, Grupo, Practica, EventoAgenda};
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

function crearEventoBasico(): EventoAgenda {
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => 'E1']);
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $prac = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);
    return EventoAgenda::create([
        'id_practica' => $prac->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(),
    ]);
}
