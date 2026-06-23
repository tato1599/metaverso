# Panel de Maestros — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir un panel web para maestros (y coordinadores/admin) que permita iniciar sesión mediante un magic link firmado, ver sus grupos y alumnos, generar magic links del juego para todo un grupo, y consultar resultados/calificaciones.

**Architecture:** Páginas Blade server-rendered protegidas por el guard web de Laravel usando el modelo `Usuario`. El acceso al panel se hace con URLs firmadas temporales (`temporarySignedRoute`) que llaman a un único método de "iniciar sesión en el panel" (LTI-ready). La autorización filtra por dueño del grupo (Maestro ve lo suyo; Coordinador/Admin ven todo).

**Tech Stack:** Laravel 12, PHP 8.5, PostgreSQL, Blade, Pest. Reutiliza `MagicLinkService` y los modelos existentes.

## Global Constraints

- Login del panel: **URL firmada temporal** (`URL::temporarySignedRoute`), TTL `PANEL_LOGIN_TTL_MINUTES` default **30**. Sin tabla nueva, sin SMTP.
- Guard web sobre el modelo `Usuario` (ya extiende `Authenticatable`).
- Solo roles **Maestro, Coordinador, Admin** entran al panel; **Alumno → 403**.
- Autorización: Maestro solo ve grupos donde `grupos.id_maestro = su maestro.id_maestro`; Coordinador/Admin ven todo. Acceso a recurso ajeno → **403**.
- Iniciar sesión del panel debe ser **un único método reutilizable** (para que LTI lo use después).
- Generar links reutiliza `MagicLinkService::generar(int $idUsuario, int $idEvento, string $plataforma='unreal'): array` (retorna `['token','modelo','url','deeplink']`).
- Nombres de tabla/PK existentes en español (`usuarios.id_usuario`, `grupos.id_grupo`, `eventos_agenda.id_evento`, `sesiones_practica.id_sesion`, `inscripciones`, `alumnos.id_alumno`, `maestros.id_maestro`, `roles.nombre`).
- Estilo visual: reutilizar la estética limpia del `/demo` (un layout Blade compartido).
- Cada tarea termina con tests verdes y un commit. PostgreSQL local: user `postgres`/pass `postgres`, DBs `metaverso`/`metaverso_test`. Correr `php artisan config:clear` antes de tests si hay errores de conexión.

## Roles de referencia (de DemoSeeder)
`roles.nombre` ∈ {Alumno, Maestro, Coordinador, Admin}. El maestro demo tiene
`usuarios.id_usuario` con rol Maestro y un registro en `maestros`. Los alumnos
demo (Ana/Beto/Caro) tienen rol Alumno e inscripciones en el grupo 1.

---

### Task 1: Guard web sobre Usuario + helper de rol

**Files:**
- Modify: `config/auth.php`
- Modify: `app/Models/Usuario.php`
- Modify: `config/metaverso.php`
- Modify: `.env.example`
- Test: `tests/Feature/Panel/GuardUsuarioTest.php`

**Interfaces:**
- Produces: guard `web` autenticando contra `Usuario`; `Usuario::esStaffPanel(): bool` (true si rol ∈ {Maestro,Coordinador,Admin}); `Usuario::esCoordinadorOAdmin(): bool`; config `metaverso.panel_login_ttl_minutes` (default 30).

- [ ] **Step 1: Escribir el test**

`tests/Feature/Panel/GuardUsuarioTest.php`:
```php
<?php
use App\Models\{Rol, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('autentica un Usuario con el guard web y expone helpers de rol', function () {
    $rolMaestro = Rol::create(['nombre' => 'Maestro']);
    $rolAlumno = Rol::create(['nombre' => 'Alumno']);

    $maestro = Usuario::create(['id_rol' => $rolMaestro->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $alumno = Usuario::create(['id_rol' => $rolAlumno->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'Y']);

    $this->actingAs($maestro);
    expect(auth()->user()->id_usuario)->toBe($maestro->id_usuario);

    expect($maestro->esStaffPanel())->toBeTrue();
    expect($alumno->esStaffPanel())->toBeFalse();
    expect($maestro->esCoordinadorOAdmin())->toBeFalse();
});

it('marca Coordinador y Admin como staff y coord/admin', function () {
    foreach (['Coordinador','Admin'] as $n) {
        $rol = Rol::create(['nombre' => $n]);
        $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => strtolower($n).'@b.com', 'nombre' => $n, 'apellidos' => 'Z']);
        expect($u->esStaffPanel())->toBeTrue();
        expect($u->esCoordinadorOAdmin())->toBeTrue();
    }
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=GuardUsuarioTest`
Expected: FAIL (el guard web usa el modelo User scaffold y los helpers no existen).

- [ ] **Step 3: Apuntar el provider del guard web a Usuario**

En `config/auth.php`, cambiar el provider `users`:
```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\Usuario::class,
    ],
],
```
(El guard `web` ya usa el provider `users`; con esto autentica `Usuario`.)

- [ ] **Step 4: Agregar helpers de rol y relación rol en Usuario**

En `app/Models/Usuario.php`, agregar dentro de la clase (la relación `rol()` ya existe):
```php
public function esStaffPanel(): bool {
    return in_array(optional($this->rol)->nombre, ['Maestro', 'Coordinador', 'Admin'], true);
}

public function esCoordinadorOAdmin(): bool {
    return in_array(optional($this->rol)->nombre, ['Coordinador', 'Admin'], true);
}
```

- [ ] **Step 5: Config TTL + .env.example**

En `config/metaverso.php` agregar a la lista:
```php
'panel_login_ttl_minutes' => (int) env('PANEL_LOGIN_TTL_MINUTES', 30),
```
En `.env.example`, cerca de las otras vars:
```
PANEL_LOGIN_TTL_MINUTES=30
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=GuardUsuarioTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(panel): guard web sobre Usuario + helpers de rol"
```

