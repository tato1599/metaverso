<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $table = 'reservas';

    protected $primaryKey = 'id_reserva';

    protected $guarded = [];

    protected $casts = ['inicio_slot' => 'datetime'];

    /**
     * Alinea el estado en memoria con el DEFAULT de la tabla:
     * Eloquent create() no rehidrata defaults de BD.
     */
    protected $attributes = ['estatus' => 'activa'];

    /**
     * Enmienda F1: una reserva creada sin horario explícito cae al inicio de la
     * ventana del evento — misma regla que el backfill de la migración y que los
     * eventos de un solo slot, y mantiene válidos los Reserva::create existentes.
     */
    protected static function booted(): void
    {
        static::creating(function (Reserva $reserva) {
            $reserva->inicio_slot ??= $reserva->evento->fecha_hora_inicio;
        });
    }

    public function evento()
    {
        return $this->belongsTo(EventoAgenda::class, 'id_evento');
    }

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno');
    }
}
