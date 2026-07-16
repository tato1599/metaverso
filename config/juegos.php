<?php

/*
 * Catálogo de juegos/escenas de Godot disponibles. El admin enlaza cada práctica
 * a uno de estos ids (columna practicas.escena_referencia); el magic link del alumno
 * lo lleva vía el token, y Godot carga la escena correspondiente.
 *
 * Clave = id de escena que Godot debe cargar (lo que viaja en escena_referencia).
 * Valor = etiqueta legible que ve el admin en el selector.
 *
 * Agrega una entrada cuando Godot publique una escena nueva.
 */
return [
    'recolecta' => 'Recolecta — junta objetos',
    'ensambla' => 'Ensambla — ordena la secuencia',
    'circuito' => 'Circuito — recorre estaciones',
];