---

### Task 2: Login del panel por URL firmada + middleware + logout

**Files:**
- Create: `app/Http/Controllers/Panel/PanelLoginController.php`
- Create: `app/Http/Middleware/EnsurePanelAccess.php`
- Create: `app/Console/Commands/GenerarAccesoPanel.php`
- Create: `resources/views/panel/acceso-invalido.blade.php`
- Modify: `routes/web.php`, `bootstrap/app.php`
- Test: `tests/Feature/Panel/AccesoPanelTest.php`

**Interfaces:**
- Consumes: guard web (Task 1), `Usuario::esStaffPanel()`.
- Produces:
  - Ruta nombrada `panel.acceso` = `GET /panel/acceso/{usuario}` (firmada). Valida firma; si `esStaffPanel()` → `Auth::login($usuario)` y redirige a `panel.dashboard`; si no → 403.
  - `PanelLoginController::establecerSesion(Usuario $u): void` (único punto de login, LTI-ready).
  - Ruta `panel.salir` = `POST /panel/salir`.
  - Middleware alias `panel` (`EnsurePanelAccess`): exige auth + `esStaffPanel()`, si no → 403.
  - Comando `metaverso:panel-acceso {id_usuario}` que imprime la URL firmada.
- Nota: `panel.dashboard` se define en Task 3; en este task la redirección puede apuntar a `route('panel.dashboard')` que existirá. Para que los tests de este task pasen sin Task 3, el test verifica la **sesión autenticada** y el **redirect 302** (no el contenido del dashboard). Define una ruta mínima temporal `panel.dashboard` que retorne `'ok'` y se reemplaza en Task 3.

- [ ] **Step 1: Escribir el test**

`tests/Feature/Panel/AccesoPanelTest.php`:
```php
<?php
use App\Models\{Rol, Usuario};
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function urlAcceso(Usuario $u): string {
    return URL::temporarySignedRoute('panel.acceso', now()->addMinutes(30), ['usuario' => $u->id_usuario]);
}

it('inicia sesion con enlace firmado valido para un Maestro', function () {
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);

    $this->get(urlAcceso($u))->assertRedirect(route('panel.dashboard'));
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($u->id_usuario);
});

it('rechaza enlace con firma manipulada', function () {
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $url = urlAcceso($u) . 'manipulado';
    $this->get($url)->assertForbidden();
    expect(auth()->check())->toBeFalse();
});

it('rechaza a un Alumno aunque el enlace sea valido', function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'Y']);
    $this->get(urlAcceso($u))->assertForbidden();
    expect(auth()->check())->toBeFalse();
});

it('el middleware panel bloquea acceso sin sesion', function () {
    $this->get(route('panel.dashboard'))->assertRedirect(); // a acceso-invalido o login; 302/403
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=AccesoPanelTest`
Expected: FAIL (rutas no existen).

- [ ] **Step 3: Crear el middleware**

`app/Http/Middleware/EnsurePanelAccess.php`:
```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePanelAccess {
    public function handle(Request $request, Closure $next) {
        $u = $request->user();
        abort_unless($u && $u->esStaffPanel(), 403, 'Acceso solo para personal docente.');
        return $next($request);
    }
}
```

- [ ] **Step 4: Registrar el alias del middleware**

En `bootstrap/app.php`, dentro de `->withMiddleware(function (Middleware $middleware) { ... })` agregar (fusionar si ya hay `alias`):
```php
$middleware->alias([
    'panel' => \App\Http\Middleware\EnsurePanelAccess::class,
]);
```

- [ ] **Step 5: Crear el controlador de login**

`app/Http/Controllers/Panel/PanelLoginController.php`:
```php
<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PanelLoginController extends Controller {
    // Punto unico de inicio de sesion del panel (reutilizable por LTI).
    public function establecerSesion(Usuario $usuario): void {
        Auth::login($usuario);
    }

    public function acceso(Request $request, Usuario $usuario) {
        // La firma ya fue validada por el middleware 'signed'.
        if (! $usuario->esStaffPanel()) {
            abort(403, 'Esta cuenta no tiene acceso al panel.');
        }
        $this->establecerSesion($usuario);
        return redirect()->route('panel.dashboard');
    }

    public function salir(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('panel.acceso.invalido');
    }
}
```

- [ ] **Step 6: Crear la vista de acceso inválido**

`resources/views/panel/acceso-invalido.blade.php`:
```blade
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Acceso al panel</title>
<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#0b1020;color:#fff}.c{text-align:center;padding:2rem}</style>
</head><body><div class="c">
<h1>Panel de Maestros</h1>
<p>Tu enlace de acceso no es válido o ya caducó. Pide uno nuevo al coordinador.</p>
</div></body></html>
```

- [ ] **Step 7: Registrar rutas**

En `routes/web.php`:
```php
use App\Http\Controllers\Panel\PanelLoginController;

Route::get('/panel/acceso/{usuario}', [PanelLoginController::class, 'acceso'])
    ->name('panel.acceso')->middleware('signed');
Route::post('/panel/salir', [PanelLoginController::class, 'salir'])->name('panel.salir');
Route::view('/panel/acceso-invalido', 'panel.acceso-invalido')->name('panel.acceso.invalido');

// Ruta temporal del dashboard (se reemplaza en Task 3)
Route::get('/panel', fn () => 'ok')->name('panel.dashboard')->middleware('panel');
```

- [ ] **Step 8: Crear el comando artisan**

