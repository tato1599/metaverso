<?php

namespace App\Support;

/**
 * Catálogo de juegos/escenas de Godot (config/juegos.php). El admin enlaza cada
 * práctica a uno de estos ids; el resto del sistema solo necesita validar que el
 * id exista y listar las opciones para el selector.
 */
class RegistroJuegos
{
    /** @return array<int, array{id: string, label: string}> */
    public static function juegos(): array
    {
        return array_map(
            fn (string $id, string $label) => ['id' => $id, 'label' => $label],
            array_keys(config('juegos', [])),
            array_values(config('juegos', [])),
        );
    }

    public static function existe(string $id): bool
    {
        return array_key_exists($id, config('juegos', []));
    }

    /** @return array<int, string> */
    public static function ids(): array
    {
        return array_keys(config('juegos', []));
    }
}
