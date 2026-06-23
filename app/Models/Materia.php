<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model {
    protected $table = 'materias';
    protected $primaryKey = 'id_materia';
    protected $guarded = [];
    public function practicas() { return $this->hasMany(Practica::class, 'id_materia'); }
}
