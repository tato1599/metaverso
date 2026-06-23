<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model {
    protected $table = 'inscripciones';
    protected $primaryKey = 'id_inscripcion';
    protected $guarded = [];
    protected $casts = ['fecha_inscripcion' => 'date'];
}
