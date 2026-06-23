<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\SesionPractica;
use Illuminate\Http\Request;

class GameSessionController extends Controller {
    public function me(Request $request) {
        $usuario = $request->user()->load('alumno');
        return response()->json(['usuario' => $usuario, 'alumno' => $usuario->alumno]);
    }

    public function start(Request $request) {
        $data = $request->validate(['id_evento' => 'required|integer']);
        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403, 'El usuario no es alumno');

        $evento = EventoAgenda::findOrFail($data['id_evento']);

        $sesion = SesionPractica::create([
            'id_evento' => $evento->id_evento,
            'id_alumno' => $alumno->id_alumno,
            'id_practica' => $evento->id_practica,
            'fecha_inicio' => now(),
            'estatus' => 'en_progreso',
        ]);

        return response()->json(['id_sesion' => $sesion->id_sesion, 'estatus' => $sesion->estatus], 201);
    }

    public function complete(Request $request, int $id) {
        $sesion = SesionPractica::findOrFail($id);
        $alumno = $request->user()->alumno;
        abort_unless($alumno && $sesion->id_alumno === $alumno->id_alumno, 403, 'Sesión de otro alumno');

        if ($sesion->estatus !== 'en_progreso') {
            return response()->json(['message' => 'La sesión no está en progreso'], 409);
        }

        $min = config('metaverso.calificacion_min', 0);
        $max = config('metaverso.calificacion_max', 100);
        $data = $request->validate([
            'calificacion' => "required|numeric|min:{$min}|max:{$max}",
            'datos_resultado' => 'nullable|array',
        ]);

        $sesion->update([
            'calificacion' => $data['calificacion'],
            'datos_resultado' => $data['datos_resultado'] ?? null,
            'fecha_fin' => now(),
            'estatus' => 'completada',
        ]);

        return response()->json(['id_sesion' => $sesion->id_sesion, 'estatus' => $sesion->estatus, 'calificacion' => $sesion->calificacion]);
    }
}
