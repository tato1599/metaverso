<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model {
    protected $table = 'grupos';
    protected $primaryKey = 'id_grupo';
    protected $guarded = [];
    public function materia() { return $this->belongsTo(Materia::class, 'id_materia'); }
    public function maestro() { return $this->belongsTo(Maestro::class, 'id_maestro'); }
    public function eventos() { return $this->hasMany(EventoAgenda::class, 'id_grupo'); }
    public function ciclo() { return $this->belongsTo(CicloEscolar::class, 'id_ciclo'); }
    public function inscripciones() { return $this->hasMany(Inscripcion::class, 'id_grupo'); }
}