`app/Console/Commands/GenerarAccesoPanel.php`:
```php
<?php
namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class GenerarAccesoPanel extends Command {
    protected $signature = 'metaverso:panel-acceso {id_usuario}';
    protected $description = 'Genera un enlace de acceso firmado al panel para un usuario (Maestro/Coordinador/Admin)';

    public function handle(): int {
        $usuario = Usuario::find((int) $this->argument('id_usuario'));
        if (! $usuario) { $this->error('Usuario no encontrado'); return self::FAILURE; }
        if (! $usuario->esStaffPanel()) { $this->error('El usuario no tiene rol con acceso al panel'); return self::FAILURE; }

        $ttl = (int) config('metaverso.panel_login_ttl_minutes', 30);
        $url = URL::temporarySignedRoute('panel.acceso', now()->addMinutes($ttl), ['usuario' => $usuario->id_usuario]);
        $this->info('Enlace de acceso (válido '.$ttl.' min):');
        $this->line($url);
        return self::SUCCESS;
    }
}
```

- [ ] **Step 9: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=AccesoPanelTest`
Expected: PASS. (El test del middleware sin sesión espera un redirect/403 — la ruta `/panel` con middleware `panel` sin sesión y sin guard de login redirige; si Laravel intenta redirigir a `login` inexistente, ajustar el test para `assertForbidden()` en su lugar. Preferir 403: el middleware `panel` aborta 403 cuando `$request->user()` es null, así que el cuarto test debe usar `assertForbidden()`.)

Corrección al cuarto test (aplicar en Step 1 si es necesario): usar
`$this->get(route('panel.dashboard'))->assertForbidden();` porque sin sesión
`$request->user()` es null y el middleware aborta con 403.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat(panel): login por URL firmada, middleware panel, logout y comando de acceso"
```

---

### Task 3: Layout Blade + Dashboard de grupos

**Files:**
- Create: `resources/views/panel/layout.blade.php`
- Create: `resources/views/panel/dashboard.blade.php`
- Create: `app/Http/Controllers/Panel/PanelController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Panel/DashboardTest.php`

**Interfaces:**
- Consumes: middleware `panel`, guard web, modelos `Grupo`, `Maestro`, `Materia`, `CicloEscolar`, `Inscripcion`.
- Produces:
  - `PanelController::dashboard(Request $request)` → vista `panel.dashboard` con `$grupos` (Collection de `Grupo` con `materia`, `ciclo`, conteo de inscripciones).
  - `PanelController::gruposVisibles(Usuario $u)` → query base: si `esCoordinadorOAdmin()` todos los grupos; si Maestro, `Grupo::where('id_maestro', $u->maestro->id_maestro)`.
  - Reemplaza la ruta temporal `panel.dashboard` por el controlador real.

- [ ] **Step 1: Escribir el test**

`tests/Feature/Panel/DashboardTest.php`:
```php
<?php
use App\Models\{Rol, Usuario, Maestro, Materia, CicloEscolar, Grupo};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function staff(string $rolNombre): Usuario {
    $rol = Rol::firstOrCreate(['nombre' => $rolNombre]);
    return Usuario::create(['id_rol' => $rol->id_rol, 'correo' => strtolower($rolNombre).rand(1,99999).'@b.com', 'nombre' => $rolNombre, 'apellidos' => 'T']);
}

function grupoDe(?Maestro $maestro = null): Grupo {
    $mat = Materia::create(['clave' => 'M'.rand(1,99999), 'nombre' => 'Mat', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    if (! $maestro) {
        $u = staff('Maestro');
        $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => 'E'.rand(1,99999)]);
    }
    return Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
}

it('un Maestro ve solo sus grupos', function () {
    $uMaestro = staff('Maestro');
    $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'EMPX']);
    $mio = grupoDe($maestro);
    $ajeno = grupoDe(); // otro maestro

    $this->actingAs($uMaestro);
    $r = $this->get(route('panel.dashboard'));
    $r->assertOk()->assertSee($mio->clave);
    $r->assertDontSee('id_grupo_ajeno_marker'); // no aplica; comprobamos por conteo abajo
    expect($r->viewData('grupos')->pluck('id_grupo'))->toContain($mio->id_grupo);
    expect($r->viewData('grupos')->pluck('id_grupo'))->not->toContain($ajeno->id_grupo);
});

it('un Coordinador ve todos los grupos', function () {
    $g1 = grupoDe(); $g2 = grupoDe();
    $coord = staff('Coordinador');
    $this->actingAs($coord);
    $r = $this->get(route('panel.dashboard'));
    $r->assertOk();
    $ids = $r->viewData('grupos')->pluck('id_grupo');
    expect($ids)->toContain($g1->id_grupo)->toContain($g2->id_grupo);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=DashboardTest`
Expected: FAIL (controlador/vista no existen; ruta devuelve 'ok').

- [ ] **Step 3: Crear el layout Blade**

`resources/views/panel/layout.blade.php`:
```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Panel de Maestros') — Metaverso TecNM</title>
    <style>
        :root{--indigo:#4f46e5;--ink:#1f2937;--bg:#f5f6fa;--line:#e5e7eb}
        *{box-sizing:border-box}body{margin:0;font-family:system-ui,sans-serif;background:var(--bg);color:var(--ink)}
        header{background:var(--indigo);color:#fff;padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center}
        header a{color:#fff;text-decoration:none;font-weight:600}
        main{max-width:1000px;margin:1.5rem auto;padding:0 1rem}
        .card{background:#fff;border:1px solid var(--line);border-radius:.75rem;padding:1.25rem;margin-bottom:1rem}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.6rem;border-bottom:1px solid var(--line)}
        th{font-size:.8rem;text-transform:uppercase;color:#6b7280}
        .btn{display:inline-block;background:var(--indigo);color:#fff;border:0;padding:.55rem 1rem;border-radius:.6rem;text-decoration:none;font-weight:600;cursor:pointer}
        a.row-link{color:var(--indigo);text-decoration:none;font-weight:600}
        form.inline{display:inline}
    </style>
</head>
<body>
    <header>
        <a href="{{ route('panel.dashboard') }}">Metaverso TecNM — Panel</a>
        <span>
            {{ auth()->user()->nombre }} {{ auth()->user()->apellidos }}
            <form class="inline" method="POST" action="{{ route('panel.salir') }}">@csrf
                <button class="btn" style="background:#374151">Salir</button>
            </form>
        </span>
    </header>
    <main>@yield('contenido')</main>
</body>
</html>
```

