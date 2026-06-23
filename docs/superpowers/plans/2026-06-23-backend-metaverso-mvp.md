# Backend Metaverso MVP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el backend Laravel 13 + PostgreSQL con login por magic link y la API REST que el juego de Unreal consumirá para canjear tokens, iniciar sesiones de práctica y enviar calificaciones.

**Architecture:** API stateless. El maestro genera un magic link (token de un solo uso por alumno+evento, guardado como hash). Unreal canjea el token vía `/api/game/redeem`, recibe un Bearer (Sanctum) y opera el ciclo de la sesión de práctica. La calificación se guarda por sesión.

**Tech Stack:** Laravel 13, PHP 8.5, PostgreSQL (jsonb), Laravel Sanctum, Pest.

## Global Constraints

- PHP >= 8.3, Laravel 13, PostgreSQL como driver de BD (`DB_CONNECTION=pgsql`).
- Nombres de tabla en **español, plural, snake_case** (ej. `tokens_juego`).
- El token del magic link **nunca** se guarda en texto plano: solo su **hash** (`hash('sha256', $token)`).
- TTL del magic link configurable vía `MAGIC_LINK_TTL_MINUTES`, **default 120**.
- Calificación numérica rango **0–100**.
- Telemetría del juego en columna `jsonb` (`datos_resultado`).
- Respuestas de error JSON: `{ "message": "...", "errors": {...} }`.
- Cada tarea termina con tests verdes y un commit.

---

### Task 1: Scaffold del proyecto Laravel + PostgreSQL + Sanctum

**Files:**
- Create: proyecto Laravel completo en la raíz del repo
- Modify: `.env`, `.env.example`, `config/database.php` (default pgsql), `phpunit.xml`
- Create: `config/metaverso.php`

**Interfaces:**
- Produces: proyecto Laravel arrancable; conexión `pgsql`; Sanctum instalado; config `metaverso.magic_link_ttl_minutes`.

- [ ] **Step 1: Crear el proyecto Laravel en un temporal y mover archivos a la raíz**

Como la raíz ya tiene `docs/` y `.git`, instalar en temp y copiar:
```bash
cd /Users/danielneri/Documents/escuela
composer create-project laravel/laravel metaverso-tmp "^12.0" --no-interaction
# Laravel 13 puede no estar publicado aún como ^13; usar la versión mayor disponible.
# Verificar versión instalada:
php metaverso-tmp/artisan --version
```
Nota: si `^13.0` no resuelve, usar la última estable (`laravel/laravel`), documentarlo en README. Copiar contenido de `metaverso-tmp/` a `metaverso/` SIN sobrescribir `docs/` ni `.git`:
```bash
rsync -a --exclude='.git' metaverso-tmp/ metaverso/
rm -rf metaverso-tmp
cd metaverso
```

- [ ] **Step 2: Instalar Sanctum**

```bash
php artisan install:api --no-interaction
```
Expected: crea `routes/api.php`, publica config de Sanctum, agrega migración de `personal_access_tokens`.

- [ ] **Step 3: Configurar PostgreSQL en `.env` y `.env.example`**

En ambos archivos:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=metaverso
DB_USERNAME=postgres
DB_PASSWORD=postgres

MAGIC_LINK_TTL_MINUTES=120
GAME_DEEPLINK_SCHEME=tecnm-metaverso
```

- [ ] **Step 4: Crear `config/metaverso.php`**

```php
<?php

return [
    'magic_link_ttl_minutes' => (int) env('MAGIC_LINK_TTL_MINUTES', 120),
    'deeplink_scheme' => env('GAME_DEEPLINK_SCHEME', 'tecnm-metaverso'),
    'calificacion_min' => 0,
    'calificacion_max' => 100,
];
```

- [ ] **Step 5: Crear la base de datos de pruebas y configurar `phpunit.xml`**

Usar una BD pgsql separada para tests. En `phpunit.xml`, dentro de `<php>`:
```xml
<env name="DB_DATABASE" value="metaverso_test"/>
```
Crear ambas BDs:
```bash
createdb metaverso 2>/dev/null; createdb metaverso_test 2>/dev/null; echo "dbs listas"
```

- [ ] **Step 6: Verificar arranque y migraciones base**

```bash
php artisan migrate --env=testing
php artisan test
```
Expected: migraciones base corren sin error; suite por defecto en verde.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: scaffold Laravel + PostgreSQL + Sanctum + config metaverso"
```

---

### Task 2: Migraciones del catálogo académico

**Files:**
- Create: `database/migrations/*_create_roles_table.php`
- Create: `*_create_usuarios_table.php`, `*_create_alumnos_table.php`, `*_create_maestros_table.php`
- Create: `*_create_carreras_table.php`, `*_create_materias_table.php`, `*_create_materia_carrera_table.php`
- Create: `*_create_ciclos_escolares_table.php`, `*_create_espacios_table.php`

**Interfaces:**
- Produces: tablas `roles, usuarios, alumnos, maestros, carreras, materias, materia_carrera, ciclos_escolares, espacios` con sus FKs.

- [ ] **Step 1: Escribir el test de existencia de tablas y columnas**

`tests/Feature/SchemaCatalogoTest.php`:
```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea las tablas del catalogo academico', function () {
    foreach (['roles','usuarios','alumnos','maestros','carreras','materias','materia_carrera','ciclos_escolares','espacios'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue();
    }
    expect(Schema::hasColumns('usuarios', ['id_rol','correo','contrasena_hash','nombre','apellidos','activo']))->toBeTrue();
    expect(Schema::hasColumns('alumnos', ['id_usuario','id_carrera','matricula','semestre_actual','generacion']))->toBeTrue();
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=SchemaCatalogoTest`
Expected: FAIL (tablas no existen).

- [ ] **Step 3: Crear migración `roles`**

