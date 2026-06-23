<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Maestro extends Model {
    protected $table = 'maestros';
    protected $primaryKey = 'id_maestro';
    protected $guarded = [];
    public function usuario() { return $this->belongsTo(Usuario::class, 'id_usuario'); }
    public function grupos() { return $this->hasMany(Grupo::class, 'id_maestro'); }
}
