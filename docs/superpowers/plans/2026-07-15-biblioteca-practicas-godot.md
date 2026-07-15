# Biblioteca de prácticas configurables (Godot) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que un coordinador arme prácticas desde el panel eligiendo un tipo de mini-juego genérico y sus parámetros, y que Godot reciba ese tipo + config al canjear el token.

**Architecture:** Un registro backend (`config/juegos.php` + `RegistroJuegos`) es la fuente única de los tipos y sus esquemas de parámetros; alimenta el formulario del panel, la validación del servidor y el contrato de Godot. `practicas.escena_referencia` pasa a ser el id del tipo (validado contra el registro) y una nueva columna `practicas.config` (jsonb) guarda los parámetros. El redeem devuelve el tipo + la config resuelta (defaults del registro + lo guardado).

**Tech Stack:** Laravel 12 (PHP 8.3), PostgreSQL (jsonb), Inertia v2 + React 19, Pest, Tailwind v4.

## Global Constraints

- Autoría de prácticas: solo rol `Coordinador`/`Admin` (middleware `admin`), como hoy `/admin/practicas`.
- Tres tipos v1: `recolecta`, `ensambla`, `circuito` (ids = valor de `escena_referencia`).
- La calificación (0–100) la calcula Godot; el servidor solo la recibe. El servidor NO puntúa.
- Migración aditiva: prácticas existentes quedan con `config` null y deben seguir funcionando (redeem resuelve defaults).
- El maestro nunca escribe JSON: el formulario renderiza campos por tipo desde el registro.
- Tests con base de datos real (Pest + `RefreshDatabase`), siguiendo los patrones existentes en `tests/Feature/`.

---

## File Structure

- Create `config/juegos.php` — registro declarativo de los 3 tipos y sus params.
- Create `app/Support/RegistroJuegos.php` — helper que lee el registro (existe/params/defaults/reglasConfig/resolver).
- Create `database/migrations/2026_07_15_120000_add_config_to_practicas_table.php` — columna `config` jsonb nullable.
- Modify `app/Models/Practica.php` — cast `config` a array + accessor `configResuelta()`.
- Modify `app/Http/Controllers/Admin/PracticaController.php` — validar `escena_referencia` ∈ registro + `config` contra el esquema; pasar `registro` a la vista; renderizar `Admin/Practicas`.
- Create `resources/js/Pages/Admin/Practicas.jsx` — página dedicada con formulario por tipo.
- Modify `app/Http/Controllers/Api/GameAuthController.php` — redeem devuelve la práctica con `escena_referencia` + `config` resuelta.
- Modify `app/Http/Controllers/Lti/LtiLaunchController.php` y `resources/views/lti/abrir-juego.blade.php` — el modo demo muestra tipo + config.
- Modify `database/seeders/DemoSeeder.php` — la práctica demo usa `recolecta` + config válida.
- Create `docs/api/tipos-de-juego.md` — contrato para el equipo de Godot.
- Tests: `tests/Unit/RegistroJuegosTest.php`, `tests/Feature/Admin/PracticaConfigTest.php`, `tests/Feature/Api/RedeemConfigTest.php`; Modify `tests/Feature/Admin/MateriasPracticasTest.php`.

---

## Task 1: Registro de tipos de juego (config + helper)

**Files:**
- Create: `config/juegos.php`
- Create: `app/Support/RegistroJuegos.php`
- Test: `tests/Unit/RegistroJuegosTest.php`

**Interfaces:**
- Produces: `App\Support\RegistroJuegos` con métodos estáticos:
  - `tipos(): array` — lista `[['id'=>string,'label'=>string,'params'=>array], ...]`
  - `existe(string $tipo): bool`
  - `params(string $tipo): array` — lista de campos del tipo (vacío si desconocido)
  - `defaults(string $tipo): array` — `['nombre_param' => valorDefault]`
  - `reglasConfig(string $tipo): array` — reglas Laravel keyed `"config.$nombre"`
  - `resolver(?string $tipo, ?array $config): array` — defaults del tipo con `$config` encima (solo claves conocidas)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/RegistroJuegosTest.php`:

```php
<?php

