<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\EventoAgenda;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EspacioController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Recurso', [
            'titulo' => 'Espacios',
            'rutaBase' => '/admin/espacios',
            'idKey' => 'id_espacio',
            'columnas' => [
                ['key' => 'nombre', 'label' => 'Nombre'],
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'capacidad', 'label' => 'Capacidad', 'mono' => true],
            ],
            'filas' => Espacio::orderBy('nombre')->get(),
            'campos' => [
                ['name' => 'nombre', 'label' => 'Nombre', 'tipo' => 'text', 'requerido' => true],
                ['name' => 'tipo', 'label' => 'Tipo', 'tipo' => 'select', 'requerido' => true, 'opciones' => [
                    ['value' => 'fisico', 'label' => 'Físico'],
                    ['value' => 'virtual', 'label' => 'Virtual'],
                ]],
                ['name' => 'capacidad', 'label' => 'Capacidad', 'tipo' => 'number', 'min' => 1],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        Espacio::create($datos);

        return back()->with('success', 'Espacio creado.');
    }

    public function update(Request $request, Espacio $espacio)
    {
        $datos = $request->validate($this->reglas());
        $espacio->update($datos);

        return back()->with('success', 'Espacio actualizado.');
    }

    public function destroy(Espacio $espacio)
    {
        if (EventoAgenda::where('id_espacio', $espacio->id_espacio)->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: el espacio tiene eventos agendados.']);
        }
        $espacio->delete();

        return back()->with('success', 'Espacio eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::in(['fisico', 'virtual'])],
            'capacidad' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
