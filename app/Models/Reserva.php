<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $table = 'reservas';

    protected $primaryKey = 'id_reserva';

    protected $guarded = [];

    /**
     * Alinea el estado en memoria con el DEFAULT de la tabla:
     * Eloquent create() no rehidrata defaults de BD.
     */
    protected $attributes = ['estatus' => 'activa'];

    public function evento()
    {
        return $this->belongsTo(EventoAgenda::class, 'id_evento');
    }

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno');
    }
}
