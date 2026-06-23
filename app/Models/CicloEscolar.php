<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CicloEscolar extends Model {
    protected $table = 'ciclos_escolares';
    protected $primaryKey = 'id_ciclo';
    protected $guarded = [];
    protected $casts = ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'activo' => 'boolean'];
}
