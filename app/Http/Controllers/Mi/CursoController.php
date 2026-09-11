<?php

namespace App\Http\Controllers\Mi;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Practica;
use App\Models\Reserva;
use App\Models\SesionPractica;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * El expediente del alumno: en qué cursos está y cómo va en cada práctica.
 *
 * Es SOLO LECTURA a propósito. Reservar, cambiar de horario y entrar al juego
 * viven en `/mi/eventos/{evento}`; aquí solo se consulta. Por eso cada práctica
 * enlaza a esa vista en lugar de traerse sus acciones.
 *
 * Índice y detalle: la lista da los cursos y su avance; el detalle, las
 * prácticas de uno. Meter todas las prácticas de todos los cursos en la misma
 * pantalla obliga a leerlo todo para encontrar una cosa.
 */
class CursoController extends Controller
{
    public function index(Request $request)
    {
        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403);

        $inscripciones = $this->inscripcionesActivas($alumno->id_alumno);
        $contexto = $this->contexto($alumno->id_alumno, $inscripciones);

        return Inertia::render('Mi/Cursos', [
            'cursos' => $inscripciones->map(function (Inscripcion $i) use ($contexto) {
                $estados = $this->estadosDelCurso($i->grupo, $contexto)->pluck('estado');

                return array_merge($this->datosCurso($i->grupo), [
                    // El resumen que hace útil una lista: cuánto llevas y qué te falta.
                    'total' => $estados->count(),
                    'completadas' => $estados->filter(fn ($e) => $e === 'completada')->count(),
                    'por_reservar' => $estados->filter(fn ($e) => $e === 'por reservar')->count(),
                    'reservadas' => $estados->filter(fn ($e) => $e === 'reservada')->count(),
                ]);
            })->values(),
        ]);
    }

    public function show(Request $request, Grupo $grupo)
    {
        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403);

        // Misma regla que en toda la sección: sin inscripción activa, nada que ver.
        $inscrito = Inscripcion::where('id_alumno', $alumno->id_alumno)
            ->where('id_grupo', $grupo->id_grupo)
            ->where('estatus', 'activa')
            ->exists();
        abort_unless($inscrito, 403, 'No estás inscrito en este grupo.');

        $grupo->load(['materia', 'ciclo', 'maestro.usuario']);
        $contexto = $this->contexto($alumno->id_alumno, collect([$grupo->id_grupo]), true);

        return Inertia::render('Mi/Curso', [
            'curso' => $this->datosCurso($grupo),
            'practicas' => $this->estadosDelCurso($grupo, $contexto)->values(),
        ]);
    }

    /** @return Collection<int,Inscripcion> */
    private function inscripcionesActivas(int $idAlumno): Collection
    {
        return Inscripcion::where('id_alumno', $idAlumno)
            ->where('estatus', 'activa')
            ->with(['grupo.materia', 'grupo.ciclo', 'grupo.maestro.usuario'])
            ->get()
            ->filter(fn (Inscripcion $i) => $i->grupo !== null)
            ->values();
    }

    /**
     * Eventos, reservas, sesiones y prácticas de todos los grupos en juego, en
     * cuatro consultas — no cuatro por grupo.
     *
     * @param  Collection<int,Inscripcion>|Collection<int,int>  $origen
     * @return array<string,Collection>
     */
    private function contexto(int $idAlumno, Collection $origen, bool $sonIds = false): array
    {
        $idsGrupo = $sonIds ? $origen : $origen->pluck('id_grupo');
        $materias = Grupo::whereIn('id_grupo', $idsGrupo)->pluck('id_materia')->unique();

        return [
            'eventos' => EventoAgenda::whereIn('id_grupo', $idsGrupo)
                ->orderBy('fecha_hora_inicio')
                ->get()
                ->groupBy(fn (EventoAgenda $e) => $e->id_grupo.'-'.$e->id_practica),
            'reservas' => Reserva::where('id_alumno', $idAlumno)
                ->where('estatus', 'activa')
                ->whereHas('evento', fn ($q) => $q->whereIn('id_grupo', $idsGrupo))
                ->get()
                ->keyBy('id_evento'),
            'sesiones' => SesionPractica::where('id_alumno', $idAlumno)->get()->groupBy('id_practica'),
            'practicas' => Practica::whereIn('id_materia', $materias)
                ->orderBy('orden')
                ->get()
                ->groupBy('id_materia'),
        ];
    }

    /** @return array<string,mixed> */
    private function datosCurso(Grupo $grupo): array
    {
        return [
            'id_grupo' => $grupo->id_grupo,
            'clave' => $grupo->clave,
            'materia' => $grupo->materia->nombre,
            'ciclo' => optional($grupo->ciclo)->nombre,
            'maestro' => trim(
                optional(optional($grupo->maestro)->usuario)->nombre.' '.
                optional(optional($grupo->maestro)->usuario)->apellidos
            ) ?: null,
        ];
    }

    /**
     * Las prácticas de la materia del grupo, con el estado del alumno en cada una.
     *
     * @param  array<string,Collection>  $contexto
     * @return Collection<int,array<string,mixed>>
     */
    private function estadosDelCurso(Grupo $grupo, array $contexto): Collection
    {
        $ahora = now();

        return $contexto['practicas']->get($grupo->id_materia, collect())
            ->map(fn (Practica $p) => $this->estadoPractica(
                $p,
                $contexto['eventos']->get($grupo->id_grupo.'-'.$p->id_practica, collect()),
                $contexto['reservas'],
                $contexto['sesiones']->get($p->id_practica, collect()),
                $ahora,
            ));
    }

    /**
     * Dónde está el alumno con una práctica concreta de un grupo concreto.
     *
     * El orden de los casos es el de la realidad: una práctica ya calificada no
     * vuelve a estar "por reservar" porque el maestro agende otra fecha.
     *
     * @param  Collection<int,EventoAgenda>  $eventos
     * @param  Collection<int,Reserva>  $reservas
     * @param  Collection<int,SesionPractica>  $sesiones
     * @return array<string,mixed>
     */
    private function estadoPractica(Practica $practica, $eventos, $reservas, $sesiones, Carbon $ahora): array
    {
        $completada = $sesiones->firstWhere('estatus', 'completada');
        $futuros = $eventos->filter(fn (EventoAgenda $e) => $e->fecha_hora_fin->gte($ahora) && $e->estatus !== 'cancelado');

        // Solo cuenta como "reservada" una reserva cuya fecha NO ha pasado. Una
        // reserva vieja sobre un evento terminado no es un plan, es historia: si
        // no dejó sesión, la práctica se perdió.
        $miReserva = $futuros->map(fn (EventoAgenda $e) => $reservas->get($e->id_evento))->filter()->first();

        $estado = match (true) {
            $completada !== null => 'completada',
            $sesiones->isNotEmpty() => 'intentada',
            $miReserva !== null => 'reservada',
            $eventos->isEmpty() => 'sin agendar',
            $futuros->isNotEmpty() => 'por reservar',
            default => 'perdida',
        };

        // A qué evento llevar al alumno: el que reservó, o el próximo disponible.
        $destino = $miReserva
            ? $eventos->firstWhere('id_evento', $miReserva->id_evento)
            : ($futuros->first() ?? $eventos->last());

        return [
            'id_practica' => $practica->id_practica,
            'titulo' => $practica->titulo,
            'orden' => $practica->orden,
            'estado' => $estado,
            'calificacion' => $completada?->calificacion,
            'id_evento' => optional($destino)->id_evento,
            'fecha_local' => $miReserva
                ? $miReserva->inicio_slot->format('Y-m-d\TH:i')
                : optional($destino)->fecha_hora_inicio?->format('Y-m-d\TH:i'),
            'total_fechas' => $eventos->count(),
        ];
    }
}
