<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Grupo;
use App\Models\Materia;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MateriaController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Materias', [
            'filas' => Materia::with('carreras')->orderBy('clave')->get()->map(fn (Materia $m) => [
                'id_materia' => $m->id_materia,
                'clave' => $m->clave,
                'nombre' => $m->nombre,
                'creditos' => $m->creditos,
                'carreras' => $m->carreras->map(fn (Carrera $c) => [
                    'id_carrera' => $c->id_carrera,
                    'nombre' => $c->nombre,
                    'semestre' => $c->pivot->semestre,
                ])->values(),
            ]),
            'carreras' => Carrera::orderBy('nombre')->get(['id_carrera', 'nombre']),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());

        DB::transaction(function () use ($datos) {
            $materia = Materia::create(Arr::except($datos, ['carreras']));
            $materia->carreras()->sync($this->asignaciones($datos));
        });

        return back()->with('success', 'Materia creada.');
    }

    public function update(Request $request, Materia $materia)
    {
        $datos = $request->validate($this->reglas($materia));

        DB::transaction(function () use ($materia, $datos) {
            $materia->update(Arr::except($datos, ['carreras']));
            $materia->carreras()->sync($this->asignaciones($datos));
        });

        return back()->with('success', 'Materia actualizada.');
    }

    public function destroy(Materia $materia)
    {
        if (Grupo::where('id_materia', $materia->id_materia)->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: la materia tiene grupos.']);
        }
        if ($materia->practicas()->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: la materia tiene prácticas.']);
        }

        DB::transaction(function () use ($materia) {
            $materia->carreras()->detach();
            $materia->delete();
        });

        return back()->with('success', 'Materia eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(?Materia $materia = null): array
    {
        return [
            'clave' => ['required', 'string', 'max:20', Rule::unique('materias', 'clave')->ignore($materia?->id_materia, 'id_materia')],
            'nombre' => ['required', 'string', 'max:150'],
            'creditos' => ['required', 'integer', 'min:0'],
            'carreras' => ['nullable', 'array'],
            'carreras.*.id_carrera' => ['required', 'integer', 'distinct', Rule::exists('carreras', 'id_carrera')],
            'carreras.*.semestre' => ['required', 'integer', 'min:1', 'max:15'],
        ];
    }

    /**
     * @param  array{carreras?: array<int, array{id_carrera: int, semestre: int}>|null}  $datos
     * @return array<int, array{semestre: int}>
     */
    private function asignaciones(array $datos): array
    {
        return collect($datos['carreras'] ?? [])
            ->mapWithKeys(fn (array $c) => [$c['id_carrera'] => ['semestre' => $c['semestre']]])
            ->all();
    }
}
