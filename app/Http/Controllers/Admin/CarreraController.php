<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\MateriaCarrera;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CarreraController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Recurso', [
            'titulo' => 'Carreras',
            'rutaBase' => '/admin/carreras',
            'idKey' => 'id_carrera',
            'columnas' => [
                ['key' => 'clave', 'label' => 'Clave', 'mono' => true],
                ['key' => 'nombre', 'label' => 'Nombre'],
                ['key' => 'duracion_semestres', 'label' => 'Semestres', 'mono' => true],
                ['key' => 'alumnos_count', 'label' => 'Alumnos', 'mono' => true],
            ],
            'filas' => Carrera::withCount('alumnos')->orderBy('clave')->get(),
            'campos' => [
                ['name' => 'clave', 'label' => 'Clave', 'tipo' => 'text', 'requerido' => true],
                ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'text', 'requerido' => true],
                ['name' => 'duracion_semestres', 'label' => 'Duración (semestres)', 'tipo' => 'number', 'requerido' => true, 'min' => 1, 'max' => 15],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        Carrera::create($datos);

        return back()->with('success', 'Carrera creada.');
    }

    public function update(Request $request, Carrera $carrera)
    {
        $datos = $request->validate($this->reglas($carrera));
        $carrera->update($datos);

        return back()->with('success', 'Carrera actualizada.');
    }

    public function destroy(Carrera $carrera)
    {
        if ($carrera->alumnos()->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: la carrera tiene alumnos.']);
        }
        if (MateriaCarrera::where('id_carrera', $carrera->id_carrera)->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: la carrera tiene materias asignadas.']);
        }
        $carrera->delete();

        return back()->with('success', 'Carrera eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(?Carrera $carrera = null): array
    {
        return [
            'clave' => ['required', 'string', 'max:20', Rule::unique('carreras', 'clave')->ignore($carrera?->id_carrera, 'id_carrera')],
            'nombre' => ['required', 'string', 'max:150'],
            'duracion_semestres' => ['required', 'integer', 'min:1', 'max:15'],
        ];
    }
}