use App\Support\RegistroJuegos;

it('reconoce los tres tipos y rechaza los desconocidos', function () {
    expect(RegistroJuegos::existe('recolecta'))->toBeTrue()
        ->and(RegistroJuegos::existe('ensambla'))->toBeTrue()
        ->and(RegistroJuegos::existe('circuito'))->toBeTrue()
        ->and(RegistroJuegos::existe('Lab_Variables'))->toBeFalse();
    expect(collect(RegistroJuegos::tipos())->pluck('id')->all())
        ->toBe(['recolecta', 'ensambla', 'circuito']);
});

it('devuelve los defaults de un tipo', function () {
    expect(RegistroJuegos::defaults('recolecta'))
        ->toBe(['meta_objetos' => 10, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']);
});

it('resuelve la config poniendo lo guardado encima de los defaults', function () {
    $r = RegistroJuegos::resolver('recolecta', ['meta_objetos' => 25]);
    expect($r)->toBe(['meta_objetos' => 25, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']);
});

it('resuelve solo con defaults cuando la config es null', function () {
    expect(RegistroJuegos::resolver('circuito', null))
        ->toBe(['num_estaciones' => 4, 'en_orden' => false, 'tiempo_limite_seg' => 300]);
});

it('ignora claves ajenas al esquema del tipo al resolver', function () {
    $r = RegistroJuegos::resolver('recolecta', ['meta_objetos' => 5, 'hackeo' => 1]);
    expect($r)->not->toHaveKey('hackeo');
});

it('da reglas de validacion por parametro del tipo', function () {
    $reglas = RegistroJuegos::reglasConfig('recolecta');
    expect($reglas)->toHaveKeys(['config.meta_objetos', 'config.tiempo_limite_seg', 'config.dificultad']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/RegistroJuegosTest.php`
Expected: FAIL — `Class "App\Support\RegistroJuegos" not found`.

- [ ] **Step 3: Create the registry config**

Create `config/juegos.php`:

```php
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
```

- [ ] **Step 4: Create the helper**

Create `app/Support/RegistroJuegos.php`:

```php
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
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/RegistroJuegosTest.php`
Expected: PASS (6 passed).

- [ ] **Step 6: Commit**

```bash
git add config/juegos.php app/Support/RegistroJuegos.php tests/Unit/RegistroJuegosTest.php
git commit -m "feat(juegos): registro de tipos de mini-juego y helper de config"
```

---

## Task 2: Columna config + modelo + seeder demo

**Files:**
- Create: `database/migrations/2026_07_15_120000_add_config_to_practicas_table.php`
- Modify: `app/Models/Practica.php`
- Modify: `database/seeders/DemoSeeder.php:43`
- Test: `tests/Feature/PracticaConfigModeloTest.php`

**Interfaces:**
- Consumes: `App\Support\RegistroJuegos::resolver()` (Task 1).
- Produces: `Practica` con `$casts['config'] => 'array'` y método `configResuelta(): array` que devuelve `RegistroJuegos::resolver($this->escena_referencia, $this->config)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PracticaConfigModeloTest.php`:

```php
<?php

use App\Models\Materia;
use App\Models\Practica;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('guarda y lee la config como array', function () {
    $m = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $p = Practica::create([
        'id_materia' => $m->id_materia, 'titulo' => 'P', 'orden' => 1,
        'escena_referencia' => 'recolecta', 'config' => ['meta_objetos' => 25],
    ]);

    expect($p->fresh()->config)->toBe(['meta_objetos' => 25]);
});

it('configResuelta mezcla lo guardado con los defaults del tipo', function () {
    $m = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $p = Practica::create([
        'id_materia' => $m->id_materia, 'titulo' => 'P', 'orden' => 1,
        'escena_referencia' => 'recolecta', 'config' => ['meta_objetos' => 25],
    ]);

    expect($p->configResuelta())
        ->toBe(['meta_objetos' => 25, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']);
});

it('configResuelta devuelve solo defaults cuando config es null (practica heredada)', function () {
    $m = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $p = Practica::create([
        'id_materia' => $m->id_materia, 'titulo' => 'P', 'orden' => 1,
        'escena_referencia' => 'circuito', 'config' => null,
    ]);

    expect($p->configResuelta())
        ->toBe(['num_estaciones' => 4, 'en_orden' => false, 'tiempo_limite_seg' => 300]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/PracticaConfigModeloTest.php`
Expected: FAIL — columna `config` inexistente / método `configResuelta` no definido.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_15_120000_add_config_to_practicas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practicas', function (Blueprint $t) {
            // Parámetros del mini-juego. Nullable: las prácticas previas resuelven
            // defaults desde el registro (config/juegos.php).
            $t->jsonb('config')->nullable()->after('escena_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('practicas', function (Blueprint $t) {
            $t->dropColumn('config');
        });
    }
};
```

- [ ] **Step 4: Update the model**

Modify `app/Models/Practica.php` — reemplaza el contenido por:

```php
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
```

- [ ] **Step 5: Update the demo seeder**

Modify `database/seeders/DemoSeeder.php` línea 43 — cambia la creación de la práctica demo a un tipo válido con config:

```php
$practica = Practica::create(['id_materia' => $materia->id_materia, 'titulo' => 'Práctica 1: Variables', 'descripcion' => 'Introducción', 'objetivos' => 'Comprender variables', 'duracion_estimada' => 30, 'orden' => 1, 'escena_referencia' => 'recolecta', 'config' => ['meta_objetos' => 8, 'tiempo_limite_seg' => 90, 'dificultad' => 'facil']]);
```

- [ ] **Step 6: Run the migration and the test**

Run: `php artisan migrate --env=testing --force && ./vendor/bin/pest tests/Feature/PracticaConfigModeloTest.php`
Expected: PASS (3 passed).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_15_120000_add_config_to_practicas_table.php app/Models/Practica.php database/seeders/DemoSeeder.php tests/Feature/PracticaConfigModeloTest.php
git commit -m "feat(practicas): columna config + configResuelta() + seed demo con tipo valido"
```

---

## Task 3: Validación en PracticaController (tipo + config)

**Files:**
- Modify: `app/Http/Controllers/Admin/PracticaController.php` (método `reglas()` y store/update)
- Test: `tests/Feature/Admin/PracticaConfigTest.php`
- Modify: `tests/Feature/Admin/MateriasPracticasTest.php` (usar tipos válidos)

**Interfaces:**
- Consumes: `RegistroJuegos::existe()`, `RegistroJuegos::reglasConfig()` (Task 1); columna `config` (Task 2).
- Produces: store/update validan `escena_referencia` ∈ registro y `config.*` según el tipo, y persisten `config`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/PracticaConfigTest.php`:

```php
<?php

use App\Models\Materia;
use App\Models\Practica;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function coordinador(): Usuario
{
    $rol = Rol::firstOrCreate(['nombre' => 'Coordinador']);

    return Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'c@b.com', 'nombre' => 'C', 'apellidos' => 'O']);
}

function materiaBase(): Materia
{
    return Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
}

it('crea una practica con tipo valido y config valida', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'recolecta',
            'config' => ['meta_objetos' => 15, 'tiempo_limite_seg' => 100, 'dificultad' => 'dificil'],
        ])->assertSessionHasNoErrors();

    expect(Practica::sole()->config)
        ->toBe(['meta_objetos' => 15, 'tiempo_limite_seg' => 100, 'dificultad' => 'dificil']);
});

it('rechaza un tipo de juego desconocido', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'Lab_Inexistente',
            'config' => [],
        ])->assertSessionHasErrors('escena_referencia');

    expect(Practica::count())->toBe(0);
});

it('rechaza un parametro fuera de rango', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'recolecta',
            'config' => ['meta_objetos' => 0, 'tiempo_limite_seg' => 100, 'dificultad' => 'media'],
        ])->assertSessionHasErrors('config.meta_objetos');

    expect(Practica::count())->toBe(0);
});

it('rechaza una opcion fuera del enum', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'recolecta',
            'config' => ['meta_objetos' => 5, 'tiempo_limite_seg' => 100, 'dificultad' => 'imposible'],
        ])->assertSessionHasErrors('config.dificultad');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Admin/PracticaConfigTest.php`
Expected: FAIL — hoy `escena_referencia` acepta cualquier string y `config` no se valida ni persiste.

- [ ] **Step 3: Update the controller validation**

En `app/Http/Controllers/Admin/PracticaController.php`:

Agrega el import arriba (junto a los demás `use`):

```php
use App\Support\RegistroJuegos;
```

Reemplaza el método `reglas()` por (el `escena_referencia` pasa a enum del registro):

```php
    /**
     * @return array<string, mixed>
     */
    private function reglas(): array
    {
        return [
            'id_materia' => ['required', 'integer', Rule::exists('materias', 'id_materia')],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'objetivos' => ['nullable', 'string'],
            'orden' => ['required', 'integer', 'min:1'],
            'duracion_estimada' => ['nullable', 'integer', 'min:1'],
            'escena_referencia' => ['required', 'string', Rule::in(collect(RegistroJuegos::tipos())->pluck('id'))],
        ];
    }

    /**
     * Valida la request incluyendo config.* según el tipo elegido.
     *
     * @return array<string, mixed>
     */
    private function validarConTipo(Request $request): array
    {
        $tipo = $request->input('escena_referencia');
        $reglas = $this->reglas();
        if (is_string($tipo) && RegistroJuegos::existe($tipo)) {
            $reglas = array_merge($reglas, RegistroJuegos::reglasConfig($tipo));
        }

        return $request->validate($reglas);
    }
```

En `store()` y `update()`, reemplaza la llamada actual a `$request->validate($this->reglas())` por `$datos = $this->validarConTipo($request);` (mantén el resto del cuerpo — la transacción de `duracion_estimada` en update, etc.). Asegúrate de que `$datos` incluya `config` (viene de `validarConTipo`) y se pase al `create`/`update`.

Nota: si `store()`/`update()` hoy hacen `Practica::create($request->validate($this->reglas()))` o similar, cambia a usar `$datos` de `validarConTipo`. Verifica que `config` quede en `$datos` (lo está, porque `reglasConfig` agrega claves `config.*` y Laravel devuelve el `config` anidado).

- [ ] **Step 4: Update the existing MateriasPracticasTest to valid types**

En `tests/Feature/Admin/MateriasPracticasTest.php`, cambia los `escena_referencia` de texto libre por tipos válidos y agrega la `config` correspondiente donde el test postea al controlador:
- Línea ~59: `'escena_referencia' => 'nivel_demo'` → `'escena_referencia' => 'recolecta'` y agrega `'config' => ['meta_objetos' => 10, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']` al payload.
- Línea ~205: `'escena_referencia' => 'nivel_soldadura_01'` → `'escena_referencia' => 'ensambla'` y `'config' => ['num_piezas' => 5, 'tiempo_limite_seg' => 180, 'reintentos' => true]`.
- Línea ~211: la aserción `->and($practica->escena_referencia)->toBe('nivel_soldadura_01')` → `->toBe('ensambla')`.
- Línea ~252: `'escena_referencia' => 'nivel_demo'` → `'escena_referencia' => 'recolecta'` + la misma `config` de recolecta.

(Abre el archivo y ajusta cada payload que POSTea al controlador; los `Practica::create` directos que no pasan por el controlador pueden quedarse, pero por coherencia cámbialos también a `'recolecta'`.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/Admin/PracticaConfigTest.php tests/Feature/Admin/MateriasPracticasTest.php`
Expected: PASS (todos verde).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/PracticaController.php tests/Feature/Admin/PracticaConfigTest.php tests/Feature/Admin/MateriasPracticasTest.php
git commit -m "feat(practicas): validar tipo de juego y config contra el registro"
```

---

## Task 4: Redeem devuelve tipo + config resuelta

**Files:**
- Modify: `app/Http/Controllers/Api/GameAuthController.php` (método `redeem`, y `ltiRedeem` si arma su propia respuesta)
- Test: `tests/Feature/Api/RedeemConfigTest.php`

**Interfaces:**
- Consumes: `Practica::configResuelta()` (Task 2).
- Produces: la respuesta de `POST /api/game/redeem` incluye `practica.escena_referencia` y `practica.config` (resuelta).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/RedeemConfigTest.php`. Basa el armado del token/evento/inscripción en el patrón de `tests/Feature/RedeemTokenTest.php` (ábrelo y reutiliza su setup). El test debe:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redeem incluye escena_referencia y config resuelta de la practica', function () {
    // Reutiliza el setup de RedeemTokenTest: crea usuario/alumno, materia, grupo,
    // inscripción, práctica ('recolecta' con config ['meta_objetos'=>15]),
    // evento y un TokenJuego válido; obtén el token en claro $tokenPlano.
    // ... (setup) ...

    $r = $this->postJson('/api/game/redeem', ['token' => $tokenPlano])->assertOk();

    $r->assertJsonPath('practica.escena_referencia', 'recolecta');
    expect($r->json('practica.config'))
        ->toBe(['meta_objetos' => 15, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']);
});

it('una practica sin config resuelve defaults en el redeem', function () {
    // igual, pero práctica 'circuito' con config null.
    $r = $this->postJson('/api/game/redeem', ['token' => $tokenPlano])->assertOk();
    expect($r->json('practica.config'))
        ->toBe(['num_estaciones' => 4, 'en_orden' => false, 'tiempo_limite_seg' => 300]);
});
```

Completa el setup copiándolo de `tests/Feature/RedeemTokenTest.php` (mismo hashing de `token_hash`, `fecha_expiracion`, etc.).

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Api/RedeemConfigTest.php`
Expected: FAIL — `practica.config` ausente o sin resolver defaults.

- [ ] **Step 3: Update the redeem response**

En `app/Http/Controllers/Api/GameAuthController.php`, dentro de `redeem()`, cambia la clave `practica` de la respuesta para inyectar la config resuelta:

```php
        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'alumno' => $usuario->alumno,
            'practica' => array_merge(
                $evento->practica->toArray(),
                ['config' => $evento->practica->configResuelta()],
            ),
            'evento' => $evento->only(['id_evento','fecha_hora_inicio','fecha_hora_fin','estatus']),
        ]);
```

Si `ltiRedeem()` arma su propia respuesta con la práctica, aplica el mismo `array_merge(..., ['config' => $practica->configResuelta()])` ahí.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Api/RedeemConfigTest.php`
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/GameAuthController.php tests/Feature/Api/RedeemConfigTest.php
git commit -m "feat(game): redeem devuelve tipo y config resuelta de la practica"
```

---

## Task 5: Página dedicada de Prácticas con formulario por tipo

**Files:**
- Create: `resources/js/Pages/Admin/Practicas.jsx`
- Modify: `app/Http/Controllers/Admin/PracticaController.php` (método `index`: renderizar `Admin/Practicas` y pasar `registro`)
- Test: `tests/Feature/Admin/PracticaPaginaTest.php`

**Interfaces:**
- Consumes: `RegistroJuegos::tipos()` (Task 1); validación/persistencia de config (Task 3).
- Produces: `/admin/practicas` (GET) renderiza `Admin/Practicas` con props `registro`, `filas`, `campos`, `materias`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/PracticaPaginaTest.php`:

```php
<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('la pagina de practicas renderiza con el registro de tipos', function () {
    $rol = Rol::firstOrCreate(['nombre' => 'Coordinador']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'c@b.com', 'nombre' => 'C', 'apellidos' => 'O']);

    $this->actingAs($u)
        ->get('/admin/practicas')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Practicas')
            ->has('registro', 3)
            ->where('registro.0.id', 'recolecta')
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Admin/PracticaPaginaTest.php`
Expected: FAIL — hoy renderiza `Admin/Recurso` sin prop `registro`.

- [ ] **Step 3: Update the controller index**

En `PracticaController@index`, cambia `Inertia::render('Admin/Recurso', [...])` por `Inertia::render('Admin/Practicas', [...])` y agrega al arreglo de props:

```php
            'registro' => \App\Support\RegistroJuegos::tipos(),
```

Mantén las props existentes (`filas`, `campos`, `materias`/`opcionesMaterias`) y agrega `config` a cada fila del `map` de `filas`:

```php
                'config' => $p->config,
```

Quita del arreglo `campos` el campo `escena_referencia` de texto libre (lo maneja la nueva página por tipo). Deja los campos base: id_materia, titulo, descripcion, objetivos, orden, duracion_estimada.

- [ ] **Step 4: Create the React page**

Create `resources/js/Pages/Admin/Practicas.jsx`. Reutiliza los componentes existentes (`AdminNav`, `Table`, `Modal`, `FormField` con `Select`/`TextInput`/`Textarea`, `Button`, `AppLayout`) siguiendo el patrón de `resources/js/Pages/Admin/Recurso.jsx`. La diferencia: un `<Select>` "Tipo de práctica" alimentado por `registro`; al cambiar el tipo, renderiza los `params` de ese tipo (number → input number con min/max; select → Select con opciones; checkbox → checkbox) y arma un objeto `config`. Al enviar, postea los campos base + `escena_referencia` (el tipo) + `config`.

```jsx
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminNav from '../../Components/AdminNav';
import Button from '../../Components/Button';
import FormField, { Select, TextInput, Textarea } from '../../Components/FormField';
import Modal from '../../Components/Modal';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

const RUTA = '/admin/practicas';

function ParamControl({ param, value, onChange }) {
    if (param.tipo === 'select') {
        return (
            <Select id={param.name} value={value ?? param.default} onChange={(e) => onChange(e.target.value)}>
                {param.opciones.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </Select>
        );
    }
    if (param.tipo === 'checkbox') {
        return (
            <input
                id={param.name}
                type="checkbox"
                checked={Boolean(value)}
                onChange={(e) => onChange(e.target.checked)}
            />
        );
    }
    return (
        <TextInput
            id={param.name}
            type="number"
            min={param.min}
            max={param.max}
            value={value ?? param.default}
            onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}
        />
    );
}

function configPorDefecto(tipoDef) {
    const c = {};
    for (const p of tipoDef?.params ?? []) c[p.name] = p.default;
    return c;
}

export default function Practicas({ registro, filas, materias }) {
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState(null);

    const form = useForm({
        id_materia: '', titulo: '', descripcion: '', objetivos: '',
        orden: 1, duracion_estimada: '',
        escena_referencia: registro[0]?.id ?? '',
        config: configPorDefecto(registro[0]),
    });

    const tipoDef = useMemo(
        () => registro.find((t) => t.id === form.data.escena_referencia),
        [registro, form.data.escena_referencia],
    );

    function abrirNuevo() {
        setEditando(null);
        form.reset();
        form.setData('config', configPorDefecto(registro[0]));
        setAbierto(true);
    }

    function abrirEditar(fila) {
        setEditando(fila.id_practica);
        const def = registro.find((t) => t.id === fila.escena_referencia) ?? registro[0];
        form.setData({
            id_materia: fila.id_materia, titulo: fila.titulo, descripcion: fila.descripcion ?? '',
            objetivos: fila.objetivos ?? '', orden: fila.orden, duracion_estimada: fila.duracion_estimada ?? '',
            escena_referencia: fila.escena_referencia,
            config: { ...configPorDefecto(def), ...(fila.config ?? {}) },
        });
        setAbierto(true);
    }

    function cambiarTipo(id) {
        const def = registro.find((t) => t.id === id);
        form.setData((d) => ({ ...d, escena_referencia: id, config: configPorDefecto(def) }));
    }

    function setParam(name, value) {
        form.setData((d) => ({ ...d, config: { ...d.config, [name]: value } }));
    }

    function enviar(e) {
        e.preventDefault();
        const opts = { onSuccess: () => setAbierto(false), preserveScroll: true };
        if (editando) form.put(`${RUTA}/${editando}`, opts);
        else form.post(RUTA, opts);
    }

    return (
        <AppLayout>
            <AdminNav />
            <div className="header">
                <h1>Prácticas</h1>
                <Button onClick={abrirNuevo}>Nueva práctica</Button>
            </div>

            <Table
                columns={[
                    { key: 'materia_nombre', label: 'Materia' },
                    { key: 'titulo', label: 'Título' },
                    { key: 'escena_referencia', label: 'Tipo', mono: true },
                    { key: 'orden', label: 'Orden', mono: true },
                ]}
                rows={filas}
                actions={(fila) => (
                    <>
                        <Button variant="ghost" onClick={() => abrirEditar(fila)}>Editar</Button>
                        <Button variant="ghost" onClick={() => router.delete(`${RUTA}/${fila.id_practica}`, { preserveScroll: true })}>Borrar</Button>
                    </>
                )}
            />

            <Modal open={abierto} onClose={() => setAbierto(false)} title={editando ? 'Editar práctica' : 'Nueva práctica'}>
                <form onSubmit={enviar}>
                    <FormField label="Materia" error={form.errors.id_materia}>
                        <Select value={form.data.id_materia} onChange={(e) => form.setData('id_materia', e.target.value)} required>
                            <option value="">Elige…</option>
                            {materias.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                        </Select>
                    </FormField>

                    <FormField label="Título" error={form.errors.titulo}>
                        <TextInput value={form.data.titulo} onChange={(e) => form.setData('titulo', e.target.value)} required />
                    </FormField>

                    <FormField label="Descripción" error={form.errors.descripcion}>
                        <Textarea value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} />
                    </FormField>

                    <FormField label="Objetivos" error={form.errors.objetivos}>
                        <Textarea value={form.data.objetivos} onChange={(e) => form.setData('objetivos', e.target.value)} />
                    </FormField>

                    <FormField label="Orden" error={form.errors.orden}>
                        <TextInput type="number" min={1} value={form.data.orden} onChange={(e) => form.setData('orden', Number(e.target.value))} required />
                    </FormField>

                    <FormField label="Duración estimada (min)" error={form.errors.duracion_estimada}>
                        <TextInput type="number" min={1} value={form.data.duracion_estimada} onChange={(e) => form.setData('duracion_estimada', e.target.value === '' ? '' : Number(e.target.value))} />
                    </FormField>

                    <FormField label="Tipo de práctica" error={form.errors.escena_referencia}>
                        <Select value={form.data.escena_referencia} onChange={(e) => cambiarTipo(e.target.value)} required>
                            {registro.map((t) => <option key={t.id} value={t.id}>{t.label}</option>)}
                        </Select>
                    </FormField>

                    {(tipoDef?.params ?? []).map((p) => (
                        <FormField key={p.name} label={p.label} error={form.errors[`config.${p.name}`]}>
                            <ParamControl param={p} value={form.data.config[p.name]} onChange={(v) => setParam(p.name, v)} />
                        </FormField>
                    ))}

                    <div className="acciones">
                        <Button type="submit" loading={form.processing}>Guardar</Button>
                        <Button type="button" variant="ghost" onClick={() => setAbierto(false)}>Cancelar</Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
```

Nota: ajusta los nombres de props de `Table`/`Modal`/`Button`/`FormField` a la firma real de esos componentes (ábrelos en `resources/js/Components/` para confirmar `columns/rows/actions`, `open/onClose/title`, `variant/loading`, `label/error`). Si difieren, adapta las llamadas — no cambies los componentes compartidos.

- [ ] **Step 5: Run the feature test**

Run: `./vendor/bin/pest tests/Feature/Admin/PracticaPaginaTest.php`
Expected: PASS.

- [ ] **Step 6: Build and verify in browser**

Run: `npm run build`
Luego levanta el server (`php artisan serve`), entra como `coordinacion@tecnm.mx` / `password`, ve a Administración → Prácticas, abre "Nueva práctica", cambia el "Tipo de práctica" entre recolecta/ensambla/circuito y confirma que los campos cambian. Crea una y verifica que aparece en la tabla con su tipo. Toma screenshot.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Admin/Practicas.jsx app/Http/Controllers/Admin/PracticaController.php tests/Feature/Admin/PracticaPaginaTest.php
git commit -m "feat(panel): pagina de practicas con formulario por tipo de juego"
```

---

## Task 6: Modo demo muestra tipo + config

**Files:**
- Modify: `app/Http/Controllers/Lti/LtiLaunchController.php:51` (pasar la práctica a la vista)
- Modify: `resources/views/lti/abrir-juego.blade.php` (mostrar escena_referencia + config)
- Test: `tests/Feature/Lti/AbrirJuegoDemoTest.php`

**Interfaces:**
- Consumes: `Practica::configResuelta()` (Task 2).
- Produces: la vista `lti.abrir-juego` recibe `escenaReferencia` (string) y `config` (array) y los muestra en el bloque de modo demo.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Lti/AbrirJuegoDemoTest.php`. Reutiliza el setup de un launch LTI de `tests/Feature/Lti/` que llegue a la vista `lti.abrir-juego` (ábrelos para copiar el patrón). El test debe cargar la vista y afirmar que muestra el tipo:

```php
it('la pantalla de abrir juego muestra el tipo y la config de la practica', function () {
    // ... setup del launch LTI que renderiza lti.abrir-juego con una práctica 'recolecta' ...
    $respuesta->assertSee('recolecta')->assertSee('meta_objetos');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Lti/AbrirJuegoDemoTest.php`
Expected: FAIL — la vista no muestra tipo ni config.

- [ ] **Step 3: Pass the practica data to the view**

En `LtiLaunchController` donde hace `return view('lti.abrir-juego', ['deeplink' => $deeplink, 'practicaId' => $datos->idPractica]);`, carga la práctica y pásala:

```php
        $practica = \App\Models\Practica::find($datos->idPractica);

        return view('lti.abrir-juego', [
            'deeplink' => $deeplink,
            'practicaId' => $datos->idPractica,
            'escenaReferencia' => $practica?->escena_referencia,
            'config' => $practica?->configResuelta() ?? [],
        ]);
```

- [ ] **Step 4: Show it in the demo block**

En `resources/views/lti/abrir-juego.blade.php`, dentro del bloque "Modo demo", agrega antes del botón:

```blade
    @isset($escenaReferencia)
        <p class="muted" style="margin-top:.75rem">El juego cargaría la escena
            <strong>{{ $escenaReferencia }}</strong> con esta configuración:</p>
        <pre style="background:#0b1020;color:#d1d5db;padding:.75rem;border-radius:.5rem;overflow:auto">{{ json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    @endisset
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Lti/AbrirJuegoDemoTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Lti/LtiLaunchController.php resources/views/lti/abrir-juego.blade.php tests/Feature/Lti/AbrirJuegoDemoTest.php
git commit -m "feat(demo): la pantalla de abrir juego muestra el tipo y la config"
```

---

## Task 7: Documento de contrato para Godot

**Files:**
- Create: `docs/api/tipos-de-juego.md`

- [ ] **Step 1: Write the contract doc**

Create `docs/api/tipos-de-juego.md` documentando: el flujo (redeem → escena_referencia + config → cargar escena → jugar → complete con calificacion 0–100 + datos_resultado), y para cada tipo (`recolecta`, `ensambla`, `circuito`) su id de escena, la tabla de parámetros (nombre, tipo, rango/opciones, default) y la regla de calificación esperada. Copia los valores exactos de `config/juegos.php` para que no diverja. Incluye un ejemplo de respuesta JSON del redeem con `practica.config`.

- [ ] **Step 2: Commit**

```bash
git add docs/api/tipos-de-juego.md
git commit -m "docs(api): contrato de tipos de mini-juego para el cliente Godot"
```

---

## Cierre

- [ ] **Suite completa verde**

Run: `./vendor/bin/pest`
Expected: todos los tests pasan (incluidos los del compañero). Si algún test previo de práctica/redeem falla por el cambio de `escena_referencia` a enum, ajústalo a un tipo válido igual que en Task 3 Step 4.

- [ ] **Verificación visual final**

Con el server corriendo y sembrado (`php artisan migrate:fresh --seed`), entra como coordinación → crea prácticas de los 3 tipos → genera un link/lanza el demo y confirma que la pantalla muestra el tipo + config correctos.
