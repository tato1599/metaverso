<?php
namespace Database\Seeders;

use App\Models\{Rol, Usuario, Alumno, Maestro, Carrera, Materia, CicloEscolar, Grupo, Espacio, Practica, Inscripcion, EventoAgenda};
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder {
    public function run(): void {
        $roles = collect(['Alumno','Maestro','Coordinador','Admin'])
            ->mapWithKeys(fn ($n) => [$n => Rol::create(['nombre' => $n])->id_rol]);

        $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Ing. en Sistemas Computacionales', 'duracion_semestres' => 9]);
        $materia = Materia::create(['clave' => 'SCD-1027', 'nombre' => 'Programación', 'creditos' => 5]);
        $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);
        $espacio = Espacio::create(['nombre' => 'Laboratorio Virtual A', 'tipo' => 'virtual', 'capacidad' => 30]);

        $uMaestro = Usuario::create(['id_rol' => $roles['Maestro'], 'correo' => 'maestro@tecnm.mx', 'nombre' => 'Laura', 'apellidos' => 'Gómez']);
        $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'EMP001', 'grado_academico' => 'M.C.', 'especialidad' => 'Software']);

        $grupo = Grupo::create(['id_materia' => $materia->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
        $practica = Practica::create(['id_materia' => $materia->id_materia, 'titulo' => 'Práctica 1: Variables', 'descripcion' => 'Introducción', 'objetivos' => 'Comprender variables', 'duracion_estimada' => 30, 'orden' => 1, 'escena_referencia' => 'Lab_Variables']);

        foreach ([['Ana','Ruiz','20250001'],['Beto','Díaz','20250002'],['Caro','León','20250003']] as $i => [$nom,$ape,$mat]) {
            $u = Usuario::create(['id_rol' => $roles['Alumno'], 'correo' => strtolower($nom).'@tecnm.mx', 'nombre' => $nom, 'apellidos' => $ape]);
            $alumno = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => $mat, 'semestre_actual' => 3, 'generacion' => '2025']);
            Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);
        }

        EventoAgenda::create([
            'id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'id_espacio' => $espacio->id_espacio,
            'fecha_hora_inicio' => now()->addDay(), 'fecha_hora_fin' => now()->addDay()->addHour(), 'estatus' => 'programado',
        ]);
    }
}
