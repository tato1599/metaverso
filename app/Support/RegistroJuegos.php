<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class RegistroJuegos
{
    /** @return array<int, array{id: string, label: string, params: array}> */
    public static function tipos(): array
    {
        $registro = config('juegos', []);

        return array_values(array_map(
            fn (string $id, array $def) => ['id' => $id, 'label' => $def['label'], 'params' => $def['params']],
            array_keys($registro),
            $registro,
        ));
    }

    public static function existe(string $tipo): bool
    {
        return array_key_exists($tipo, config('juegos', []));
    }

    /** @return array<int, array<string, mixed>> */
    public static function params(string $tipo): array
    {
        return config("juegos.$tipo.params", []);
    }

    /** @return array<string, mixed> */
    public static function defaults(string $tipo): array
    {
        $out = [];
        foreach (self::params($tipo) as $p) {
            $out[$p['name']] = $p['default'];
        }

        return $out;
    }

    /**
     * Reglas Laravel para validar la config, keyed "config.<param>".
     *
     * @return array<string, array<int, mixed>>
     */
    public static function reglasConfig(string $tipo): array
    {
        $reglas = [];
        foreach (self::params($tipo) as $p) {
            $clave = "config.{$p['name']}";
            $reglas[$clave] = match ($p['tipo']) {
                'number' => ['required', 'integer', 'min:'.$p['min'], 'max:'.$p['max']],
                'select' => ['required', Rule::in(array_column($p['opciones'], 'value'))],
                'checkbox' => ['required', 'boolean'],
                default => ['nullable'],
            };
        }

        return $reglas;
    }

    /**
     * Defaults del tipo con la config guardada encima; ignora claves ajenas al esquema.
     *
     * @return array<string, mixed>
     */
    public static function resolver(?string $tipo, ?array $config): array
    {
        if ($tipo === null || ! self::existe($tipo)) {
            return [];
        }
        $resuelto = self::defaults($tipo);
        foreach ($config ?? [] as $k => $v) {
            if (array_key_exists($k, $resuelto)) {
                $resuelto[$k] = $v;
            }
        }

        return $resuelto;
    }
}