```php
Schema::create('roles', function (Blueprint $t) {
    $t->id('id_rol');
    $t->string('nombre');           // Alumno, Maestro, Coordinador, Admin
    $t->string('descripcion')->nullable();
    $t->timestamps();
});
```

- [ ] **Step 4: Crear migración `usuarios`**

```php
Schema::create('usuarios', function (Blueprint $t) {
    $t->id('id_usuario');
    $t->foreignId('id_rol')->constrained('roles', 'id_rol');
    $t->string('correo')->unique();
    $t->string('contrasena_hash')->nullable();
    $t->string('nombre');
    $t->string('apellidos');
    $t->boolean('activo')->default(true);
    $t->timestamp('fecha_creacion')->useCurrent();
    $t->timestamps();
});
```

- [ ] **Step 5: Crear migración `carreras`**

```php
Schema::create('carreras', function (Blueprint $t) {
    $t->id('id_carrera');
    $t->string('clave')->unique();
    $t->string('nombre');
    $t->integer('duracion_semestres');
    $t->timestamps();
});
```

- [ ] **Step 6: Crear migración `alumnos`**

```php
Schema::create('alumnos', function (Blueprint $t) {
    $t->id('id_alumno');
    $t->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
    $t->foreignId('id_carrera')->constrained('carreras', 'id_carrera');
    $t->string('matricula')->unique();
    $t->integer('semestre_actual');
    $t->string('generacion');
    $t->timestamps();
});
```

- [ ] **Step 7: Crear migración `maestros`**

```php
Schema::create('maestros', function (Blueprint $t) {
    $t->id('id_maestro');
    $t->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
    $t->string('numero_empleado')->unique();
    $t->string('grado_academico')->nullable();
    $t->string('especialidad')->nullable();
    $t->timestamps();
});
```

- [ ] **Step 8: Crear migración `materias`**

```php
Schema::create('materias', function (Blueprint $t) {
    $t->id('id_materia');
    $t->string('clave')->unique();
    $t->string('nombre');
    $t->integer('creditos');
    $t->timestamps();
});
```

- [ ] **Step 9: Crear migración `materia_carrera`**

```php
Schema::create('materia_carrera', function (Blueprint $t) {
    $t->id('id_materia_carrera');
    $t->foreignId('id_materia')->constrained('materias', 'id_materia');
    $t->foreignId('id_carrera')->constrained('carreras', 'id_carrera');
    $t->integer('semestre'); // en que semestre de esa carrera
    $t->timestamps();
});
```

- [ ] **Step 10: Crear migración `ciclos_escolares`**

```php
Schema::create('ciclos_escolares', function (Blueprint $t) {
    $t->id('id_ciclo');
    $t->string('nombre'); // 2026-1, 2026-2
    $t->date('fecha_inicio');
    $t->date('fecha_fin');
    $t->boolean('activo')->default(true);
    $t->timestamps();
});
```

- [ ] **Step 11: Crear migración `espacios`**

```php
Schema::create('espacios', function (Blueprint $t) {
    $t->id('id_espacio');
    $t->string('nombre');
    $t->string('tipo'); // fisico / virtual
    $t->integer('capacidad')->nullable();
    $t->timestamps();
});
```

- [ ] **Step 12: Correr el test (debe pasar)**

Run: `php artisan test --filter=SchemaCatalogoTest`
Expected: PASS.

- [ ] **Step 13: Commit**

```bash
git add -A
git commit -m "feat: migraciones del catalogo academico"
```

---

### Task 3: Migraciones de operación (grupos, prácticas, inscripciones, agenda, sesiones, tokens)

**Files:**
- Create: `*_create_grupos_table.php`, `*_create_practicas_table.php`, `*_create_inscripciones_table.php`
- Create: `*_create_eventos_agenda_table.php`, `*_create_sesiones_practica_table.php`, `*_create_tokens_juego_table.php`

**Interfaces:**
- Consumes: tablas de Task 2.
- Produces: tablas `grupos, practicas, inscripciones, eventos_agenda, sesiones_practica, tokens_juego`.

- [ ] **Step 1: Escribir el test de existencia**

`tests/Feature/SchemaOperacionTest.php`:
```php
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
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=SchemaOperacionTest`
Expected: FAIL.

- [ ] **Step 3: Crear migración `grupos`**

```php
Schema::create('grupos', function (Blueprint $t) {
    $t->id('id_grupo');
    $t->foreignId('id_materia')->constrained('materias', 'id_materia');
    $t->foreignId('id_maestro')->constrained('maestros', 'id_maestro');
    $t->foreignId('id_ciclo')->constrained('ciclos_escolares', 'id_ciclo');
    $t->string('clave'); // 3A, 5B
    $t->integer('cupo_maximo');
    $t->timestamps();
});
```

- [ ] **Step 4: Crear migración `practicas`**

```php
Schema::create('practicas', function (Blueprint $t) {
    $t->id('id_practica');
    $t->foreignId('id_materia')->constrained('materias', 'id_materia');
    $t->string('titulo');
    $t->text('descripcion')->nullable();
    $t->text('objetivos')->nullable();
    $t->integer('duracion_estimada')->nullable(); // minutos
    $t->integer('orden')->default(1);
    $t->string('escena_referencia')->nullable(); // id de nivel/escena en Unreal
    $t->timestamps();
});
```

- [ ] **Step 5: Crear migración `inscripciones`**

```php
Schema::create('inscripciones', function (Blueprint $t) {
    $t->id('id_inscripcion');
    $t->foreignId('id_alumno')->constrained('alumnos', 'id_alumno');
    $t->foreignId('id_grupo')->constrained('grupos', 'id_grupo');
    $t->date('fecha_inscripcion');
    $t->string('estatus')->default('activa');
    $t->timestamps();
    $t->unique(['id_alumno', 'id_grupo']);
});
```

