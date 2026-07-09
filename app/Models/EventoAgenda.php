<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EventoAgenda extends Model
{
    protected $table = 'eventos_agenda';

    protected $primaryKey = 'id_evento';

    protected $guarded = [];

    protected $casts = ['fecha_hora_inicio' => 'datetime', 'fecha_hora_fin' => 'datetime'];

    public function practica()
    {
        return $this->belongsTo(Practica::class, 'id_practica');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'id_espacio');
    }

    public function tokens()
    {
        return $this->hasMany(TokenJuego::class, 'id_evento');
    }

    public function sesiones()
    {
        return $this->hasMany(SesionPractica::class, 'id_evento');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_evento');
    }

    public function reservasActivas()
    {
        return $this->reservas()->where('estatus', 'activa');
    }

    public function getRouteKeyName()
    {
        return 'id_evento';
    }

    /**
     * Duración de cada horario reservable en minutos, o null cuando la ventana no
     * se particiona. Enmienda F6: la columna integer no tiene CHECK, así que un
     * valor null o < 1 significa "un solo slot = la ventana completa".
     */
    public function duracionSlotMinutos(): ?int
    {
        $duracion = $this->loadMissing('practica')->practica?->duracion_estimada;

        return ($duracion !== null && (int) $duracion >= 1) ? (int) $duracion : null;
    }

    /**
     * Partición de la ventana en horarios reservables: pasos de duracionSlotMinutos()
     * desde el inicio; solo slots que caben completos. Si no cabe ninguno (ventana
     * menor que la duración, o duración null/<1) el único slot es la ventana entera.
     * Enmienda F6: for sobre intdiv, inmune a loop infinito.
     *
     * @return list<array{inicio: Carbon, fin: Carbon}>
     */
    public function slots(): array
    {
        $duracion = $this->duracionSlotMinutos();
        $totalSlots = $duracion === null
            ? 0
            : intdiv((int) $this->fecha_hora_inicio->diffInMinutes($this->fecha_hora_fin), $duracion);

        if ($totalSlots < 1) {
            return [['inicio' => $this->fecha_hora_inicio->copy(), 'fin' => $this->fecha_hora_fin->copy()]];
        }

        $slots = [];
        for ($i = 0; $i < $totalSlots; $i++) {
            $slots[] = [
                'inicio' => $this->fecha_hora_inicio->copy()->addMinutes($i * $duracion),
                'fin' => $this->fecha_hora_inicio->copy()->addMinutes(($i + 1) * $duracion),
            ];
        }

        return $slots;
    }
}
