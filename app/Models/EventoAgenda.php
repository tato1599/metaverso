<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EventoAgenda extends Model {
    protected $table = 'eventos_agenda';
    protected $primaryKey = 'id_evento';
    protected $guarded = [];
    protected $casts = ['fecha_hora_inicio' => 'datetime', 'fecha_hora_fin' => 'datetime'];
    public function practica() { return $this->belongsTo(Practica::class, 'id_practica'); }
    public function grupo() { return $this->belongsTo(Grupo::class, 'id_grupo'); }
    public function espacio() { return $this->belongsTo(Espacio::class, 'id_espacio'); }
    public function tokens() { return $this->hasMany(TokenJuego::class, 'id_evento'); }
    public function sesiones() { return $this->hasMany(SesionPractica::class, 'id_evento'); }
}
