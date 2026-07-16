<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Materia;
use App\Models\Practica;
use App\Models\Reserva;
use App\Models\SesionPractica;
use App\Support\RegistroJuegos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PracticaController extends Controller
{
    /**
     * Solo el rol Admin enlaza prácticas con su juego de Godot. Coordinador y
     * maestros no administran esto (el resto de /admin sí es Coordinador+Admin).
     */
    private function soloAdmin(Request $request): void
    {
        abort_unless($request->user()?->esAdmin(), 403, 'Solo un administrador puede administrar prácticas.');
    }

    public function index(Request $request)
    {
        $this->soloAdmin($request);

        $opcionesMaterias = Materia::orderBy('clave')->get()
            ->map(fn (Materia $m) => ['value' => $m->id_materia, 'label' => "{$m->clave} — {$m->nombre}"])
            ->values();

        return Inertia::render('Admin/Practicas', [
            'titulo' => 'Prácticas',
            'rutaBase' => '/admin/practicas',
            'idKey' => 'id_practica',
            'juegos' => RegistroJuegos::juegos(),
            'columnas' => [
                ['key' => 'materia_nombre', 'label' => 'Materia'],
                ['key' => 'titulo', 'label' => 'Título'],
                ['key' => 'orden', 'label' => 'Orden', 'mono' => true],
                ['key' => 'duracion_estimada', 'label' => 'Duración (min)', 'mono' => true],
                ['key' => 'escena_referencia', 'label' => 'Juego', 'mono' => true],
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
            'materias' => $opcionesMaterias,
        ]);
    }

    public function store(Request $request)
    {
        $this->soloAdmin($request);
        $datos = $request->validate($this->reglas());
        Practica::create($datos);

        return back()->with('success', 'Práctica creada.');
    }

    public function update(Request $request, Practica $practica)
    {
        $this->soloAdmin($request);
        $datos = $request->validate($this->reglas());

        $cambiaMateria = (int) $datos['id_materia'] !== $practica->id_materia;
        if ($cambiaMateria && (
            EventoAgenda::where('id_practica', $practica->id_practica)->exists()
            || SesionPractica::where('id_practica', $practica->id_practica)->exists()
        )) {
            throw ValidationException::withMessages(['id_materia' => 'No se puede cambiar la materia: la práctica tiene eventos o sesiones registradas.']);
        }

        // Enmienda F5: la partición de los eventos vigentes depende de la duración;
        // cambiarla dejaría inicio_slot fuera de la nueva partición (sobreventa).
        // La transacción + lockForUpdate cierra el TOCTOU: nadie reserva en esos
        // eventos entre el conteo y el update.
        DB::transaction(function () use ($datos, $practica) {
            if (array_key_exists('duracion_estimada', $datos)) {
                $nuevaDuracion = $datos['duracion_estimada'] === null ? null : (int) $datos['duracion_estimada'];
                $duracionActual = $practica->duracion_estimada === null ? null : (int) $practica->duracion_estimada;
                if ($nuevaDuracion !== $duracionActual) {
                    $idsEventos = EventoAgenda::where('id_practica', $practica->id_practica)
                        ->where('estatus', '!=', 'cancelado')
                        ->where('fecha_hora_fin', '>', now())
                        ->lockForUpdate()
                        ->pluck('id_evento');
                    $reservas = Reserva::whereIn('id_evento', $idsEventos)
                        ->where('estatus', 'activa')
                        ->count();
                    if ($reservas > 0) {
                        throw ValidationException::withMessages([
                            'duracion_estimada' => "Hay {$reservas} reservas en eventos futuros; cancélalas o espera.",
                        ]);
                    }
                }
            }

            $practica->update($datos);
        });

        return back()->with('success', 'Práctica actualizada.');
    }

    public function destroy(Request $request, Practica $practica)
    {
        $this->soloAdmin($request);
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
            'escena_referencia' => ['required', 'string', Rule::in(RegistroJuegos::ids())],
        ];
    }
}
