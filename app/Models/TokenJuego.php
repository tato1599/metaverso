<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TokenJuego extends Model {
    protected $table = 'tokens_juego';
    protected $primaryKey = 'id_token';
    protected $guarded = [];
    protected $casts = [
        'fecha_expiracion' => 'datetime', 'fecha_uso' => 'datetime', 'usado' => 'boolean',
    ];
    public function usuario() { return $this->belongsTo(Usuario::class, 'id_usuario'); }
    public function evento() { return $this->belongsTo(EventoAgenda::class, 'id_evento'); }
}
