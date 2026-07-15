<?php
namespace App\Models;

use App\Support\RegistroJuegos;
use Illuminate\Database\Eloquent\Model;

class Practica extends Model {
    protected $table = 'practicas';
    protected $primaryKey = 'id_practica';
    protected $guarded = [];
    protected $casts = ['config' => 'array'];

    public function materia() { return $this->belongsTo(Materia::class, 'id_materia'); }

    /**
     * Config completa que recibe el juego: defaults del tipo (escena_referencia)
     * con lo guardado encima. Vacío si el tipo no está en el registro.
     *
     * @return array<string, mixed>
     */
    public function configResuelta(): array {
        return RegistroJuegos::resolver($this->escena_referencia, $this->config);
    }
}