- [ ] **Step 6: Crear migración `eventos_agenda`**

```php
Schema::create('eventos_agenda', function (Blueprint $t) {
    $t->id('id_evento');
    $t->foreignId('id_practica')->constrained('practicas', 'id_practica');
    $t->foreignId('id_grupo')->constrained('grupos', 'id_grupo');
    $t->foreignId('id_espacio')->nullable()->constrained('espacios', 'id_espacio');
    $t->dateTime('fecha_hora_inicio');
    $t->dateTime('fecha_hora_fin');
    $t->string('estatus')->default('programado'); // programado, en_curso, finalizado, cancelado
    $t->timestamps();
});
```

- [ ] **Step 7: Crear migración `sesiones_practica`**

```php
Schema::create('sesiones_practica', function (Blueprint $t) {
    $t->id('id_sesion');
    $t->foreignId('id_evento')->constrained('eventos_agenda', 'id_evento');
    $t->foreignId('id_alumno')->constrained('alumnos', 'id_alumno');
    $t->foreignId('id_practica')->constrained('practicas', 'id_practica');
    $t->dateTime('fecha_inicio');
    $t->dateTime('fecha_fin')->nullable();
    $t->string('estatus')->default('en_progreso'); // en_progreso, completada, abandonada
    $t->float('calificacion')->nullable();
    $t->jsonb('datos_resultado')->nullable(); // telemetria cruda del juego
    $t->timestamps();
});
```

- [ ] **Step 8: Crear migración `tokens_juego`**

```php
Schema::create('tokens_juego', function (Blueprint $t) {
    $t->id('id_token');
    $t->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
    $t->foreignId('id_evento')->constrained('eventos_agenda', 'id_evento');
    $t->string('token_hash')->unique();
    $t->string('plataforma')->default('unreal'); // unreal, web, etc.
    $t->dateTime('fecha_expiracion');
    $t->boolean('usado')->default(false);
    $t->dateTime('fecha_uso')->nullable();
    $t->string('ip_origen')->nullable();
    $t->timestamps();
});
```

- [ ] **Step 9: Correr el test (debe pasar)**

