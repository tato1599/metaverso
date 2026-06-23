<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Practica extends Model {
    protected $table = 'practicas';
    protected $primaryKey = 'id_practica';
    protected $guarded = [];
    public function materia() { return $this->belongsTo(Materia::class, 'id_materia'); }
}
