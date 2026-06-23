<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea las tablas de operacion', function () {
    foreach (['grupos','practicas','inscripciones','eventos_agenda','sesiones_practica','tokens_juego'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue();
    }
    expect(Schema::hasColumns('tokens_juego', ['id_usuario','id_evento','token_hash','plataforma','fecha_expiracion','usado','fecha_uso','ip_origen']))->toBeTrue();
    expect(Schema::hasColumns('sesiones_practica', ['id_evento','id_alumno','id_practica','fecha_inicio','fecha_fin','estatus','calificacion','datos_resultado']))->toBeTrue();
});