Run: `php artisan test --filter=SchemaOperacionTest`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: migraciones de operacion (grupos, practicas, agenda, sesiones, tokens)"
```

---

### Task 4: Modelos Eloquent y relaciones

**Files:**
- Create: `app/Models/Rol.php`, `Usuario.php`, `Alumno.php`, `Maestro.php`, `Carrera.php`, `Materia.php`, `MateriaCarrera.php`, `CicloEscolar.php`, `Espacio.php`, `Grupo.php`, `Practica.php`, `Inscripcion.php`, `EventoAgenda.php`, `SesionPractica.php`, `TokenJuego.php`
- Test: `tests/Feature/ModelosRelacionesTest.php`

**Interfaces:**
- Consumes: tablas de Tasks 2–3.
- Produces: modelos con PK custom y relaciones. Claves: `Usuario` (`id_usuario`, hasOne `alumno`, `maestro`, belongsTo `rol`), `Alumno` (`id_alumno`, belongsTo `usuario`, `carrera`, hasMany `sesiones`), `EventoAgenda` (`id_evento`, belongsTo `practica`,`grupo`,`espacio`, hasMany `tokens`,`sesiones`), `TokenJuego` (`id_token`, belongsTo `usuario`,`evento`), `SesionPractica` (`id_sesion`, casts `datos_resultado`=>array, belongsTo `alumno`,`evento`,`practica`).

- [ ] **Step 1: Escribir el test de relaciones**

```php
<?php
use App\Models\{Usuario, Rol, Alumno, Carrera, EventoAgenda, TokenJuego, SesionPractica};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resuelve las relaciones clave', function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $usuario = Usuario::create([
        'id_rol' => $rol->id_rol, 'correo' => 'a@b.com',
        'nombre' => 'Ana', 'apellidos' => 'Lopez',
    ]);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sistemas', 'duracion_semestres' => 9]);
    $alumno = Alumno::create([
        'id_usuario' => $usuario->id_usuario, 'id_carrera' => $carrera->id_carrera,
        'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025',
    ]);

    expect($usuario->rol->nombre)->toBe('Alumno');
    expect($alumno->usuario->correo)->toBe('a@b.com');
    expect($usuario->alumno->matricula)->toBe('20250001');
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=ModelosRelacionesTest`
Expected: FAIL (modelos no existen / sin PK custom).

- [ ] **Step 3: Crear los modelos del catálogo**

Cada modelo define `$table`, `$primaryKey`, `public $timestamps = true`, `$guarded = []`. Ejemplos:

`app/Models/Rol.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model {
    protected $table = 'roles';
    protected $primaryKey = 'id_rol';
    protected $guarded = [];
    public function usuarios() { return $this->hasMany(Usuario::class, 'id_rol'); }
}
```

`app/Models/Usuario.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model {
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    protected $guarded = [];
    public $timestamps = true;
    const CREATED_AT = 'created_at';
    public function rol() { return $this->belongsTo(Rol::class, 'id_rol'); }
    public function alumno() { return $this->hasOne(Alumno::class, 'id_usuario'); }
    public function maestro() { return $this->hasOne(Maestro::class, 'id_usuario'); }
}
```

`app/Models/Carrera.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Carrera extends Model {
    protected $table = 'carreras';
    protected $primaryKey = 'id_carrera';
    protected $guarded = [];
    public function alumnos() { return $this->hasMany(Alumno::class, 'id_carrera'); }
}
```

`app/Models/Alumno.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Alumno extends Model {
    protected $table = 'alumnos';
    protected $primaryKey = 'id_alumno';
    protected $guarded = [];
    public function usuario() { return $this->belongsTo(Usuario::class, 'id_usuario'); }
    public function carrera() { return $this->belongsTo(Carrera::class, 'id_carrera'); }
    public function sesiones() { return $this->hasMany(SesionPractica::class, 'id_alumno'); }
}
```

`app/Models/Maestro.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Maestro extends Model {
    protected $table = 'maestros';
    protected $primaryKey = 'id_maestro';
    protected $guarded = [];
    public function usuario() { return $this->belongsTo(Usuario::class, 'id_usuario'); }
    public function grupos() { return $this->hasMany(Grupo::class, 'id_maestro'); }
}
```

`app/Models/Materia.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model {
    protected $table = 'materias';
    protected $primaryKey = 'id_materia';
    protected $guarded = [];
    public function practicas() { return $this->hasMany(Practica::class, 'id_materia'); }
}
```

`app/Models/MateriaCarrera.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MateriaCarrera extends Model {
    protected $table = 'materia_carrera';
    protected $primaryKey = 'id_materia_carrera';
    protected $guarded = [];
}
```

`app/Models/CicloEscolar.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CicloEscolar extends Model {
    protected $table = 'ciclos_escolares';
    protected $primaryKey = 'id_ciclo';
    protected $guarded = [];
    protected $casts = ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'activo' => 'boolean'];
}
```

`app/Models/Espacio.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Espacio extends Model {
    protected $table = 'espacios';
    protected $primaryKey = 'id_espacio';
    protected $guarded = [];
}
```

- [ ] **Step 4: Crear los modelos de operación**

`app/Models/Grupo.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model {
    protected $table = 'grupos';
    protected $primaryKey = 'id_grupo';
    protected $guarded = [];
    public function materia() { return $this->belongsTo(Materia::class, 'id_materia'); }
    public function maestro() { return $this->belongsTo(Maestro::class, 'id_maestro'); }
    public function eventos() { return $this->hasMany(EventoAgenda::class, 'id_grupo'); }
}
```

`app/Models/Practica.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Practica extends Model {
    protected $table = 'practicas';
    protected $primaryKey = 'id_practica';
    protected $guarded = [];
    public function materia() { return $this->belongsTo(Materia::class, 'id_materia'); }
}
```

`app/Models/Inscripcion.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model {
    protected $table = 'inscripciones';
    protected $primaryKey = 'id_inscripcion';
    protected $guarded = [];
    protected $casts = ['fecha_inscripcion' => 'date'];
}
```

`app/Models/EventoAgenda.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EventoAgenda extends Model {
    protected $table = 'eventos_agenda';
    protected $primaryKey = 'id_evento';
    protected $guarded = [];
    protected $casts = ['fecha_hora_inicio' => 'datetime', 'fecha_hora_fin' => 'datetime'];
    public function practica() { return $this->belongsTo(Practica::class, 'id_practica'); }
    public function grupo() { return $this->belongsTo(Grupo::class, 'id_grupo'); }
    public function espacio() { return $this->belongsTo(Espacio::class, 'id_espacio'); }
    public function tokens() { return $this->hasMany(TokenJuego::class, 'id_evento'); }
    public function sesiones() { return $this->hasMany(SesionPractica::class, 'id_evento'); }
}
```

`app/Models/SesionPractica.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SesionPractica extends Model {
    protected $table = 'sesiones_practica';
    protected $primaryKey = 'id_sesion';
    protected $guarded = [];
    protected $casts = [
        'fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime',
        'calificacion' => 'float', 'datos_resultado' => 'array',
    ];
    public function alumno() { return $this->belongsTo(Alumno::class, 'id_alumno'); }
    public function evento() { return $this->belongsTo(EventoAgenda::class, 'id_evento'); }
    public function practica() { return $this->belongsTo(Practica::class, 'id_practica'); }
}
```

`app/Models/TokenJuego.php`:
```php
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
```

- [ ] **Step 5: Correr el test (debe pasar)**

Run: `php artisan test --filter=ModelosRelacionesTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: modelos Eloquent y relaciones"
```

---

### Task 5: Servicio de magic link (generación)

**Files:**
- Create: `app/Services/MagicLinkService.php`
- Test: `tests/Feature/MagicLinkServiceTest.php`

**Interfaces:**
- Consumes: `TokenJuego`, `config('metaverso.*')`.
- Produces: `MagicLinkService::generar(int $idUsuario, int $idEvento, string $plataforma = 'unreal'): array` que retorna `['token' => string_plano, 'modelo' => TokenJuego, 'url' => string, 'deeplink' => string]`. El método `hash(string $token): string` retorna `hash('sha256', $token)`. La URL es `URL::to('/jugar/'.$token)`; el deeplink es `"{scheme}://play?token={$token}"`.

- [ ] **Step 1: Escribir el test**

```php
<?php
use App\Models\{Rol, Usuario, Carrera, Materia, Maestro, CicloEscolar, Grupo, Practica, EventoAgenda, TokenJuego};
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearEventoBasico(): EventoAgenda {
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => 'E1']);
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $prac = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);
    return EventoAgenda::create([
        'id_practica' => $prac->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(),
    ]);
}

