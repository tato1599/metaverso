<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Materia;
use App\Models\Practica;
use App\Models\SesionPractica;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PracticaController extends Controller
{
    public function index(Request $request)
    {
        $opcionesMaterias = Materia::orderBy('clave')->get()
            ->map(fn (Materia $m) => ['value' => $m->id_materia, 'label' => "{$m->clave} — {$m->nombre}"])
            ->values();

        return Inertia::render('Admin/Recurso', [
            'titulo' => 'Prácticas',
            'rutaBase' => '/admin/practicas',
            'idKey' => 'id_practica',
            'columnas' => [
                ['key' => 'materia_nombre', 'label' => 'Materia'],
                ['key' => 'titulo', 'label' => 'Título'],
                ['key' => 'orden', 'label' => 'Orden', 'mono' => true],
                ['key' => 'duracion_estimada', 'label' => 'Duración (min)', 'mono' => true],
                ['key' => 'escena_referencia', 'label' => 'Escena', 'mono' => true],
            ],
            'filas' => Practica::with('materia')->orderBy('id_materia')->orderBy('orden')->get()->map(fn (Practica $p) => [
                'id_practica' => $p->id_practica,
                'id_materia' => $p->id_materia,
                'titulo' => $p->titulo,
                'descripcion' => $p->descripcion,
                'objetivos' => $p->objetivos,
                'orden' => $p->orden,
                'duracion_estimada' => $p->duracion_estimada,
                'escena_referencia' => $p->escena_referencia,
                'materia_nombre' => $p->materia->nombre,
            ]),
            'campos' => [
                ['name' => 'id_materia', 'label' => 'Materia', 'tipo' => 'select', 'requerido' => true, 'opciones' => $opcionesMaterias],
                ['name' => 'titulo', 'label' => 'Título', 'tipo' => 'text', 'requerido' => true],
                ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'textarea'],
                ['name' => 'objetivos', 'label' => 'Objetivos', 'tipo' => 'textarea'],
                ['name' => 'orden', 'label' => 'Orden', 'tipo' => 'number', 'requerido' => true, 'min' => 1],
                ['name' => 'duracion_estimada', 'label' => 'Duración estimada (min)', 'tipo' => 'number', 'min' => 1],
                ['name' => 'escena_referencia', 'label' => 'Escena de referencia', 'tipo' => 'text', 'requerido' => true],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        Practica::create($datos);

        return back()->with('success', 'Práctica creada.');
    }

    public function update(Request $request, Practica $practica)
    {
        $datos = $request->validate($this->reglas());
        $practica->update($datos);

        return back()->with('success', 'Práctica actualizada.');
    }

    public function destroy(Practica $practica)
    {
        if (EventoAgenda::where('id_practica', $practica->id_practica)->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: la práctica tiene eventos agendados.']);
        }
        if (SesionPractica::where('id_practica', $practica->id_practica)->exists()) {
            throw ValidationException::withMessages(['eliminar' => 'No se puede eliminar: la práctica tiene sesiones registradas.']);
        }
        $practica->delete();

        return back()->with('success', 'Práctica eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(): array
    {
        return [
            'id_materia' => ['required', 'integer', Rule::exists('materias', 'id_materia')],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'objetivos' => ['nullable', 'string'],
            'orden' => ['required', 'integer', 'min:1'],
            'duracion_estimada' => ['nullable', 'integer', 'min:1'],
            'escena_referencia' => ['required', 'string', 'max:255'],
        ];
    }
}