- [ ] **Step 4: Crear el controlador**

`app/Http/Controllers/Panel/PanelController.php`:
```php
<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Usuario;
use Illuminate\Http\Request;

class PanelController extends Controller {
    public function gruposVisibles(Usuario $u) {
        $q = Grupo::with(['materia', 'ciclo'])->withCount('inscripciones');
        if (! $u->esCoordinadorOAdmin()) {
            $q->where('id_maestro', optional($u->maestro)->id_maestro);
        }
        return $q;
    }

    public function dashboard(Request $request) {
        $grupos = $this->gruposVisibles($request->user())->get();
        return view('panel.dashboard', ['grupos' => $grupos]);
    }
}
```

- [ ] **Step 5: Agregar la relación `inscripciones` y `ciclo` en Grupo (si falta)**

En `app/Models/Grupo.php`, asegurar relaciones (la `materia` ya existe):
```php
public function ciclo() { return $this->belongsTo(CicloEscolar::class, 'id_ciclo'); }
public function inscripciones() { return $this->hasMany(Inscripcion::class, 'id_grupo'); }
public function eventos() { return $this->hasMany(EventoAgenda::class, 'id_grupo'); }
```
(Importar las clases con `use App\Models\...` si el modelo usa imports; si está en el mismo namespace `App\Models`, no hace falta.)

- [ ] **Step 6: Crear la vista dashboard**

`resources/views/panel/dashboard.blade.php`:
```blade
@extends('panel.layout')
@section('titulo', 'Mis grupos')
@section('contenido')
<h1>Grupos</h1>
@forelse ($grupos as $grupo)
    <div class="card">
        <a class="row-link" href="{{ route('panel.grupos.show', $grupo->id_grupo) }}">
            {{ $grupo->clave }} — {{ optional($grupo->materia)->nombre }}
        </a>
        <div style="color:#6b7280;font-size:.9rem">
            Ciclo {{ optional($grupo->ciclo)->nombre }} · {{ $grupo->inscripciones_count }} alumnos
        </div>
    </div>
@empty
    <div class="card">No tienes grupos asignados.</div>
@endforelse
@endsection
```

- [ ] **Step 7: Reemplazar la ruta temporal del dashboard**

En `routes/web.php`, reemplazar la ruta temporal `panel.dashboard` por:
```php
use App\Http\Controllers\Panel\PanelController;

Route::middleware('panel')->group(function () {
    Route::get('/panel', [PanelController::class, 'dashboard'])->name('panel.dashboard');
});
```
(La ruta `panel.grupos.show` se define en Task 4; el `route()` en la vista la referenciará — para que el dashboard test pase, define también en este task una ruta placeholder `panel.grupos.show` que retorne `'ok'` bajo el grupo `panel`, reemplazada en Task 4.)

Placeholder a incluir en el grupo `panel`:
```php
Route::get('/panel/grupos/{grupo}', fn () => 'ok')->name('panel.grupos.show');
```

- [ ] **Step 8: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=DashboardTest`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat(panel): layout + dashboard de grupos con filtro por rol"
```

---

### Task 4: Detalle de grupo (alumnos + eventos) con autorización

**Files:**
- Create: `resources/views/panel/grupo.blade.php`
- Modify: `app/Http/Controllers/Panel/PanelController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Panel/GrupoDetalleTest.php`

**Interfaces:**
- Consumes: `PanelController::gruposVisibles()`, modelos `Grupo`, `Inscripcion`, `Alumno`, `EventoAgenda`, `Practica`.
- Produces:
  - `PanelController::autorizarGrupo(Usuario $u, Grupo $grupo): void` → 403 si el grupo no es visible para el usuario.
  - `PanelController::show(Request $request, Grupo $grupo)` → vista `panel.grupo` con `$grupo`, `$alumnos` (inscritos con su `alumno.usuario`), `$eventos` (con `practica`).
  - Ruta real `panel.grupos.show` = `GET /panel/grupos/{grupo}`.

- [ ] **Step 1: Escribir el test**

`tests/Feature/Panel/GrupoDetalleTest.php`:
```php
<?php
use App\Models\{Rol, Usuario, Maestro, Alumno, Carrera, Materia, CicloEscolar, Grupo, Inscripcion, Practica, EventoAgenda};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('un Maestro abre su grupo y ve alumnos y eventos; un grupo ajeno da 403', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);

    $uMaestro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'm@b.com', 'nombre' => 'Lau', 'apellidos' => 'G']);
    $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'EMP1']);

    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);

    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    $uAlumno = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'ana@b.com', 'nombre' => 'Ana', 'apellidos' => 'R']);
    $alumno = Alumno::create(['id_usuario' => $uAlumno->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);

    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);
    EventoAgenda::create(['id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(), 'estatus' => 'programado']);

    $this->actingAs($uMaestro);
    $r = $this->get(route('panel.grupos.show', $grupo->id_grupo));
    $r->assertOk()->assertSee('Ana')->assertSee('Lab 1');

    // grupo de otro maestro
    $uOtro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'otro@b.com', 'nombre' => 'Otro', 'apellidos' => 'M']);
    $otroMaestro = Maestro::create(['id_usuario' => $uOtro->id_usuario, 'numero_empleado' => 'EMP2']);
    $grupoAjeno = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $otroMaestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '5B', 'cupo_maximo' => 30]);
    $this->get(route('panel.grupos.show', $grupoAjeno->id_grupo))->assertForbidden();
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=GrupoDetalleTest`
Expected: FAIL (ruta placeholder devuelve 'ok').