it('genera un magic link con token hasheado y expiracion default', function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $evento = crearEventoBasico();

    $res = app(MagicLinkService::class)->generar($usuario->id_usuario, $evento->id_evento);

    expect($res['token'])->toBeString()->not->toBeEmpty();
    $modelo = TokenJuego::first();
    expect($modelo->token_hash)->toBe(hash('sha256', $res['token']));
    expect($modelo->usado)->toBeFalse();
    // default 120 min: expira aprox en 2h
    expect($modelo->fecha_expiracion->diffInMinutes(now()))->toBeGreaterThan(115);
    expect($res['deeplink'])->toContain('tecnm-metaverso://play?token=');
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=MagicLinkServiceTest`
Expected: FAIL (clase no existe).

- [ ] **Step 3: Implementar el servicio**

`app/Services/MagicLinkService.php`:
```php
<?php
namespace App\Services;

use App\Models\TokenJuego;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class MagicLinkService {
    public function hash(string $token): string {
        return hash('sha256', $token);
    }

    public function generar(int $idUsuario, int $idEvento, string $plataforma = 'unreal'): array {
        $token = Str::random(64);
        $ttl = (int) config('metaverso.magic_link_ttl_minutes', 120);

        $modelo = TokenJuego::create([
            'id_usuario' => $idUsuario,
            'id_evento' => $idEvento,
            'token_hash' => $this->hash($token),
            'plataforma' => $plataforma,
            'fecha_expiracion' => now()->addMinutes($ttl),
            'usado' => false,
        ]);

        $scheme = config('metaverso.deeplink_scheme', 'tecnm-metaverso');

        return [
            'token' => $token,
            'modelo' => $modelo,
            'url' => URL::to('/jugar/'.$token),
            'deeplink' => "{$scheme}://play?token={$token}",
        ];
    }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php artisan test --filter=MagicLinkServiceTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: MagicLinkService genera tokens hasheados con TTL configurable"
```

---

### Task 6: Endpoint POST /api/game/redeem (canje del magic link)

**Files:**
- Create: `app/Http/Controllers/Api/GameAuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/RedeemTokenTest.php`

**Interfaces:**
- Consumes: `MagicLinkService::hash()`, `TokenJuego`, Sanctum (`$usuario->createToken()`).
- Produces: ruta `POST /api/game/redeem` (body: `{token}`). Respuesta 200: `{ access_token, token_type:'Bearer', alumno:{...}, practica:{...}, evento:{...} }`. Marca token `usado=true`, `fecha_uso=now()`, `ip_origen`. Errores: 422 sin token, 401 token inexistente, 410 expirado o ya usado.

Nota: `Usuario` debe usar el trait `HasApiTokens` de Sanctum para `createToken()`.

- [ ] **Step 1: Agregar HasApiTokens a Usuario**

En `app/Models/Usuario.php`, agregar:
```php
use Laravel\Sanctum\HasApiTokens;
// dentro de la clase:
use HasApiTokens;
```
(El `use HasApiTokens;` interno va como primer statement dentro del cuerpo de la clase.)

- [ ] **Step 2: Escribir el test**

```php
<?php
use App\Models\{Rol, Usuario, Alumno, Carrera};
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $this->usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    Alumno::create(['id_usuario' => $this->usuario->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    $this->evento = crearEventoBasico(); // helper de Task 5 (mover a Pest.php si hace falta)
});

it('canjea un token valido y devuelve bearer', function () {
    $res = app(MagicLinkService::class)->generar($this->usuario->id_usuario, $this->evento->id_evento);
    $r = $this->postJson('/api/game/redeem', ['token' => $res['token']]);
    $r->assertOk()
      ->assertJsonStructure(['access_token','token_type','alumno' => ['matricula'],'practica','evento']);
    expect($res['modelo']->fresh()->usado)->toBeTrue();
});

it('rechaza token inexistente con 401', function () {
    $this->postJson('/api/game/redeem', ['token' => 'noexiste'])->assertStatus(401);
});

it('rechaza token expirado con 410', function () {
    $res = app(MagicLinkService::class)->generar($this->usuario->id_usuario, $this->evento->id_evento);
    $res['modelo']->update(['fecha_expiracion' => now()->subMinute()]);
    $this->postJson('/api/game/redeem', ['token' => $res['token']])->assertStatus(410);
});

it('rechaza token ya usado con 410', function () {
    $res = app(MagicLinkService::class)->generar($this->usuario->id_usuario, $this->evento->id_evento);
    $res['modelo']->update(['usado' => true, 'fecha_uso' => now()]);
    $this->postJson('/api/game/redeem', ['token' => $res['token']])->assertStatus(410);
});
```

Mover el helper `crearEventoBasico()` a `tests/Pest.php` para que esté disponible en todos los tests.

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php artisan test --filter=RedeemTokenTest`
Expected: FAIL (ruta 404).

- [ ] **Step 4: Implementar el controller**

`app/Http/Controllers/Api/GameAuthController.php`:
```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TokenJuego;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class GameAuthController extends Controller {
    public function __construct(private MagicLinkService $magicLink) {}

    public function redeem(Request $request) {
        $data = $request->validate(['token' => 'required|string']);

        $tokenJuego = TokenJuego::where('token_hash', $this->magicLink->hash($data['token']))->first();
        if (! $tokenJuego) {
            return response()->json(['message' => 'Token inválido'], 401);
        }
        if ($tokenJuego->usado || $tokenJuego->fecha_expiracion->isPast()) {
            return response()->json(['message' => 'Token expirado o ya utilizado'], 410);
        }

        $tokenJuego->update([
            'usado' => true,
            'fecha_uso' => now(),
            'ip_origen' => $request->ip(),
        ]);

        $usuario = $tokenJuego->usuario()->with('alumno')->first();
        $evento = $tokenJuego->evento()->with('practica')->first();

        $accessToken = $usuario->createToken('unreal-session', ['game'])->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'alumno' => $usuario->alumno,
            'practica' => $evento->practica,
            'evento' => $evento->only(['id_evento','fecha_hora_inicio','fecha_hora_fin','estatus']),
        ]);
    }
}
```

- [ ] **Step 5: Registrar la ruta**

En `routes/api.php`:
```php
use App\Http\Controllers\Api\GameAuthController;

Route::post('/game/redeem', [GameAuthController::class, 'redeem']);
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan test --filter=RedeemTokenTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: endpoint /api/game/redeem canjea magic link y emite Bearer"
```

---

### Task 7: Endpoints de sesión de práctica (me, start, complete)

**Files:**
- Create: `app/Http/Controllers/Api/GameSessionController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/SesionPracticaTest.php`

**Interfaces:**
- Consumes: Sanctum `auth:sanctum`, `EventoAgenda`, `SesionPractica`, `Alumno`.
- Produces:
  - `GET /api/game/me` → `{ alumno, usuario }` del Bearer.
  - `POST /api/game/sessions` (body: `{id_evento}`) → crea `SesionPractica` (estatus `en_progreso`, `fecha_inicio=now`), responde `{ id_sesion, estatus }`. 201.
  - `POST /api/game/sessions/{id}/complete` (body: `{calificacion (0-100), datos_resultado (array)}`) → set `calificacion`, `datos_resultado`, `fecha_fin=now`, `estatus='completada'`. 403 si la sesión es de otro alumno; 409 si no está `en_progreso`.

- [ ] **Step 1: Escribir el test**

```php
<?php
use App\Models\{Rol, Usuario, Alumno, Carrera, SesionPractica};
use App\Services\MagicLinkService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $this->usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    $this->alumno = Alumno::create(['id_usuario' => $this->usuario->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    $this->evento = crearEventoBasico();
});

it('inicia y completa una sesion guardando calificacion y telemetria', function () {
    Sanctum::actingAs($this->usuario, ['game']);

    $start = $this->postJson('/api/game/sessions', ['id_evento' => $this->evento->id_evento]);
    $start->assertCreated()->assertJsonPath('estatus', 'en_progreso');
    $idSesion = $start->json('id_sesion');

    $done = $this->postJson("/api/game/sessions/{$idSesion}/complete", [
        'calificacion' => 87.5,
        'datos_resultado' => ['aciertos' => 9, 'errores' => 1],
    ]);
    $done->assertOk()->assertJsonPath('estatus', 'completada');

    $sesion = SesionPractica::find($idSesion);
    expect($sesion->calificacion)->toBe(87.5);
    expect($sesion->datos_resultado['aciertos'])->toBe(9);
    expect($sesion->fecha_fin)->not->toBeNull();
});

it('rechaza calificacion fuera de rango con 422', function () {
    Sanctum::actingAs($this->usuario, ['game']);
    $idSesion = $this->postJson('/api/game/sessions', ['id_evento' => $this->evento->id_evento])->json('id_sesion');
    $this->postJson("/api/game/sessions/{$idSesion}/complete", ['calificacion' => 150])->assertStatus(422);
});

it('impide completar la sesion de otro alumno con 403', function () {
    $idSesion = SesionPractica::create([
        'id_evento' => $this->evento->id_evento, 'id_alumno' => $this->alumno->id_alumno,
        'id_practica' => $this->evento->id_practica, 'fecha_inicio' => now(), 'estatus' => 'en_progreso',
    ])->id_sesion;

    $rol = Rol::create(['nombre' => 'Alumno2']);
    $otro = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'c@d.com', 'nombre' => 'C', 'apellidos' => 'D']);
    $carrera = Carrera::first();
    Alumno::create(['id_usuario' => $otro->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250002', 'semestre_actual' => 3, 'generacion' => '2025']);
    Sanctum::actingAs($otro, ['game']);

    $this->postJson("/api/game/sessions/{$idSesion}/complete", ['calificacion' => 80, 'datos_resultado' => []])->assertStatus(403);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=SesionPracticaTest`
Expected: FAIL (rutas 404).

- [ ] **Step 3: Implementar el controller**

`app/Http/Controllers/Api/GameSessionController.php`:
```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\SesionPractica;
use Illuminate\Http\Request;

class GameSessionController extends Controller {
    public function me(Request $request) {
        $usuario = $request->user()->load('alumno');
        return response()->json(['usuario' => $usuario, 'alumno' => $usuario->alumno]);
    }

    public function start(Request $request) {
        $data = $request->validate(['id_evento' => 'required|integer']);
        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403, 'El usuario no es alumno');

        $evento = EventoAgenda::findOrFail($data['id_evento']);

        $sesion = SesionPractica::create([
            'id_evento' => $evento->id_evento,
            'id_alumno' => $alumno->id_alumno,
            'id_practica' => $evento->id_practica,
            'fecha_inicio' => now(),
            'estatus' => 'en_progreso',
        ]);

        return response()->json(['id_sesion' => $sesion->id_sesion, 'estatus' => $sesion->estatus], 201);
    }

    public function complete(Request $request, int $id) {
        $min = config('metaverso.calificacion_min', 0);
        $max = config('metaverso.calificacion_max', 100);
        $data = $request->validate([
            'calificacion' => "required|numeric|min:{$min}|max:{$max}",
            'datos_resultado' => 'nullable|array',
        ]);

        $sesion = SesionPractica::findOrFail($id);
        $alumno = $request->user()->alumno;
        abort_unless($alumno && $sesion->id_alumno === $alumno->id_alumno, 403, 'Sesión de otro alumno');

        if ($sesion->estatus !== 'en_progreso') {
            return response()->json(['message' => 'La sesión no está en progreso'], 409);
        }

        $sesion->update([
            'calificacion' => $data['calificacion'],
            'datos_resultado' => $data['datos_resultado'] ?? null,
            'fecha_fin' => now(),
            'estatus' => 'completada',
        ]);

        return response()->json(['id_sesion' => $sesion->id_sesion, 'estatus' => $sesion->estatus, 'calificacion' => $sesion->calificacion]);
    }
}
```

- [ ] **Step 4: Registrar las rutas protegidas**

En `routes/api.php`:
```php
use App\Http\Controllers\Api\GameSessionController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/game/me', [GameSessionController::class, 'me']);
    Route::post('/game/sessions', [GameSessionController::class, 'start']);
    Route::post('/game/sessions/{id}/complete', [GameSessionController::class, 'complete']);
});
```

- [ ] **Step 5: Correr el test (debe pasar)**

Run: `php artisan test --filter=SesionPracticaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: endpoints de sesion de practica (me, start, complete)"
```

---

### Task 8: Endpoint interno POST /api/links + página /jugar/{token}

**Files:**
- Create: `app/Http/Controllers/Api/LinkController.php`
- Create: `resources/views/jugar.blade.php`
- Modify: `routes/api.php`, `routes/web.php`
- Test: `tests/Feature/GenerarLinkTest.php`

**Interfaces:**
- Consumes: `MagicLinkService::generar()`.
- Produces:
  - `POST /api/links` (body: `{id_usuario, id_evento, plataforma?}`) → `{ url, deeplink, expira }`. 201.
  - `GET /jugar/{token}` (web) → vista con botón que dispara el deeplink `tecnm-metaverso://play?token={token}`.

Nota MVP: `/api/links` queda sin auth por ahora (uso interno/maestro); se protegerá en fase 2. Documentar esto.

- [ ] **Step 1: Escribir el test**

```php
<?php
use App\Models\{Rol, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('genera un link via API', function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $evento = crearEventoBasico();

    $r = $this->postJson('/api/links', ['id_usuario' => $usuario->id_usuario, 'id_evento' => $evento->id_evento]);
    $r->assertCreated()->assertJsonStructure(['url','deeplink','expira']);
    expect($r->json('deeplink'))->toContain('tecnm-metaverso://play?token=');
});

it('la pagina /jugar muestra el boton de abrir juego', function () {
    $this->get('/jugar/cualquier-token')->assertOk()->assertSee('Abrir juego');
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=GenerarLinkTest`
Expected: FAIL.

- [ ] **Step 3: Implementar el controller**

`app/Http/Controllers/Api/LinkController.php`:
```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class LinkController extends Controller {
    public function __construct(private MagicLinkService $magicLink) {}

    public function store(Request $request) {
        $data = $request->validate([
            'id_usuario' => 'required|integer',
            'id_evento' => 'required|integer',
            'plataforma' => 'nullable|string',
        ]);

        $res = $this->magicLink->generar(
            $data['id_usuario'], $data['id_evento'], $data['plataforma'] ?? 'unreal'
        );

        return response()->json([
            'url' => $res['url'],
            'deeplink' => $res['deeplink'],
            'expira' => $res['modelo']->fecha_expiracion->toIso8601String(),
        ], 201);
    }
}
```

- [ ] **Step 4: Crear la vista `resources/views/jugar.blade.php`**

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Metaverso Escolar — Abrir juego</title>
    <style>
        body { font-family: system-ui, sans-serif; display:grid; place-items:center; min-height:100vh; margin:0; background:#0b1020; color:#fff; }
        .card { text-align:center; padding:2rem; }
        a.btn { display:inline-block; margin-top:1rem; padding:1rem 2rem; background:#3b82f6; color:#fff; border-radius:.75rem; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Metaverso Escolar TecNM</h1>
        <p>Tu sesión está lista. Haz clic para abrir el juego.</p>
        <a class="btn" href="{{ $deeplink }}">Abrir juego</a>
    </div>
    <script>window.location.href = @json($deeplink);</script>
</body>
</html>
```

- [ ] **Step 5: Registrar rutas**

En `routes/api.php`:
```php
use App\Http\Controllers\Api\LinkController;
Route::post('/links', [LinkController::class, 'store']);
```
En `routes/web.php`:
```php
Route::get('/jugar/{token}', function (string $token) {
    $scheme = config('metaverso.deeplink_scheme', 'tecnm-metaverso');
    return view('jugar', ['deeplink' => "{$scheme}://play?token={$token}"]);
});
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan test --filter=GenerarLinkTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: endpoint /api/links y pagina /jugar con deeplink"
```

---

### Task 9: Seeders demo + comando de magic link de prueba

**Files:**
- Create: `database/seeders/DemoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `app/Console/Commands/GenerarMagicLink.php`
- Test: `tests/Feature/DemoSeederTest.php`

**Interfaces:**
- Consumes: todos los modelos, `MagicLinkService`.
- Produces: `DemoSeeder` que crea 4 roles, 1 ciclo, 1 carrera, 1 materia, 1 maestro, 1 grupo, 1 espacio, 1 práctica, 3 alumnos, 1 evento. Comando `php artisan metaverso:magic-link {id_usuario} {id_evento}` que imprime URL y deeplink.

- [ ] **Step 1: Escribir el test del seeder**

```php
<?php
use App\Models\{Rol, Alumno, EventoAgenda};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\DemoSeeder;

uses(RefreshDatabase::class);

it('siembra datos demo coherentes', function () {
    $this->seed(DemoSeeder::class);
    expect(Rol::count())->toBe(4);
    expect(Alumno::count())->toBe(3);
    expect(EventoAgenda::count())->toBe(1);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=DemoSeederTest`
Expected: FAIL.

- [ ] **Step 3: Implementar `DemoSeeder`**

`database/seeders/DemoSeeder.php`:
```php
<?php
namespace Database\Seeders;

use App\Models\{Rol, Usuario, Alumno, Maestro, Carrera, Materia, CicloEscolar, Grupo, Espacio, Practica, Inscripcion, EventoAgenda};
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder {
    public function run(): void {
        $roles = collect(['Alumno','Maestro','Coordinador','Admin'])
            ->mapWithKeys(fn ($n) => [$n => Rol::create(['nombre' => $n])->id_rol]);

        $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Ing. en Sistemas Computacionales', 'duracion_semestres' => 9]);
        $materia = Materia::create(['clave' => 'SCD-1027', 'nombre' => 'Programación', 'creditos' => 5]);
        $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);
        $espacio = Espacio::create(['nombre' => 'Laboratorio Virtual A', 'tipo' => 'virtual', 'capacidad' => 30]);

        $uMaestro = Usuario::create(['id_rol' => $roles['Maestro'], 'correo' => 'maestro@tecnm.mx', 'nombre' => 'Laura', 'apellidos' => 'Gómez']);
        $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'EMP001', 'grado_academico' => 'M.C.', 'especialidad' => 'Software']);

        $grupo = Grupo::create(['id_materia' => $materia->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
        $practica = Practica::create(['id_materia' => $materia->id_materia, 'titulo' => 'Práctica 1: Variables', 'descripcion' => 'Introducción', 'objetivos' => 'Comprender variables', 'duracion_estimada' => 30, 'orden' => 1, 'escena_referencia' => 'Lab_Variables']);

        foreach ([['Ana','Ruiz','20250001'],['Beto','Díaz','20250002'],['Caro','León','20250003']] as $i => [$nom,$ape,$mat]) {
            $u = Usuario::create(['id_rol' => $roles['Alumno'], 'correo' => strtolower($nom).'@tecnm.mx', 'nombre' => $nom, 'apellidos' => $ape]);
            $alumno = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => $mat, 'semestre_actual' => 3, 'generacion' => '2025']);
            Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);
        }

        EventoAgenda::create([
            'id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'id_espacio' => $espacio->id_espacio,
            'fecha_hora_inicio' => now()->addDay(), 'fecha_hora_fin' => now()->addDay()->addHour(), 'estatus' => 'programado',
        ]);
    }
}
```

- [ ] **Step 4: Registrar el seeder en `DatabaseSeeder`**

En `database/seeders/DatabaseSeeder.php`, dentro de `run()`:
```php
$this->call(DemoSeeder::class);
```

- [ ] **Step 5: Implementar el comando artisan**

`app/Console/Commands/GenerarMagicLink.php`:
```php
<?php
namespace App\Console\Commands;

use App\Services\MagicLinkService;
use Illuminate\Console\Command;

class GenerarMagicLink extends Command {
    protected $signature = 'metaverso:magic-link {id_usuario} {id_evento} {--plataforma=unreal}';
    protected $description = 'Genera un magic link de prueba para un alumno y evento';

    public function handle(MagicLinkService $magicLink): int {
        $res = $magicLink->generar(
            (int) $this->argument('id_usuario'),
            (int) $this->argument('id_evento'),
            $this->option('plataforma'),
        );
        $this->info('URL:      '.$res['url']);
        $this->info('Deeplink: '.$res['deeplink']);
        $this->info('Expira:   '.$res['modelo']->fecha_expiracion->toDateTimeString());
        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan test --filter=DemoSeederTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: seeder demo + comando metaverso:magic-link"
```

---

### Task 10: README y verificación final

**Files:**
- Create: `README.md`

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: documentación de arranque y suite completa en verde.

- [ ] **Step 1: Escribir `README.md`**

```markdown
# Metaverso Escolar TecNM — Backend

Backend Laravel + PostgreSQL con magic link y API para el juego de Unreal.

## Requisitos
- PHP >= 8.3, Composer, PostgreSQL

## Arranque
```bash
cp .env.example .env
php artisan key:generate
createdb metaverso
php artisan migrate --seed
php artisan serve
```

## Probar el flujo
```bash
# Genera un magic link para el alumno 1 y el evento 1
php artisan metaverso:magic-link 1 1
```
Abre la URL en el navegador → botón "Abrir juego" dispara el deeplink
`tecnm-metaverso://play?token=...` que Unreal debe registrar.

## Flujo de la API (lo que consume Unreal)
1. `POST /api/game/redeem` `{token}` → `{access_token, alumno, practica, evento}`
2. `POST /api/game/sessions` `{id_evento}` (Bearer) → `{id_sesion}`
3. `POST /api/game/sessions/{id}/complete` `{calificacion, datos_resultado}` (Bearer)

## Configuración
- `MAGIC_LINK_TTL_MINUTES` (default 120) — vigencia del magic link.
- `GAME_DEEPLINK_SCHEME` (default tecnm-metaverso) — esquema del deeplink.

## Fuera de alcance (fase 2)
Paneles CRUD, agenda visual, integración Moodle/LTI, calificación agregada,
proteger `/api/links` con auth de maestro.
```

- [ ] **Step 2: Correr toda la suite**

Run: `php artisan test`
Expected: PASS (todos los tests de Tasks 2–9 en verde).

- [ ] **Step 3: Verificar arranque manual**

```bash
php artisan migrate:fresh --seed
php artisan metaverso:magic-link 1 1
```
Expected: imprime URL, deeplink y fecha de expiración.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "docs: README de arranque y verificacion final"
```

---

## Self-Review

**Cobertura del spec:**
- §3 Stack → Task 1. §5 Modelo de datos → Tasks 2–4. §6 Flujo magic link → Tasks 5,6,8. §7 API (redeem/me/sessions/complete/links) → Tasks 6,7,8. §8 Errores → Tasks 6,7 (401/410/422/403/409). §9 Pruebas (5 escenarios) → Tasks 6,7. §10 Datos demo → Task 9. §11 Entregables → Task 10.
- Constraint TTL configurable default 120 → Task 1 (config) + Task 5 (uso) + test en Task 5.
- Constraint token solo hash → Task 5 + Task 6 (lookup por hash).
- Constraint jsonb telemetría → Task 3 + Task 7 (cast array).

**Placeholders:** ninguno; todos los steps tienen código/comandos reales.

**Consistencia de tipos:** `MagicLinkService::generar()` retorna `['token','modelo','url','deeplink']` usado consistente en Tasks 5,6,8,9. `hash()` usado en Tasks 5,6. PKs custom (`id_usuario`, `id_evento`, etc.) consistentes en migraciones, modelos y controllers.
