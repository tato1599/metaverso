# F1: Stack Inertia/React + Auth + Design System — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Instalar Inertia v2 + React 19, crear el sistema de diseño base, y el login/logout unificado por roles — dejando la plataforma lista para las pantallas de F2–F4.

**Architecture:** Monolito Laravel 12; páginas de humano en Inertia+React, páginas-máquina en Blade. Auth de sesión sobre `usuarios` (provider ya configurado). Un layout invitado (login) y un layout app (nav por rol).

**Tech Stack:** Laravel 12, Inertia v2 (`inertiajs/inertia-laravel` + `@inertiajs/react`), React 19, Tailwind v4 (`@theme` tokens), Vite 7, Pest 4.

## Enmiendas (revisión experta 2026-07-08, decisor delegado)

1. Cliente Inertia pineado a `@inertiajs/react@^2.0` (v3 tenía skew con servidor v2).
2. `routes/web.php` debe importar `use Inertia\Inertia;`.
3. El stub `mi.calendario` es OBLIGATORIO en Task 3 (sin él, `route()` lanza RouteNotFoundException); Task 4 lo MUEVE (no duplica) al grupo `[auth, alumno]`.
4. No existe ningún test del redirect viejo de `salir`; el único test a actualizar es "el middleware panel bloquea acceso sin sesion" (`tests/Feature/Panel/AccesoPanelTest.php`): 403 → `assertRedirect('/login')`.
5. `/demo`: `'apiKey' => app()->environment('local') ? config('metaverso.links_api_key') : null`. Gate `['local','testing']` aceptado como desviación documentada del spec (producción 404; testing sin clave).
6. Task 4 agrega el test guardrail Sanctum por invariantes: el grupo `api` no contiene `EnsureFrontendRequestsAreStateful` ni `StartSession`, y `/api/game/me` sin credenciales → 401. (La formulación cookie-only da falso verde/rojo con SESSION_DRIVER=array.)
7. `LoginController::store` SIN `intended()` — redirect puro por rol. Test extra: guest visita /panel, luego login como alumno → `/mi/calendario`.
8. A11y: FormField liga el error con `aria-describedby`/`aria-invalid`; nav de AppLayout con focus visible. (Aplicado.)
9. `tests/TestCase.php` con `$this->withoutVite()` en `setUp` (la suite debe pasar en un clon sin `public/build`).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-08-portal-agenda-reservas-design.md` (§2 Autenticación, §5 UI, §6 F1).
- La suite existente (LTI, game API, panel) debe quedar verde; solo se actualizan los tests de guard/salir citados abajo.
- El contrato de la API del juego no cambia en F1.
- Sin SSR. Sin `statefulApi()` de Sanctum — Inertia usa la sesión web normal.
- Sin "recordarme", sin registro, sin password reset.
- Después de tocar PHP: `vendor/bin/pint --dirty --format agent`.
- Comandos artisan siempre con `--no-interaction`.

---

### Task 1: Instalar Inertia v2 (servidor y cliente)

**Files:**
- Modify: `composer.json` (via composer require), `package.json` (via npm i), `vite.config.js`
- Create: `resources/views/app.blade.php`, `resources/js/app.jsx`, `app/Http/Middleware/HandleInertiaRequests.php` (artisan), `resources/js/Pages/Auth/Login.jsx` (stub temporal para smoke test)
- Modify: `bootstrap/app.php` (append middleware web)
- Delete: `resources/js/app.js`, `resources/js/bootstrap.js` (axios ya viene con @inertiajs/react)

**Interfaces:**
- Produces: helper `Inertia::render('Auth/Login')` funcional; `@vite('resources/js/app.jsx')`; middleware `HandleInertiaRequests` compartiendo `auth.user` (`id_usuario`, `nombre`, `apellidos`, `rol` como string) y `flash.error/success`.

- [ ] **Step 1: composer + npm**

```bash
composer require inertiajs/inertia-laravel:^2.0 --no-interaction
php artisan inertia:middleware --no-interaction
npm i react@^19 react-dom@^19 @inertiajs/react @vitejs/plugin-react
```

- [ ] **Step 2: vite.config.js — plugin react + entrada jsx**

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

- [ ] **Step 3: root template `resources/views/app.blade.php`**

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Metaverso') }}</title>
    @viteReactRefresh
    @vite('resources/js/app.jsx')
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
```

- [ ] **Step 4: entrada `resources/js/app.jsx`**

```jsx
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
```

Borrar `resources/js/app.js` y `resources/js/bootstrap.js`.

- [ ] **Step 5: registrar middleware y compartir props**

En `bootstrap/app.php`, dentro de `withMiddleware`:

```php
$middleware->web(append: [
    \App\Http\Middleware\HandleInertiaRequests::class,
]);
```

