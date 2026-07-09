<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    protected $guarded = [];

    protected $hidden = ['contrasena_hash', 'lti_user_id'];

    /**
     * Alinea el estado en memoria con el DEFAULT de la tabla:
     * Eloquent create() no rehidrata defaults de BD y los middleware leen $u->activo.
     */
    protected $attributes = ['activo' => true];

    public $timestamps = true;

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    public function alumno()
    {
        return $this->hasOne(Alumno::class, 'id_usuario');
    }

    public function maestro()
    {
        return $this->hasOne(Maestro::class, 'id_usuario');
    }

    public function getAuthPasswordName(): string
    {
        return 'contrasena_hash';
    }

    public function esAlumno(): bool
    {
        return optional($this->rol)->nombre === 'Alumno';
    }

    public function esStaffPanel(): bool
    {
        return in_array(optional($this->rol)->nombre, ['Maestro', 'Coordinador', 'Admin'], true);
    }

    public function esCoordinadorOAdmin(): bool
    {
        return in_array(optional($this->rol)->nombre, ['Coordinador', 'Admin'], true);
    }
}
