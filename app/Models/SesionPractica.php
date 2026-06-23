<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SesionPractica extends Model {
    protected $table = 'sesiones_practica';
    protected $primaryKey = 'id_sesion';
    protected $guarded = [];
    protected $casts = [
        'fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime',
        'calificacion' => 'float', 'datos_resultado' => 'array',
    ];
    public function getRouteKeyName(): string { return 'id_sesion'; }
    public function alumno() { return $this->belongsTo(Alumno::class, 'id_alumno'); }
    public function evento() { return $this->belongsTo(EventoAgenda::class, 'id_evento'); }
    public function practica() { return $this->belongsTo(Practica::class, 'id_practica'); }
}
