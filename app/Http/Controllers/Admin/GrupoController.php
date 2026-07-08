<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\CicloEscolar;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Maestro;
use App\Models\Materia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GrupoController extends Controller
{
    public function index(Request $request)
    {
        $filas = Grupo::with([
            'materia',
            'maestro.usuario',
            'ciclo',
            'inscripciones' => fn ($q) => $q->where('estatus', 'activa')->with('alumno.usuario'),
        ])->orderBy('clave')->get()->map(fn (Grupo $g) => [
            'id_grupo' => $g->id_grupo,
            'clave' => $g->clave,
            'cupo_maximo' => $g->cupo_maximo,
            'id_materia' => $g->id_materia,
            'id_maestro' => $g->id_maestro,
            'id_ciclo' => $g->id_ciclo,
            'materia' => $g->materia->nombre,
            'maestro' => trim($g->maestro->usuario->nombre.' '.$g->maestro->usuario->apellidos),
            'ciclo' => $g->ciclo->nombre,
            'inscritos' => $g->inscripciones->count(),
            'inscripciones' => $g->inscripciones->map(fn (Inscripcion $i) => [
                'id_inscripcion' => $i->id_inscripcion,
                'id_alumno' => $i->id_alumno,
                'nombre' => trim($i->alumno->usuario->nombre.' '.$i->alumno->usuario->apellidos),
                'matricula' => $i->alumno->matricula,
            ])->values(),
        ]);

        return Inertia::render('Admin/Grupos', [
            'filas' => $filas,
            'materias' => Materia::orderBy('clave')->get()
                ->map(fn (Materia $m) => ['value' => $m->id_materia, 'label' => "{$m->clave} — {$m->nombre}"])->values(),
            'maestros' => Maestro::with('usuario')->get()
                ->map(fn (Maestro $m) => ['value' => $m->id_maestro, 'label' => trim($m->usuario->nombre.' '.$m->usuario->apellidos)])
                ->sortBy('label')->values(),
            'ciclos' => CicloEscolar::orderByDesc('fecha_inicio')->get()
                ->map(fn (CicloEscolar $c) => ['value' => $c->id_ciclo, 'label' => $c->nombre])->values(),
            'alumnos' => Alumno::with('usuario')->orderBy('matricula')->get()
                ->map(fn (Alumno $a) => [
                    'id_alumno' => $a->id_alumno,
                    'etiqueta' => trim($a->usuario->nombre.' '.$a->usuario->apellidos)." — {$a->matricula}",
                ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        Grupo::create($datos);

        return back()->with('success', 'Grupo creado.');
    }

    public function update(Request $request, Grupo $grupo)
    {
        $datos = $request->validate($this->reglas());
        $grupo->update($datos);

        return back()->with('success', 'Grupo actualizado.');
    }

    public function destroy(Request $request, Grupo $grupo)
    {
        if ($grupo->eventos()->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: el grupo tiene eventos en la agenda.']);
        }
        if ($grupo->inscripciones()->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: el grupo tiene inscripciones.']);
        }
        $grupo->delete();

        return back()->with('success', 'Grupo eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(): array
    {
        return [
            'id_materia' => ['required', Rule::exists('materias', 'id_materia')],
            'id_maestro' => ['required', Rule::exists('maestros', 'id_maestro')],
            'id_ciclo' => ['required', Rule::exists('ciclos_escolares', 'id_ciclo')],
            'clave' => ['required', 'string', 'max:20'],
            'cupo_maximo' => ['required', 'integer', 'min:1'],
        ];
    }
}