- [ ] **Step 3: Agregar autorización y `show` al controlador**

En `app/Http/Controllers/Panel/PanelController.php` agregar:
```php
use App\Models\Grupo;

public function autorizarGrupo(Usuario $u, Grupo $grupo): void {
    if ($u->esCoordinadorOAdmin()) return;
    abort_unless($grupo->id_maestro === optional($u->maestro)->id_maestro, 403, 'No puedes ver este grupo.');
}

public function show(Request $request, Grupo $grupo) {
    $this->autorizarGrupo($request->user(), $grupo);
    $grupo->load(['materia', 'ciclo']);
    $alumnos = $grupo->inscripciones()->with('alumno.usuario')->get();
    $eventos = $grupo->eventos()->with('practica')->get();
    return view('panel.grupo', ['grupo' => $grupo, 'alumnos' => $alumnos, 'eventos' => $eventos]);
}
```
Nota: `Grupo` se resuelve por route-model binding con `{grupo}`; como la PK es `id_grupo` (no `id`), agregar en `Grupo`:
```php
public function getRouteKeyName(): string { return 'id_grupo'; }
```

- [ ] **Step 4: Agregar relación `alumno` en Inscripcion (si falta)**

En `app/Models/Inscripcion.php`:
```php
public function alumno() { return $this->belongsTo(Alumno::class, 'id_alumno'); }
```
(con su `use App\Models\Alumno;` si aplica). En `app/Models/Alumno.php` confirmar `usuario()` existe (ya existe).

- [ ] **Step 5: Crear la vista de grupo**

`resources/views/panel/grupo.blade.php`:
```blade
@extends('panel.layout')
@section('titulo', 'Grupo '.$grupo->clave)
@section('contenido')
<a class="row-link" href="{{ route('panel.dashboard') }}">← Volver</a>
<h1>Grupo {{ $grupo->clave }} — {{ optional($grupo->materia)->nombre }}</h1>
<p><a class="btn" href="{{ route('panel.grupos.resultados', $grupo->id_grupo) }}">Ver resultados</a></p>

<div class="card">
    <h2>Alumnos inscritos ({{ $alumnos->count() }})</h2>
    <table>
        <thead><tr><th>Matrícula</th><th>Nombre</th></tr></thead>
        <tbody>
        @foreach ($alumnos as $insc)
            <tr>
                <td>{{ optional($insc->alumno)->matricula }}</td>
                <td>{{ optional(optional($insc->alumno)->usuario)->nombre }} {{ optional(optional($insc->alumno)->usuario)->apellidos }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Eventos / prácticas</h2>
    <table>
        <thead><tr><th>Práctica</th><th>Inicio</th><th>Estatus</th><th></th></tr></thead>
        <tbody>
        @foreach ($eventos as $evento)
            <tr>
                <td>{{ optional($evento->practica)->titulo }}</td>
                <td>{{ $evento->fecha_hora_inicio }}</td>
                <td>{{ $evento->estatus }}</td>
                <td>
                    <form class="inline" method="POST" action="{{ route('panel.grupos.eventos.links', [$grupo->id_grupo, $evento->id_evento]) }}">@csrf
                        <button class="btn">Generar links del grupo</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
```

- [ ] **Step 6: Definir la ruta real + placeholders de Task 5**

En `routes/web.php`, dentro del grupo `panel`, reemplazar el placeholder `panel.grupos.show` por el controlador y agregar placeholders para las rutas que usa la vista (se implementan en Task 5/6):
```php
Route::get('/panel/grupos/{grupo}', [PanelController::class, 'show'])->name('panel.grupos.show');

// placeholders (Task 5 y 6)
Route::post('/panel/grupos/{grupo}/eventos/{evento}/links', fn () => 'ok')->name('panel.grupos.eventos.links');
Route::get('/panel/grupos/{grupo}/resultados', fn () => 'ok')->name('panel.grupos.resultados');
```

- [ ] **Step 7: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=GrupoDetalleTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(panel): detalle de grupo con alumnos, eventos y autorizacion por dueño"
```

---

### Task 5: Generar magic links de todo el grupo (HTML + CSV)

**Files:**
- Create: `app/Http/Controllers/Panel/PanelLinkController.php`
- Create: `resources/views/panel/links.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Panel/GenerarLinksGrupoTest.php`

**Interfaces:**
- Consumes: `MagicLinkService::generar()`, `PanelController::autorizarGrupo()`, modelos `Grupo`, `EventoAgenda`, `Inscripcion`, `Alumno`.
- Produces:
  - `POST /panel/grupos/{grupo}/eventos/{evento}/links` (`panel.grupos.eventos.links`) → crea un magic link por alumno inscrito, muestra vista `panel.links` con `$filas` (alumno, matrícula, url).
  - `GET /panel/grupos/{grupo}/eventos/{evento}/links.csv` (`panel.grupos.eventos.links.csv`) → mismas filas en CSV (regenera links).

- [ ] **Step 1: Escribir el test**

`tests/Feature/Panel/GenerarLinksGrupoTest.php`:
```php
<?php
use App\Models\{Rol, Usuario, Maestro, Alumno, Carrera, Materia, CicloEscolar, Grupo, Inscripcion, Practica, EventoAgenda, TokenJuego};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grupoConAlumnos(int $n): array {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $uMaestro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'm'.rand(1,99999).'@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'E'.rand(1,99999)]);
    $mat = Materia::create(['clave' => 'M'.rand(1,99999), 'nombre' => 'Mat', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $carrera = Carrera::create(['clave' => 'C'.rand(1,99999), 'nombre' => 'Car', 'duracion_semestres' => 9]);
    for ($i = 0; $i < $n; $i++) {
        $u = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'al'.rand(1,999999).'@b.com', 'nombre' => 'Al'.$i, 'apellidos' => 'P']);
        $al = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => (string) rand(10000000,99999999), 'semestre_actual' => 3, 'generacion' => '2025']);
        Inscripcion::create(['id_alumno' => $al->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);
    }
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab']);
    $evento = EventoAgenda::create(['id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(), 'estatus' => 'programado']);
    return [$uMaestro, $grupo, $evento];
}

it('genera un magic link por alumno inscrito del grupo', function () {
    [$uMaestro, $grupo, $evento] = grupoConAlumnos(3);
    $this->actingAs($uMaestro);

    $r = $this->post(route('panel.grupos.eventos.links', [$grupo->id_grupo, $evento->id_evento]));
    $r->assertOk();
    expect(TokenJuego::where('id_evento', $evento->id_evento)->count())->toBe(3);
    expect($r->viewData('filas'))->toHaveCount(3);
});

it('un maestro no puede generar links de un grupo ajeno', function () {
    [, $grupo, $evento] = grupoConAlumnos(2);
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $otro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'otro@b.com', 'nombre' => 'O', 'apellidos' => 'T']);
    Maestro::create(['id_usuario' => $otro->id_usuario, 'numero_empleado' => 'EZZ']);
    $this->actingAs($otro);
    $this->post(route('panel.grupos.eventos.links', [$grupo->id_grupo, $evento->id_evento]))->assertForbidden();
});

