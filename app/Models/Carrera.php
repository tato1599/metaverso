<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Carrera extends Model {
    protected $table = 'carreras';
    protected $primaryKey = 'id_carrera';
    protected $guarded = [];
    public function alumnos() { return $this->hasMany(Alumno::class, 'id_carrera'); }
}