En `app/Http/Middleware/HandleInertiaRequests.php`, método `share`:

```php
public function share(Request $request): array
{
    $u = $request->user();

    return [
        ...parent::share($request),
        'auth' => [
            'user' => $u ? [
                'id_usuario' => $u->id_usuario,
                'nombre' => $u->nombre,
                'apellidos' => $u->apellidos,
                'rol' => optional($u->rol)->nombre,
            ] : null,
        ],
        'flash' => [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
        ],
    ];
}
```

- [ ] **Step 6: smoke — stub `resources/js/Pages/Auth/Login.jsx` + build**

```jsx
export default function Login() {
    return <div>Login</div>;
}
```

```bash
npm run build
```
Expected: build OK, manifest incluye `resources/js/app.jsx`.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(f1): instalar Inertia v2 + React 19 (server + client)"
```

---

### Task 2: Dirección visual + tokens + componentes base

**Files:**
- Modify: `resources/css/app.css` (tokens `@theme`, OKLCH)
- Create: `resources/js/Layouts/GuestLayout.jsx`, `resources/js/Layouts/AppLayout.jsx`
- Create: `resources/js/Components/{Button,Card,FormField,Badge,Table,Modal,EmptyState}.jsx`

**Interfaces (contratos que consumen F2–F4):**
- `Button({ variant: 'primary'|'secondary'|'danger'|'ghost', type, disabled, children, ...rest })`
- `Card({ title?, children, footer? })`
- `FormField({ label, name, error?, children })` — envuelve cualquier input
- `Badge({ tone: 'ok'|'warn'|'danger'|'muted', children })`
- `Table({ head: string[], children })` — children son `<tr>`
- `Modal({ open, onClose, title, children })`
- `EmptyState({ title, hint? , action? })`
- `AppLayout({ children })` — lee `usePage().props.auth.user`, nav por rol (Alumno: Calendario; Maestro: Panel/Agenda; Coordinador/Admin: + Administración), botón salir (`router.post('/logout')`), muestra `flash`
- `GuestLayout({ children })` — centrado, para login

**Proceso (creativo, no mecánico):** correr `npx ui-skills get dammyjay93/interface-design` y `npx ui-skills get jakubkrehel/oklch-skill`, e invocar el skill `frontend-design`, ANTES de escribir CSS/JSX. La identidad debe ser propia (campus + juego: seria pero no corporativa genérica), definida como tokens: paleta OKLCH (fondo, superficie, tinta, acento, estados ok/warn/danger), tipografía (una display + una texto del sistema o self-hosted), radios, sombras. Nada de gradientes morados genéricos ni glassmorphism por default.

- [ ] **Step 1:** Cargar skills de diseño (comandos arriba) y fijar tokens en `resources/css/app.css` bajo `@theme`.
- [ ] **Step 2:** Implementar los 7 componentes + 2 layouts con las firmas de arriba, usando solo clases Tailwind v4 + tokens.
- [ ] **Step 3:** `npm run build` — Expected: OK.
- [ ] **Step 4: Commit** — `git add -A && git commit -m "feat(f1): design system base (tokens OKLCH, layouts, componentes)"`

---

### Task 3: Login/logout por roles (TDD)

**Files:**
- Modify: `app/Models/Usuario.php` (`getAuthPasswordName`, helper `esAlumno()`)
- Modify: `database/seeders/DemoSeeder.php` (contraseñas)
- Create: `app/Http/Controllers/Auth/LoginController.php`
- Modify: `routes/web.php` (rutas login/logout), `app/Http/Controllers/Panel/PanelLoginController.php` (salir → /login)
- Create: `tests/Feature/Auth/LoginTest.php`
- Modify: el test de Panel que asuma el redirect viejo de `salir`
- Reescribir: `resources/js/Pages/Auth/Login.jsx` (formulario real con `useForm`)

**Interfaces:**
- Produces: ruta `GET /login` con nombre **`login`** (los middleware `auth` de Task 4 dependen de ese nombre); `POST /login`; `POST /logout` con nombre `logout`; `Usuario::esAlumno(): bool`.

- [ ] **Step 1: Test que falla — `tests/Feature/Auth/LoginTest.php`**

```php
<?php

use App\Models\{Rol, Usuario, Alumno, Carrera};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function crearUsuario(string $rol, array $attrs = []): Usuario {
    $r = Rol::firstOrCreate(['nombre' => $rol]);
    return Usuario::create(array_merge([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('secreto123'),
        'nombre' => 'Test',
        'apellidos' => 'User',
        'activo' => true,
    ], $attrs));
}

it('muestra la página de login', function () {
    $this->get('/login')->assertOk();
});