it('exporta CSV con una fila por alumno', function () {
    [$uMaestro, $grupo, $evento] = grupoConAlumnos(2);
    $this->actingAs($uMaestro);
    $r = $this->get(route('panel.grupos.eventos.links.csv', [$grupo->id_grupo, $evento->id_evento]));
    $r->assertOk();
    expect($r->headers->get('content-type'))->toContain('text/csv');
    $lineas = array_filter(explode("\n", trim($r->streamedContent() ?? $r->getContent())));
    expect(count($lineas))->toBe(3); // encabezado + 2 alumnos
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=GenerarLinksGrupoTest`
Expected: FAIL (placeholder devuelve 'ok').

- [ ] **Step 3: Crear el controlador**

`app/Http/Controllers/Panel/PanelLinkController.php`:
```php
<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class PanelLinkController extends Controller {
    public function __construct(private MagicLinkService $magicLink, private PanelController $panel) {}

    private function filasDeLinks(Grupo $grupo, EventoAgenda $evento): array {
        $inscripciones = $grupo->inscripciones()->with('alumno.usuario')->get();
        $filas = [];
        foreach ($inscripciones as $insc) {
            $alumno = $insc->alumno;
            if (! $alumno || ! $alumno->usuario) continue;
            $res = $this->magicLink->generar($alumno->id_usuario, $evento->id_evento);
            $filas[] = [
                'nombre' => trim($alumno->usuario->nombre.' '.$alumno->usuario->apellidos),
                'matricula' => $alumno->matricula,
                'url' => $res['url'],
            ];
        }
        return $filas;
    }

    public function generar(Request $request, Grupo $grupo, EventoAgenda $evento) {
        $this->panel->autorizarGrupo($request->user(), $grupo);
        abort_unless($evento->id_grupo === $grupo->id_grupo, 404);
        $filas = $this->filasDeLinks($grupo, $evento);
        return view('panel.links', ['grupo' => $grupo, 'evento' => $evento, 'filas' => $filas]);
    }

    public function csv(Request $request, Grupo $grupo, EventoAgenda $evento) {
        $this->panel->autorizarGrupo($request->user(), $grupo);
        abort_unless($evento->id_grupo === $grupo->id_grupo, 404);
        $filas = $this->filasDeLinks($grupo, $evento);
        $callback = function () use ($filas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['nombre', 'matricula', 'url']);
            foreach ($filas as $f) fputcsv($out, [$f['nombre'], $f['matricula'], $f['url']]);
            fclose($out);
        };
        return response()->streamDownload($callback, "links-grupo-{$grupo->id_grupo}-evento-{$evento->id_evento}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
```
Nota: `EventoAgenda` route binding necesita `getRouteKeyName(): string { return 'id_evento'; }` en su modelo.

- [ ] **Step 4: Crear la vista de links**

`resources/views/panel/links.blade.php`:
```blade
@extends('panel.layout')
@section('titulo', 'Links del grupo')
@section('contenido')
<a class="row-link" href="{{ route('panel.grupos.show', $grupo->id_grupo) }}">← Volver al grupo</a>
<h1>Magic links — {{ $grupo->clave }}</h1>
<p><a class="btn" href="{{ route('panel.grupos.eventos.links.csv', [$grupo->id_grupo, $evento->id_evento]) }}">Descargar CSV</a></p>
<div class="card">
<table>
    <thead><tr><th>Alumno</th><th>Matrícula</th><th>Link de acceso</th></tr></thead>
    <tbody>
    @foreach ($filas as $f)
        <tr>
            <td>{{ $f['nombre'] }}</td>
            <td>{{ $f['matricula'] }}</td>
            <td><input type="text" readonly value="{{ $f['url'] }}" style="width:100%" onclick="this.select()"></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
```

- [ ] **Step 5: Definir rutas reales (reemplazar placeholder)**

En `routes/web.php`, dentro del grupo `panel`, reemplazar el placeholder de links por:
```php
use App\Http\Controllers\Panel\PanelLinkController;

Route::post('/panel/grupos/{grupo}/eventos/{evento}/links', [PanelLinkController::class, 'generar'])->name('panel.grupos.eventos.links');
Route::get('/panel/grupos/{grupo}/eventos/{evento}/links.csv', [PanelLinkController::class, 'csv'])->name('panel.grupos.eventos.links.csv');
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=GenerarLinksGrupoTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(panel): generar magic links de todo el grupo (HTML + CSV)"
```

---

### Task 6: Resultados del grupo + detalle de sesión

**Files:**
- Create: `app/Http/Controllers/Panel/PanelResultadoController.php`
- Create: `resources/views/panel/resultados.blade.php`
- Create: `resources/views/panel/sesion.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Panel/ResultadosTest.php`

**Interfaces:**
- Consumes: `PanelController::autorizarGrupo()`, modelos `Grupo`, `SesionPractica`, `EventoAgenda`, `Alumno`.
- Produces:
  - `GET /panel/grupos/{grupo}/resultados` (`panel.grupos.resultados`) → vista `panel.resultados` con `$sesiones` (sesiones de los eventos del grupo, con `alumno.usuario`, `practica`).
  - `GET /panel/sesiones/{sesion}` (`panel.sesiones.show`) → vista `panel.sesion` con la sesión y su `datos_resultado`; autoriza por el grupo del evento de la sesión.

- [ ] **Step 1: Escribir el test**

`tests/Feature/Panel/ResultadosTest.php`:
```php
<?php
use App\Models\{Rol, Usuario, Maestro, Alumno, Carrera, Materia, CicloEscolar, Grupo, Inscripcion, Practica, EventoAgenda, SesionPractica};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('muestra las sesiones del grupo y el detalle con telemetria', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $uMaestro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'E1']);
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    $uAlumno = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'ana@b.com', 'nombre' => 'Ana', 'apellidos' => 'R']);
    $alumno = Alumno::create(['id_usuario' => $uAlumno->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);
    $evento = EventoAgenda::create(['id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(), 'estatus' => 'programado']);
    $sesion = SesionPractica::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'id_practica' => $practica->id_practica, 'fecha_inicio' => now(), 'fecha_fin' => now(), 'estatus' => 'completada', 'calificacion' => 88.5, 'datos_resultado' => ['aciertos' => 9]]);

    $this->actingAs($uMaestro);
    $this->get(route('panel.grupos.resultados', $grupo->id_grupo))
        ->assertOk()->assertSee('Ana')->assertSee('88.5');

    $this->get(route('panel.sesiones.show', $sesion->id_sesion))
        ->assertOk()->assertSee('aciertos');
});

