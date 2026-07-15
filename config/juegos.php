<?php

/*
 * Registro de tipos de mini-juego. Fuente única: alimenta el formulario del panel,
 * la validación del servidor y el contrato con Godot (docs/api/tipos-de-juego.md).
 * El id (la clave) es el valor de practicas.escena_referencia y el nombre de escena
 * que Godot carga. Agregar un tipo = una entrada aquí + su escena en Godot.
 *
 * Cada param: name, label, tipo (number|select|checkbox), default,
 * y según el tipo: min/max (number) u opciones (select).
 */
return [
    'recolecta' => [
        'label' => 'Recolecta — junta objetos',
        'params' => [
            ['name' => 'meta_objetos', 'label' => 'Objetos a juntar', 'tipo' => 'number', 'default' => 10, 'min' => 1, 'max' => 200],
            ['name' => 'tiempo_limite_seg', 'label' => 'Tiempo límite (seg)', 'tipo' => 'number', 'default' => 120, 'min' => 10, 'max' => 3600],
            ['name' => 'dificultad', 'label' => 'Dificultad', 'tipo' => 'select', 'default' => 'media', 'opciones' => [
                ['value' => 'facil', 'label' => 'Fácil'],
                ['value' => 'media', 'label' => 'Media'],
                ['value' => 'dificil', 'label' => 'Difícil'],
            ]],
        ],
    ],
    'ensambla' => [
        'label' => 'Ensambla — ordena la secuencia',
        'params' => [
            ['name' => 'num_piezas', 'label' => 'Número de piezas', 'tipo' => 'number', 'default' => 5, 'min' => 2, 'max' => 50],
            ['name' => 'tiempo_limite_seg', 'label' => 'Tiempo límite (seg)', 'tipo' => 'number', 'default' => 180, 'min' => 10, 'max' => 3600],
            ['name' => 'reintentos', 'label' => 'Permitir reintentos', 'tipo' => 'checkbox', 'default' => true],
        ],
    ],
    'circuito' => [
        'label' => 'Circuito — recorre estaciones',
        'params' => [
            ['name' => 'num_estaciones', 'label' => 'Número de estaciones', 'tipo' => 'number', 'default' => 4, 'min' => 1, 'max' => 50],
            ['name' => 'en_orden', 'label' => 'Respetar el orden', 'tipo' => 'checkbox', 'default' => false],
            ['name' => 'tiempo_limite_seg', 'label' => 'Tiempo límite (seg)', 'tipo' => 'number', 'default' => 300, 'min' => 10, 'max' => 3600],
        ],
    ],
];
