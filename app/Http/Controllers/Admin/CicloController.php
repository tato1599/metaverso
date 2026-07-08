<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CicloEscolar;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CicloController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Recurso', [
            'titulo' => 'Ciclos escolares',
            'rutaBase' => '/admin/ciclos',
            'idKey' => 'id_ciclo',
            'columnas' => [
                ['key' => 'nombre', 'label' => 'Nombre', 'mono' => true],
                ['key' => 'fecha_inicio', 'label' => 'Inicio', 'mono' => true],
                ['key' => 'fecha_fin', 'label' => 'Fin', 'mono' => true],
                ['key' => 'activo', 'label' => 'Activo'],
            ],
            'filas' => CicloEscolar::orderByDesc('fecha_inicio')->get()->map(fn (CicloEscolar $ciclo): array => [
                'id_ciclo' => $ciclo->id_ciclo,
                'nombre' => $ciclo->nombre,
                'fecha_inicio' => $ciclo->fecha_inicio->toDateString(),
                'fecha_fin' => $ciclo->fecha_fin->toDateString(),
                'activo' => $ciclo->activo,
            ]),
            'campos' => [
                ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'text', 'requerido' => true],
                ['name' => 'fecha_inicio', 'label' => 'Fecha de inicio', 'tipo' => 'date', 'requerido' => true],
                ['name' => 'fecha_fin', 'label' => 'Fecha de fin', 'tipo' => 'date', 'requerido' => true],
                ['name' => 'activo', 'label' => 'Activo', 'tipo' => 'checkbox'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        CicloEscolar::create($datos);

        return back()->with('success', 'Ciclo escolar creado.');
    }

    public function update(Request $request, CicloEscolar $ciclo)
    {
        $datos = $request->validate($this->reglas($ciclo));
        $ciclo->update($datos);

        return back()->with('success', 'Ciclo escolar actualizado.');
    }

    public function destroy(CicloEscolar $ciclo)
    {
        if (Grupo::where('id_ciclo', $ciclo->id_ciclo)->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: el ciclo tiene grupos.']);
        }
        $ciclo->delete();

        return back()->with('success', 'Ciclo escolar eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(?CicloEscolar $ciclo = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:50', Rule::unique('ciclos_escolares', 'nombre')->ignore($ciclo?->id_ciclo, 'id_ciclo')],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'activo' => ['boolean'],
        ];
    }
}