it('loguea a un alumno y redirige a su calendario', function () {
    $u = crearUsuario('Alumno');
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertRedirect('/mi/calendario');
    $this->assertAuthenticatedAs($u);
});

it('loguea a un maestro y redirige al panel', function () {
    $u = crearUsuario('Maestro');
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertRedirect('/panel');
});

it('rechaza contraseña incorrecta', function () {
    $u = crearUsuario('Alumno');
    $this->from('/login')->post('/login', ['correo' => $u->correo, 'password' => 'mala'])
        ->assertRedirect('/login')->assertSessionHasErrors('correo');
    $this->assertGuest();
});

it('rechaza usuario inactivo', function () {
    $u = crearUsuario('Alumno', ['activo' => false]);
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertSessionHasErrors('correo');
    $this->assertGuest();
});

it('aplica rate limit tras 5 intentos fallidos', function () {
    $u = crearUsuario('Alumno');
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['correo' => $u->correo, 'password' => 'mala']);
    }
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertSessionHasErrors('correo');
    $this->assertGuest();
});

it('logout redirige a login para cualquier rol', function () {
    $u = crearUsuario('Alumno');
    $this->actingAs($u)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
```

- [ ] **Step 2:** `php artisan test --compact --filter=LoginTest` — Expected: FAIL (ruta /login no existe).

- [ ] **Step 3: Implementación mínima**

`app/Models/Usuario.php` — agregar:

```php
public function getAuthPasswordName(): string {
    return 'contrasena_hash';
}

public function esAlumno(): bool {
    return optional($this->rol)->nombre === 'Alumno';
}
```

`app/Http/Controllers/Auth/LoginController.php`:

```php
<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller {
    public function create() {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request) {
        $datos = $request->validate([
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $llave = mb_strtolower($datos['correo']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw ValidationException::withMessages([
                'correo' => 'Demasiados intentos. Espera un minuto.',
            ]);
        }

        $credenciales = ['correo' => $datos['correo'], 'password' => $datos['password'], 'activo' => true];
        if (! Auth::attempt($credenciales)) {
            RateLimiter::hit($llave, 60);
            throw ValidationException::withMessages([
                'correo' => 'Credenciales incorrectas.',
            ]);
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();

        $u = $request->user();
        return redirect()->intended($u->esStaffPanel() ? route('panel.dashboard') : route('mi.calendario'));
    }

    public function destroy(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
```

Nota: `Auth::attempt` ignora llaves no-credenciales vía query solo para columnas reales — `activo` es columna bool real, funciona como filtro del provider. El caso "sin rol válido" no existe: `esStaffPanel()` false → calendario; `EnsureAlumno` (Task 4) da el 403 si el rol no corresponde.

`routes/web.php` — agregar (fuera de grupos):

```php
use App\Http\Controllers\Auth\LoginController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
```

`PanelLoginController::salir` — cambiar el redirect final a `redirect()->route('login');` y actualizar el test de panel que asuma `panel.acceso.invalido` como destino del logout.

`DemoSeeder` — en ambos `Usuario::create`, agregar `'contrasena_hash' => Hash::make('password')` (import `Illuminate\Support\Facades\Hash`).

- [ ] **Step 4:** `php artisan test --compact --filter=LoginTest` — Expected: FAIL solo el redirect a `/mi/calendario` (ruta aún no existe; llega en Task 4). Los demás PASS. Si `mi.calendario` truena por nombre inexistente, crear en Task 3 la ruta stub mínima `Route::get('/mi/calendario', fn () => Inertia::render('Mi/Calendario'))->name('mi.calendario');` SIN middleware (Task 4 la protege) y `resources/js/Pages/Mi/Calendario.jsx` placeholder.

- [ ] **Step 5: Login.jsx real** — formulario con `useForm` de `@inertiajs/react`: campos correo/password, errores bajo el campo, botón submit deshabilitado en `processing`, dentro de `GuestLayout`.

- [ ] **Step 6:** `php artisan test --compact --filter="LoginTest|Panel"` — Expected: PASS. `vendor/bin/pint --dirty --format agent`.

- [ ] **Step 7: Commit** — `git commit -am "feat(f1): login/logout unificado por roles sobre usuarios"`

---

### Task 4: Middleware auth/rol + stub calendario + guard tests

**Files:**
- Create: `app/Http/Middleware/EnsureAlumno.php`
- Modify: `bootstrap/app.php` (alias `alumno`), `routes/web.php` (grupo `/mi`, `auth` en panel)
- Create: `resources/js/Pages/Mi/Calendario.jsx` (si no se creó en Task 3)
- Create: `tests/Feature/Auth/AutorizacionRutasTest.php`
- Modify: test de guard del panel existente (invitado: 403 → redirect a login)

**Interfaces:**
- Produces: alias de middleware `alumno`; grupo de rutas `/mi/*` (`auth` + `alumno`); ruta nombrada `mi.calendario`. El panel queda `['auth', 'panel']`.

- [ ] **Step 1: Test que falla — `tests/Feature/Auth/AutorizacionRutasTest.php`**

```php
<?php

use App\Models\{Rol, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function usuarioConRol(string $rol): Usuario {
    $r = Rol::firstOrCreate(['nombre' => $rol]);
    return Usuario::create([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('x'),
        'nombre' => 'T', 'apellidos' => 'U', 'activo' => true,
    ]);
}

it('redirige invitados a login en /mi/calendario', function () {
    $this->get('/mi/calendario')->assertRedirect('/login');
});

it('redirige invitados a login en /panel', function () {
    $this->get('/panel')->assertRedirect('/login');
});

it('permite alumno en su calendario', function () {
    $this->actingAs(usuarioConRol('Alumno'))->get('/mi/calendario')->assertOk();
});

it('bloquea maestro en /mi/*', function () {
    $this->actingAs(usuarioConRol('Maestro'))->get('/mi/calendario')->assertForbidden();
});

it('bloquea alumno en /panel', function () {
    $this->actingAs(usuarioConRol('Alumno'))->get('/panel')->assertForbidden();
});
```

- [ ] **Step 2:** correr — Expected: FAIL (invitado /panel da 403 hoy, /mi sin middleware).

- [ ] **Step 3: Implementación**

`app/Http/Middleware/EnsureAlumno.php`:

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAlumno {
    public function handle(Request $request, Closure $next) {
        abort_unless($request->user() && $request->user()->esAlumno(), 403, 'Acceso solo para alumnos.');
        return $next($request);
    }
}
```

`bootstrap/app.php`: agregar `'alumno' => \App\Http\Middleware\EnsureAlumno::class,` al alias array.

`routes/web.php`: el grupo del panel pasa de `middleware('panel')` a `middleware(['auth', 'panel'])`; grupo nuevo:

```php
Route::middleware(['auth', 'alumno'])->group(function () {
    Route::get('/mi/calendario', fn () => Inertia::render('Mi/Calendario'))->name('mi.calendario');
});
```

`Mi/Calendario.jsx`: `AppLayout` + `EmptyState` ("Tu calendario de prácticas aparecerá aquí").

Actualizar el test de guard del panel: invitado ahora `->assertRedirect('/login')` (antes 403).

- [ ] **Step 4:** `php artisan test --compact` (suite completa) — Expected: PASS total. `vendor/bin/pint --dirty --format agent`.

- [ ] **Step 5: Commit** — `git commit -am "feat(f1): middleware auth+rol, grupo /mi, guard actualizado"`

---

### Task 5: Gate de /demo + nota de rotación de clave

**Files:**
- Modify: `routes/web.php` (ruta /demo), test de demo existente si asume otro comportamiento
- Modify: `deploy/DEPLOY.md` (nota de rotación)

- [ ] **Step 1: Test** — en el test de demo existente (o nuevo `tests/Feature/DemoGateTest.php`):

```php
it('demo disponible en entorno de pruebas', function () {
    $this->get('/demo')->assertOk(); // APP_ENV=testing está permitido
});

it('demo oculta en producción', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->get('/demo')->assertNotFound();
});
```

- [ ] **Step 2: Implementación** — ruta:

```php
Route::get('/demo', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);
    return view('demo', [
        'apiKey'  => config('metaverso.links_api_key'),
        'baseUrl' => url('/'),
    ]);
});
```

`deploy/DEPLOY.md` — agregar al checklist: "Rotar `LINKS_API_KEY` al desplegar esta versión: la versión previa exponía la clave en `/demo` público."

- [ ] **Step 3:** `php artisan test --compact --filter=Demo` — Expected: PASS. Commit: `git commit -am "fix(f1): gate /demo a local/testing (exponía LINKS_API_KEY)"`

---

### Task 6: Verificación en navegador + cierre de fase

- [ ] **Step 1:** `php artisan migrate:fresh --seed --seeder=DemoSeeder --no-interaction && npm run build && php artisan serve` (background).
- [ ] **Step 2:** Con Chrome DevTools MCP: abrir `/login`, login `maestro@tecnm.mx`/`password` → debe aterrizar en `/panel`; logout → `/login`; login `ana@tecnm.mx`/`password` → `/mi/calendario`; revisar consola sin errores; screenshot de login y calendario.
- [ ] **Step 3:** `php artisan test --compact` suite completa verde + `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 4:** Revisión por agentes expertos del diff de F1 (workflow multi-lente) y aplicar hallazgos confirmados.
- [ ] **Step 5: Commit final de fase** — `git commit -am "feat(f1): cierre de fase — verificación navegador y revisión experta"`