it('un maestro no ve resultados de un grupo ajeno', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $mat = Materia::create(['clave' => 'X', 'nombre' => 'X', 'creditos' => 1]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $uDueno = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'd@b.com', 'nombre' => 'D', 'apellidos' => 'U']);
    $dueno = Maestro::create(['id_usuario' => $uDueno->id_usuario, 'numero_empleado' => 'ED']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $dueno->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '9Z', 'cupo_maximo' => 30]);

    $uOtro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'o@b.com', 'nombre' => 'O', 'apellidos' => 'T']);
    Maestro::create(['id_usuario' => $uOtro->id_usuario, 'numero_empleado' => 'EO']);
    $this->actingAs($uOtro);
    $this->get(route('panel.grupos.resultados', $grupo->id_grupo))->assertForbidden();
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=ResultadosTest`
Expected: FAIL (placeholder/rutas).

- [ ] **Step 3: Crear el controlador**

`app/Http/Controllers/Panel/PanelResultadoController.php`:
```php
<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\SesionPractica;
use Illuminate\Http\Request;

class PanelResultadoController extends Controller {
    public function __construct(private PanelController $panel) {}

    public function index(Request $request, Grupo $grupo) {
        $this->panel->autorizarGrupo($request->user(), $grupo);
        $eventoIds = $grupo->eventos()->pluck('id_evento');
        $sesiones = SesionPractica::whereIn('id_evento', $eventoIds)
            ->with(['alumno.usuario', 'practica'])
            ->orderByDesc('fecha_inicio')
            ->get();
        return view('panel.resultados', ['grupo' => $grupo, 'sesiones' => $sesiones]);
    }

    public function sesion(Request $request, SesionPractica $sesion) {
        $sesion->load(['alumno.usuario', 'practica', 'evento']);
        $grupo = Grupo::findOrFail($sesion->evento->id_grupo);
        $this->panel->autorizarGrupo($request->user(), $grupo);
        return view('panel.sesion', ['sesion' => $sesion]);
    }
}
```
Nota: agregar `getRouteKeyName(): string { return 'id_sesion'; }` en `SesionPractica`.

- [ ] **Step 4: Crear las vistas**

`resources/views/panel/resultados.blade.php`:
```blade
@extends('panel.layout')
@section('titulo', 'Resultados '.$grupo->clave)
@section('contenido')
<a class="row-link" href="{{ route('panel.grupos.show', $grupo->id_grupo) }}">← Volver al grupo</a>
<h1>Resultados — {{ $grupo->clave }}</h1>
<div class="card">
<table>
    <thead><tr><th>Alumno</th><th>Práctica</th><th>Estatus</th><th>Calificación</th><th>Inicio</th><th></th></tr></thead>
    <tbody>
    @forelse ($sesiones as $s)
        <tr>
            <td>{{ optional(optional($s->alumno)->usuario)->nombre }} {{ optional(optional($s->alumno)->usuario)->apellidos }}</td>
            <td>{{ optional($s->practica)->titulo }}</td>
            <td>{{ $s->estatus }}</td>
            <td>{{ $s->calificacion }}</td>
            <td>{{ $s->fecha_inicio }}</td>
            <td><a class="row-link" href="{{ route('panel.sesiones.show', $s->id_sesion) }}">Ver detalle</a></td>
        </tr>
    @empty
        <tr><td colspan="6">Aún no hay sesiones registradas.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
