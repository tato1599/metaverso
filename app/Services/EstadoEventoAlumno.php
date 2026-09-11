<?php

namespace App\Services;

use App\Models\EventoAgenda;
use App\Models\Reserva;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La única definición del estado de un alumno frente a un evento de agenda.
 *
 * El calendario semanal y la vista de una práctica muestran lo mismo con
 * distinto detalle, así que las reglas —puede reservar, está lleno, puede
 * cancelar, puede jugar— viven aquí y no en cada pantalla. La UI no
 * reimplementa reglas: recibe booleanos ya decididos.
 *
 * Los llamadores traen `$mia` y `$ocupados` porque cada uno los consigue a su
 * manera: el calendario agrega una consulta para toda la semana, la vista de
 * una práctica consulta solo su evento.
 */
class EstadoEventoAlumno
{
    /**
     * @param  Reserva|null  $mia  Reserva activa del alumno en este evento, si la hay.
     * @param  Collection<string,int>  $ocupados  Reservas activas por slot, con clave 'Y-m-d\TH:i'.
     * @return array<string,mixed>
     */
    public function para(EventoAgenda $evento, ?Reserva $mia, Collection $ocupados, Carbon $ahora): array
    {
        $slotsCrudos = $evento->slots();

        $slots = collect($slotsCrudos)->map(function (array $s) use ($evento, $mia, $ocupados) {
            $clave = $s['inicio']->format('Y-m-d\TH:i');
            $enSlot = (int) $ocupados->get($clave, 0);
            $llenoSlot = $enSlot >= $evento->cupo_maximo;

            return [
                'inicio_local' => $clave,
                'fin_local' => $s['fin']->format('Y-m-d\TH:i'),
                'ocupados' => $enSlot,
                'lleno' => $llenoSlot,
                'es_mio' => (bool) $mia && $mia->inicio_slot->format('Y-m-d\TH:i') === $clave,
                'puede_reservar' => ! $mia && $evento->estatus === 'programado'
                    && $s['inicio']->isFuture() && ! $llenoSlot,
            ];
        })->values();

        // 'lleno' solo mira horarios aún reservables: un slot pasado libre no debe
        // ocultar que todo lo que queda por venir ya está lleno.
        $slotsFuturos = $slots->filter(fn (array $s, int $i) => $slotsCrudos[$i]['inicio']->isFuture());

        return [
            'id_evento' => $evento->id_evento,
            'practica' => $evento->practica->titulo,
            'materia' => $evento->grupo->materia->nombre,
            'grupo' => $evento->grupo->clave,
            'espacio' => optional($evento->espacio)->nombre,
            'inicio_local' => $evento->fecha_hora_inicio->format('Y-m-d\TH:i'),
            'fin_local' => $evento->fecha_hora_fin->format('Y-m-d\TH:i'),
            'estatus' => $evento->estatus,
            'cupo_maximo' => $evento->cupo_maximo,
            'reservas_activas' => (int) ($evento->reservas_activas ?? $evento->reservasActivas()->count()),
            'multi_slot' => count($slotsCrudos) > 1,
            'slots' => $slots,
            'mi_reserva' => $mia ? [
                'id_reserva' => $mia->id_reserva,
                'inicio_slot_local' => $mia->inicio_slot->format('Y-m-d\TH:i'),
                'fin_slot_local' => $this->finDelSlot($evento, $mia)->format('Y-m-d\TH:i'),
            ] : null,
            'puede_reservar' => $slots->contains(fn (array $s) => $s['puede_reservar']),
            'lleno' => ! $mia && $evento->estatus === 'programado'
                && $slotsFuturos->isNotEmpty()
                && $slotsFuturos->every(fn (array $s) => $s['lleno']),
            'finalizado' => $evento->estatus !== 'cancelado' && $evento->fecha_hora_fin->lt($ahora),
            'puede_cancelar' => (bool) $mia && $mia->inicio_slot->isFuture(),
            'puede_jugar' => (bool) $mia && $evento->estatus !== 'cancelado'
                && $ahora->between($mia->inicio_slot, $this->finDelSlot($evento, $mia)),
        ];
    }

    /**
     * Ocupación activa por slot de un solo evento, con la misma forma que la
     * agregada de la semana.
     *
     * @return Collection<string,int>
     */
    public function ocupadosPorSlot(EventoAgenda $evento): Collection
    {
        return $evento->reservasActivas()
            ->groupBy('inicio_slot')
            ->selectRaw('inicio_slot, count(*) as total')
            ->get()
            ->keyBy(fn ($r) => $r->inicio_slot->format('Y-m-d\TH:i'))
            ->map(fn ($r) => (int) $r->total);
    }

    /**
     * Fin de la ventana de juego del slot reservado: inicio_slot + duración,
     * capado al fin del evento (F6). Misma regla que JugarController.
     */
    private function finDelSlot(EventoAgenda $evento, Reserva $reserva): Carbon
    {
        $duracion = $evento->duracionSlotMinutos();

        return $duracion === null
            ? $evento->fecha_hora_fin
            : $reserva->inicio_slot->copy()->addMinutes($duracion)->min($evento->fecha_hora_fin);
    }
}
