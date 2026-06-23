<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable {
    use HasApiTokens;
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    protected $guarded = [];
    protected $hidden = ['contrasena_hash'];
    public $timestamps = true;
    public function rol() { return $this->belongsTo(Rol::class, 'id_rol'); }
    public function alumno() { return $this->hasOne(Alumno::class, 'id_usuario'); }
    public function maestro() { return $this->hasOne(Maestro::class, 'id_usuario'); }

    public function esStaffPanel(): bool {
        return in_array(optional($this->rol)->nombre, ['Maestro', 'Coordinador', 'Admin'], true);
    }

    public function esCoordinadorOAdmin(): bool {
        return in_array(optional($this->rol)->nombre, ['Coordinador', 'Admin'], true);
    }
}
