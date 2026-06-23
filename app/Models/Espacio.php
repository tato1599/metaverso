<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Espacio extends Model {
    protected $table = 'espacios';
    protected $primaryKey = 'id_espacio';
    protected $guarded = [];
}