```

`resources/views/panel/sesion.blade.php`:
```blade
@extends('panel.layout')
@section('titulo', 'Sesión #'.$sesion->id_sesion)
@section('contenido')
<h1>Sesión #{{ $sesion->id_sesion }}</h1>
<div class="card">
    <p><strong>Alumno:</strong> {{ optional(optional($sesion->alumno)->usuario)->nombre }} {{ optional(optional($sesion->alumno)->usuario)->apellidos }}</p>
    <p><strong>Práctica:</strong> {{ optional($sesion->practica)->titulo }}</p>
    <p><strong>Estatus:</strong> {{ $sesion->estatus }} · <strong>Calificación:</strong> {{ $sesion->calificacion }}</p>
    <p><strong>Inicio:</strong> {{ $sesion->fecha_inicio }} · <strong>Fin:</strong> {{ $sesion->fecha_fin }}</p>
    <h2>Telemetría</h2>
    <pre style="background:#0b1020;color:#d1d5db;padding:1rem;border-radius:.5rem;overflow:auto">{{ json_encode($sesion->datos_resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</div>
@endsection
```

- [ ] **Step 5: Definir rutas reales (reemplazar placeholder)**

En `routes/web.php`, dentro del grupo `panel`, reemplazar el placeholder de resultados y agregar la de sesión:
```php
use App\Http\Controllers\Panel\PanelResultadoController;

Route::get('/panel/grupos/{grupo}/resultados', [PanelResultadoController::class, 'index'])->name('panel.grupos.resultados');
Route::get('/panel/sesiones/{sesion}', [PanelResultadoController::class, 'sesion'])->name('panel.sesiones.show');
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=ResultadosTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(panel): resultados del grupo y detalle de sesion con telemetria"
```

---

### Task 7: Verificación integral + README del panel

**Files:**
- Modify: `README.md`
- Test: ejecuta toda la suite.

**Interfaces:**
- Consumes: todo lo anterior.

- [ ] **Step 1: Documentar el panel en el README**

Agregar una sección a `README.md`:
```markdown
## Panel de Maestros

Panel web para maestros/coordinadores: ver grupos y alumnos, generar magic links
de todo un grupo y consultar calificaciones.

### Acceso (sin contraseña, magic link firmado)
```bash
# Genera un enlace de acceso para un usuario con rol Maestro/Coordinador/Admin
php artisan metaverso:panel-acceso <id_usuario>
```
Abre la URL impresa (válida 30 min, configurable con `PANEL_LOGIN_TTL_MINUTES`).
Caduca y entra a `/panel`. Los alumnos no pueden entrar.

### Rutas
- `/panel` — mis grupos (Maestro) o todos (Coordinador/Admin)
- `/panel/grupos/{grupo}` — alumnos + eventos del grupo
- `/panel/grupos/{grupo}/eventos/{evento}/links` — genera links del grupo (+ CSV)
- `/panel/grupos/{grupo}/resultados` — calificaciones y sesiones

### Compatibilidad con LTI
El inicio de sesión del panel es un único método (`PanelLoginController::establecerSesion`).
Cuando se agregue LTI, el launch de Moodle reutilizará ese mismo método.
```

- [ ] **Step 2: Correr toda la suite**

Run: `php artisan config:clear && php artisan test`
Expected: PASS (todas las pruebas previas del proyecto + las nuevas del panel).

- [ ] **Step 3: Verificación manual**

```bash
php artisan migrate:fresh --seed
php artisan metaverso:panel-acceso 1   # usuario 1 = maestro demo (Laura)
```
Expected: imprime una URL `/panel/acceso/1?...signature=...`. Abrirla inicia sesión y muestra el dashboard con el grupo demo.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "docs(panel): documentar acceso y rutas del panel de maestros"
```

---

## Self-Review

**Cobertura del spec:**
- §3 Login firmado + LTI-ready → Task 2 (`establecerSesion` único). §3 guard web → Task 1.
- §3 TTL config + comando artisan → Task 1 (config) + Task 2 (comando).
- §4 Autorización por rol/dueño → Task 1 (helpers), Task 3 (gruposVisibles), Task 4 (autorizarGrupo).
- §5.1 Dashboard → Task 3. §5.2 Detalle grupo → Task 4. §5.3 Generar links + CSV → Task 5. §5.4 Resultados + detalle sesión → Task 6.
- §6 Seguridad (Alumno bloqueado, recurso ajeno 403, firma) → Tasks 1,2,4,5,6.
- §8 Pruebas (7 escenarios) → cubiertos: login válido (T2), firma manipulada (T2), Alumno bloqueado (T2), Maestro solo lo suyo (T3/T4), Coordinador ve todo (T3), N links por grupo (T5), resultados (T6).
- §9 Entregables (config, middleware, controllers, vistas, comando, env, tests) → Tasks 1–7.

**Placeholders de implementación:** las rutas placeholder temporales (`fn () => 'ok'`) son intencionales y cada una se reemplaza por su controlador real en la task indicada (T2→T3 dashboard, T3→T4 grupos.show, T4→T5 links, T4→T6 resultados). No quedan placeholders al final.

**Consistencia de tipos/nombres:** `gruposVisibles(Usuario)`, `autorizarGrupo(Usuario,Grupo)`, `establecerSesion(Usuario)`, `MagicLinkService::generar(idUsuario,idEvento)` usados consistentemente. Route-model binding requiere `getRouteKeyName()` en `Grupo` (T4), `EventoAgenda` (T5), `SesionPractica` (T6) por sus PK custom. Nombres de rutas consistentes: `panel.dashboard`, `panel.grupos.show`, `panel.grupos.eventos.links(.csv)`, `panel.grupos.resultados`, `panel.sesiones.show`, `panel.acceso`, `panel.salir`, `panel.acceso.invalido`.
