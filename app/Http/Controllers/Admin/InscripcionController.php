<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InscripcionController extends Controller
{
    public function store(Request $request, Grupo $grupo)
    {
        $datos = $request->validate([
            'id_alumno' => ['required', 'integer', Rule::exists('alumnos', 'id_alumno')],
        ]);

        /*
         * El unique (id_alumno, id_grupo) de BD es incondicional: si existe fila
         * en baja se reactiva; solo se crea cuando el par no existe.
         */
        $inscripcion = Inscripcion::where('id_alumno', $datos['id_alumno'])
            ->where('id_grupo', $grupo->id_grupo)
            ->first();

        if ($inscripcion !== null && $inscripcion->estatus === 'activa') {
            throw ValidationException::withMessages(['id_alumno' => 'El alumno ya está inscrito en este grupo.']);
        }

        if ($inscripcion !== null) {
            $inscripcion->update(['estatus' => 'activa', 'fecha_inscripcion' => today()]);
        } else {
            Inscripcion::create([
                'id_alumno' => $datos['id_alumno'],
                'id_grupo' => $grupo->id_grupo,
                'fecha_inscripcion' => today(),
                'estatus' => 'activa',
            ]);
        }

        return back()->with('success', 'Alumno inscrito.');
    }

    public function destroy(Request $request, Grupo $grupo, Inscripcion $inscripcion)
    {
        abort_unless($inscripcion->id_grupo === $grupo->id_grupo, 404);

        $inscripcion->update(['estatus' => 'baja']);

        return back()->with('success', 'Alumno dado de baja del grupo.');
    }
}
