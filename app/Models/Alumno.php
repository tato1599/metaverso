<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Alumno extends Model {
    protected $table = 'alumnos';
    protected $primaryKey = 'id_alumno';
    protected $guarded = [];
    public function usuario() { return $this->belongsTo(Usuario::class, 'id_usuario'); }
    public function carrera() { return $this->belongsTo(Carrera::class, 'id_carrera'); }
    public function sesiones() { return $this->hasMany(SesionPractica::class, 'id_alumno'); }
}
