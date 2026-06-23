<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\SesionPractica;
use Illuminate\Http\Request;

class GameSessionController extends Controller {

    /**
     * Obtener datos del alumno autenticado
     *
     * Devuelve la información del usuario y del alumno asociado al token Bearer activo.
     * Útil para que Unreal Engine confirme la identidad del jugador al iniciar la sesión.
     *
     * @group Flujo de juego (Unreal)
     * @authenticated
     *
     * @response 200 scenario="Datos del alumno autenticado" {
     *   "usuario": {
     *     "id_usuario": 42,
     *     "name": "Juan Pérez López",
     *     "email": "juan.perez@tecnm.mx"
     *   },
     *   "alumno": {
     *     "id_alumno": 15,
     *     "numero_control": "21TI0001",
     *     "nombre": "Juan Pérez López",
     *     "semestre": 5,
     *     "id_grupo": 3
     *   }
     * }
     *
     * @response 401 scenario="Token inválido o expirado" {
     *   "message": "Unauthenticated."
     * }
     */
    public function me(Request $request) {
        $usuario = $request->user()->load('alumno');
        return response()->json(['usuario' => $usuario, 'alumno' => $usuario->alumno]);
    }

    /**
     * Iniciar sesión de práctica
     *
     * Crea una nueva sesión de práctica en estado `en_progreso` para el alumno autenticado
     * en el evento especificado. Verifica que el alumno esté inscrito en el grupo del evento.
     *
     * @group Flujo de juego (Unreal)
     * @authenticated
     *
     * @bodyParam id_evento int required El ID del evento de agenda en el que se inicia la sesión. Example: 7
     *
     * @response 201 scenario="Sesión iniciada exitosamente" {
     *   "id_sesion": 88,
     *   "estatus": "en_progreso"
     * }
     *
     * @response 401 scenario="Token inválido o expirado" {
     *   "message": "Unauthenticated."
     * }
     *
     * @response 403 scenario="Alumno no inscrito en el grupo del evento" {
     *   "message": "El alumno no está inscrito en el grupo de este evento"
     * }
     *
     * @response 422 scenario="Falta el campo id_evento" {
     *   "message": "The id_evento field is required.",
     *   "errors": {
     *     "id_evento": ["The id_evento field is required."]
     *   }
     * }
     */
    public function start(Request $request) {
        $data = $request->validate(['id_evento' => 'required|integer']);
        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403, 'El usuario no es alumno');

        $evento = EventoAgenda::findOrFail($data['id_evento']);

        $inscrito = \App\Models\Inscripcion::where('id_alumno', $alumno->id_alumno)
            ->where('id_grupo', $evento->id_grupo)
            ->exists();
        abort_unless($inscrito, 403, 'El alumno no está inscrito en el grupo de este evento');

        $sesion = SesionPractica::create([
            'id_evento' => $evento->id_evento,
            'id_alumno' => $alumno->id_alumno,
            'id_practica' => $evento->id_practica,
            'fecha_inicio' => now(),
            'estatus' => 'en_progreso',
        ]);

        return response()->json(['id_sesion' => $sesion->id_sesion, 'estatus' => $sesion->estatus], 201);
    }

    /**
     * Completar sesión de práctica
     *
     * Marca una sesión de práctica como `completada` y guarda la calificación obtenida
     * (entre 0 y 100) y los datos de resultado opcionales. Solo el alumno dueño de la
     * sesión puede completarla, y únicamente si está en estado `en_progreso`.
     *
     * @group Flujo de juego (Unreal)
     * @authenticated
     *
     * @urlParam id int required El ID de la sesión de práctica a completar. Example: 88
     *
     * @bodyParam calificacion number required La calificación obtenida, entre 0 y 100. Example: 85.5
     * @bodyParam datos_resultado object optional Objeto JSON con datos adicionales del resultado (estadísticas, logs, etc.). Example: {"tiempo_segundos": 240, "errores": 3}
     *
     * @response 200 scenario="Sesión completada exitosamente" {
     *   "id_sesion": 88,
     *   "estatus": "completada",
     *   "calificacion": 85.5
     * }
     *
     * @response 401 scenario="Token inválido o expirado" {
     *   "message": "Unauthenticated."
     * }
     *
     * @response 403 scenario="Sesión pertenece a otro alumno" {
     *   "message": "Sesión de otro alumno"
     * }
     *
     * @response 404 scenario="Sesión no encontrada" {
     *   "message": "No query results for model [App\\Models\\SesionPractica] 88"
     * }
     *
     * @response 409 scenario="La sesión no está en progreso" {
     *   "message": "La sesión no está en progreso"
     * }
     *
     * @response 422 scenario="Calificación fuera de rango o faltante" {
     *   "message": "The calificacion field must not be greater than 100.",
     *   "errors": {
     *     "calificacion": ["The calificacion field must not be greater than 100."]
     *   }
     * }
     */
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

        if ($sesion->ags_lineitem_url) {
            app(\App\Lti\AgsCliente::class)->enviar($sesion->fresh());
        }

        return response()->json(['id_sesion' => $sesion->id_sesion, 'estatus' => $sesion->estatus, 'calificacion' => $sesion->calificacion]);
    }
}
