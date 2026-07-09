<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Lti\SincronizarRosterMoodle;
use App\Models\Alumno;
use App\Models\CicloEscolar;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\LtiContexto;
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
            'id_lti_contexto' => $g->id_lti_contexto,
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
            'contextos' => LtiContexto::orderBy('titulo')->orderBy('context_id')->get()
                ->map(fn (LtiContexto $c) => ['value' => $c->id, 'label' => $c->titulo ?: $c->context_id])->values(),
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

        if ((int) $datos['id_materia'] !== $grupo->id_materia && $grupo->eventos()->exists()) {
            throw ValidationException::withMessages(['id_materia' => 'No se puede cambiar la materia: el grupo tiene eventos en la agenda.']);
        }

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

    public function sincronizar(Request $request, Grupo $grupo, SincronizarRosterMoodle $sincronizador)
    {
        $resumen = $sincronizador->sincronizar($grupo);

        return back()->with('success', $this->resumenLegible($resumen));
    }

    /**
     * @param  array{alumnos_nuevos: int, alumnos_reactivados: int, maestros: int, sin_cambio: int, inactivos: list<string>, en_moodle_no_locales: list<string>, locales_no_en_moodle: list<string>}  $resumen
     */
    private function resumenLegible(array $resumen): string
    {
        $mensaje = sprintf(
            'Roster sincronizado: %d alumnos nuevos, %d reactivados, %d maestros, %d sin cambio.',
            $resumen['alumnos_nuevos'],
            $resumen['alumnos_reactivados'],
            $resumen['maestros'],
            $resumen['sin_cambio'],
        );

        if ($resumen['locales_no_en_moodle'] !== []) {
            $mensaje .= ' Solo locales, ya no en Moodle (revisar): '.implode(', ', $resumen['locales_no_en_moodle']).'.';
        }
        if ($resumen['inactivos'] !== []) {
            $mensaje .= ' Inactivos en Moodle (omitidos): '.implode(', ', $resumen['inactivos']).'.';
        }

        return $mensaje;
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(): array
    {
        return [
            'id_materia' => ['required', 'integer', Rule::exists('materias', 'id_materia')],
            'id_maestro' => ['required', 'integer', Rule::exists('maestros', 'id_maestro')],
            'id_ciclo' => ['required', 'integer', Rule::exists('ciclos_escolares', 'id_ciclo')],
            'clave' => ['required', 'string', 'max:20'],
            'cupo_maximo' => ['required', 'integer', 'min:1'],
            'id_lti_contexto' => ['nullable', 'integer', Rule::exists('lti_contextos', 'id')],
        ];
    }
}
