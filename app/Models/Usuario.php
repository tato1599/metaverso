<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model {
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    protected $guarded = [];
    public $timestamps = true;
    public function rol() { return $this->belongsTo(Rol::class, 'id_rol'); }
    public function alumno() { return $this->hasOne(Alumno::class, 'id_usuario'); }
    public function maestro() { return $this->hasOne(Maestro::class, 'id_usuario'); }
}
