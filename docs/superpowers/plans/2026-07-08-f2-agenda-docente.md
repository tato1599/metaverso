# F2: Agenda del docente — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El docente crea, reprograma y cancela eventos de práctica con cupo por slot; ve su agenda semanal y el detalle de cada evento con sus reservas. Nacen las migraciones de `cupo_maximo` y `reservas` (tabla + modelo, sin flujos de alumno — esos son F3).

**Architecture:** Controlador nuevo `Panel/AgendaController` (Inertia) dentro del grupo `['auth','panel']`. Autorización por `evento->grupo` con el patrón `autorizarGrupo` existente. Reducción de cupo bajo `lockForUpdate()`. Páginas Inertia nuevas conviven con el panel Blade legado (migra en F5).

**Tech Stack:** Laravel 12, Inertia v2 + React 19, Tailwind v4, Pest 4, PostgreSQL (índice parcial único).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-08-portal-agenda-reservas-design.md` §3 (migraciones 1-2), §4 (agenda docente), §6-F2, §7.
- Suite existente verde; contrato API del juego intacto.
- Después de PHP: `vendor/bin/pint --dirty --format agent`. Artisan con `--no-interaction`.
- Datos duros (horas, claves, cupos) en `font-mono tabular-nums`; cupo como puntos llenables (signature del design system).

---

### Task 1: Migraciones `cupo_maximo` + `reservas`, modelo `Reserva`, config (TDD)

**Files:**
- Create: `database/migrations/2026_07_08_100000_add_cupo_maximo_to_eventos_agenda.php`
- Create: `database/migrations/2026_07_08_100001_create_reservas_table.php`
- Create: `app/Models/Reserva.php`
- Modify: `app/Models/EventoAgenda.php` (relaciones y helper)
- Modify: `config/metaverso.php` (`cupo_default_evento`)
- Create: `tests/Feature/Schema/ReservasSchemaTest.php`

**Interfaces:**
- Produces: `EventoAgenda::reservas(): HasMany`, `EventoAgenda::reservasActivas(): HasMany` (filtrado `estatus='activa'`), columna `eventos_agenda.cupo_maximo`, modelo `Reserva` (`evento()`, `alumno()`), `config('metaverso.cupo_default_evento')` (int, default 5).

- [ ] **Step 1: Test que falla — `tests/Feature/Schema/ReservasSchemaTest.php`**

```php
<?php

use App\Models\{Alumno, Carrera, CicloEscolar, EventoAgenda, Espacio, Grupo, Maestro, Materia, Practica, Reserva, Rol, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Schema};

uses(RefreshDatabase::class);

function armarEvento(array $attrs = []): EventoAgenda {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => fake()->unique()->numerify('EMP###')]);
    $materia = Materia::create(['clave' => fake()->unique()->bothify('MAT-###'), 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);
    $grupo = Grupo::create(['id_materia' => $materia->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $practica = Practica::create(['id_materia' => $materia->id_materia, 'titulo' => 'P1', 'orden' => 1, 'escena_referencia' => 'Lab_1']);
    return EventoAgenda::create(array_merge([
        'id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->addDay(), 'fecha_hora_fin' => now()->addDay()->addHour(),
        'estatus' => 'programado', 'cupo_maximo' => 5,
    ], $attrs));
}

function armarAlumno(): Alumno {
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'A', 'apellidos' => 'L']);
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);
    return Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => fake()->unique()->numerify('2025####'), 'semestre_actual' => 3, 'generacion' => '2025']);
}

it('eventos_agenda tiene cupo_maximo', function () {
    expect(Schema::hasColumn('eventos_agenda', 'cupo_maximo'))->toBeTrue();
    expect(armarEvento()->cupo_maximo)->toBe(5);
});

it('crea reservas y las relaciones navegan', function () {
    $evento = armarEvento();
    $alumno = armarAlumno();
    $r = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    expect($r->estatus)->toBe('activa')
        ->and($r->evento->id_evento)->toBe($evento->id_evento)
        ->and($r->alumno->id_alumno)->toBe($alumno->id_alumno)
        ->and($evento->reservasActivas()->count())->toBe(1);
});

it('el índice parcial único impide dos reservas activas del mismo alumno en el mismo evento', function () {
    $evento = armarEvento();
    $alumno = armarAlumno();
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    expect(fn () => Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('permite re-reservar tras cancelar', function () {
    $evento = armarEvento();
    $alumno = armarAlumno();
    $r1 = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    $r1->update(['estatus' => 'cancelada']);
    $r2 = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    expect($r2->estatus)->toBe('activa')->and($evento->reservasActivas()->count())->toBe(1);
});
```

- [ ] **Step 2:** `php artisan test --compact --filter=ReservasSchemaTest` — Expected: FAIL (columna/tabla inexistentes).

- [ ] **Step 3: Migraciones + modelo + config**

`database/migrations/2026_07_08_100000_add_cupo_maximo_to_eventos_agenda.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_agenda', function (Blueprint $t) {
            $t->unsignedSmallInteger('cupo_maximo')->default(5);
        });
        // Eventos preexistentes: hereda capacidad del espacio si es menor (CONTEXTO §5).
        DB::statement(<<<'SQL'
            UPDATE eventos_agenda SET cupo_maximo = LEAST(5, e.capacidad)
            FROM espacios e
            WHERE eventos_agenda.id_espacio = e.id_espacio AND e.capacidad IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('eventos_agenda', fn (Blueprint $t) => $t->dropColumn('cupo_maximo'));
    }
};
```

`database/migrations/2026_07_08_100001_create_reservas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $t) {
            $t->id('id_reserva');
            $t->foreignId('id_evento')->constrained('eventos_agenda', 'id_evento');
            $t->foreignId('id_alumno')->constrained('alumnos', 'id_alumno');
            $t->string('estatus')->default('activa'); // activa | cancelada (extensible: lista_espera)
            $t->timestamps(); // created_at = fecha de reserva
            $t->index('id_alumno');
        });
        DB::statement("CREATE UNIQUE INDEX reservas_evento_alumno_activa ON reservas (id_evento, id_alumno) WHERE estatus = 'activa'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
```

`app/Models/Reserva.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $table = 'reservas';

    protected $primaryKey = 'id_reserva';

    protected $guarded = [];

    public function evento()
    {
        return $this->belongsTo(EventoAgenda::class, 'id_evento');
    }

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno');
    }
}
```

`app/Models/EventoAgenda.php` — agregar:

```php
public function reservas()
{
    return $this->hasMany(Reserva::class, 'id_evento');
}

public function reservasActivas()
{
    return $this->reservas()->where('estatus', 'activa');
}
```

`config/metaverso.php` — agregar: `'cupo_default_evento' => (int) env('CUPO_DEFAULT_EVENTO', 5),`

- [ ] **Step 4:** `php artisan test --compact --filter=ReservasSchemaTest` PASS y suite completa verde. `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 5: Commit** — `git commit -am "feat(f2): migraciones cupo_maximo y reservas + modelo Reserva"`

---

### Task 2: AgendaController — autorización, CRUD y lock de cupo (TDD)

**Files:**
- Create: `app/Http/Controllers/Panel/AgendaController.php`
- Modify: `routes/web.php` (rutas en grupo `['auth','panel']`)
- Create: `tests/Feature/Panel/AgendaEventosTest.php`

**Interfaces:**
- Consumes: `EventoAgenda::reservasActivas()`, `Usuario::esCoordinadorOAdmin()`, patrón de autorización de `PanelController` (maestro dueño del grupo o coordinador/admin).
- Produces (rutas nombradas): `panel.agenda` (GET /panel/agenda), `panel.eventos.store` (POST /panel/eventos), `panel.eventos.show` (GET /panel/eventos/{evento}), `panel.eventos.update` (PUT), `panel.eventos.destroy` (DELETE = cancelar). Páginas Inertia: `Panel/Agenda`, `Panel/EventoDetalle`.

**Reglas (spec §4):**
- Autorización en show/update/destroy vía `evento->grupo`: 403 si el maestro no es dueño; Coordinador/Admin exentos. En store: el grupo del payload debe ser suyo (o staff superior).
- store: `id_practica` debe pertenecer a `grupo->id_materia` (422); `fecha_hora_fin > fecha_hora_inicio` (422); `cupo_maximo >= 1` (422); `id_espacio` opcional; estatus inicial `programado`.
- update: mismas validaciones; el cambio de cupo corre en `DB::transaction` con `lockForUpdate()` sobre el evento y valida `cupo_maximo >= reservasActivas()->count()` (422 si no).
- destroy: soft — `estatus = 'cancelado'`; no borra fila. Eventos ya cancelados: 409.
- index: eventos de la semana pedida (`?semana=YYYY-MM-DD`, default hoy; semana lunes-domingo) de los grupos del maestro (staff superior: todos), con `practica`, `grupo.materia`, `espacio`, conteo de reservas activas; más catálogos para el form (grupos propios con su materia y prácticas de esa materia, espacios) y `config('metaverso.cupo_default_evento')`.

- [ ] **Step 1: Tests que fallan — `tests/Feature/Panel/AgendaEventosTest.php`** (usa helpers de armado locales al archivo, mismo estilo que `ReservasSchemaTest` pero con nombres propios para evitar colisiones Pest):

Casos (cada uno un `it(...)`):
1. invitado GET /panel/agenda → redirect /login; alumno → 403.
2. maestro ve solo eventos de sus grupos en su semana; coordinador ve todos.
3. maestro crea evento válido → 302 a `panel.agenda`, fila con estatus `programado` y cupo del payload.
4. crear con práctica de OTRA materia → 422.
5. crear con `fin <= inicio` → 422; cupo 0 → 422.
6. crear en grupo ajeno → 403 (coordinador sí puede).
7. IDOR: GET detalle / PUT / DELETE de evento de otro maestro → 403 en los tres; coordinador → 200/302.
8. PUT reduce cupo por debajo de reservas activas → 422 (sembrar 2 reservas activas, intentar cupo 1).
9. DELETE cancela (estatus `cancelado`), segunda vez → 409.
10. detalle incluye reservas activas con alumno (nombre, matrícula).

- [ ] **Step 2:** correr filtro — Expected: FAIL (rutas inexistentes).

- [ ] **Step 3: Implementación** — `AgendaController` con métodos `index/store/show/update/destroy`. Esqueleto de autorización y lock:

```php
private function autorizarEvento(Request $request, EventoAgenda $evento): void
{
    $u = $request->user();
    if ($u->esCoordinadorOAdmin()) {
        return;
    }
    abort_unless(optional($u->maestro)->id_maestro === $evento->grupo->id_maestro, 403);
}

// update (fragmento del cupo):
DB::transaction(function () use ($evento, $datos) {
    $bloqueado = EventoAgenda::whereKey($evento->id_evento)->lockForUpdate()->firstOrFail();
    $activas = $bloqueado->reservasActivas()->count();
    if ($datos['cupo_maximo'] < $activas) {
        throw ValidationException::withMessages([
            'cupo_maximo' => "Hay {$activas} reservas activas; el cupo no puede ser menor.",
        ]);
    }
    $bloqueado->update($datos);
});
```

Rutas (dentro del grupo `['auth','panel']` existente):

```php
Route::get('/panel/agenda', [AgendaController::class, 'index'])->name('panel.agenda');
Route::post('/panel/eventos', [AgendaController::class, 'store'])->name('panel.eventos.store');
Route::get('/panel/eventos/{evento}', [AgendaController::class, 'show'])->name('panel.eventos.show');
Route::put('/panel/eventos/{evento}', [AgendaController::class, 'update'])->name('panel.eventos.update');
Route::delete('/panel/eventos/{evento}', [AgendaController::class, 'destroy'])->name('panel.eventos.destroy');
```

(`{evento}` se resuelve por route model binding con `getRouteKeyName` — agregar `public function getRouteKeyName() { return 'id_evento'; }` a `EventoAgenda` si no existe.)

- [ ] **Step 4:** tests del filtro PASS + suite completa verde + pint.
- [ ] **Step 5: Commit** — `git commit -am "feat(f2): CRUD de eventos del docente con autorización y lock de cupo"`

---

### Task 3: UI — agenda semanal y detalle de evento

**Files:**
- Create: `resources/js/Pages/Panel/Agenda.jsx`, `resources/js/Pages/Panel/EventoDetalle.jsx`
- Create: `resources/js/Components/CupoPuntos.jsx` (signature: puntos llenables ●●●○○; >12 lugares degrada a fracción mono `9/30`)
- Create: `resources/js/Components/EventoFormModal.jsx` (crear/editar: grupo → prácticas de su materia, espacio opcional, `datetime-local` nativos, cupo prefill `min(config, capacidad espacio)`)
- Modify: `resources/js/Layouts/AppLayout.jsx` (nav staff: agregar `Agenda` → `/panel/agenda`)

**Interfaces:**
- Consumes: props de `AgendaController@index` (`semana`, `eventos[]`, `grupos[]` con materia+prácticas, `espacios[]`, `cupoDefault`) y componentes del design system.
- `CupoPuntos({ ocupados, cupo })`; `EventoFormModal({ open, onClose, grupos, espacios, cupoDefault, evento? })` — `evento` presente = modo edición.

- [ ] **Step 1:** `Agenda.jsx` — encabezado con semana (nav ‹ hoy ›, `router.get` con `?semana=`), grid semanal (7 columnas en desktop, lista apilada en móvil), slot chips: hora en mono, título de práctica, grupo, `CupoPuntos`, estatus con `Badge`. Botón "Agendar práctica" abre el modal. Eventos cancelados en opacidad reducida con badge.
- [ ] **Step 2:** `EventoDetalle.jsx` — Card con datos del evento, acciones Reprogramar (modal en modo edición) y Cancelar (confirm), `Table` de reservas activas (alumno, matrícula en mono, fecha de reserva).
- [ ] **Step 3:** `npm run build` OK; recorrido en navegador (Chrome DevTools): crear, editar, cancelar, ver detalle; consola limpia.
- [ ] **Step 4: Commit** — `git commit -am "feat(f2): UI agenda semanal del docente y detalle de evento"`

---

### Task 4: Cierre de fase

- [ ] **Step 1:** Suite completa + pint.
- [ ] **Step 2:** Actualizar `DIAGRAMA-ER.md`: entidad RESERVA, `EVENTO_AGENDA.cupo_maximo` (nota: sesiones_practica.id_reserva se agrega en F3).
- [ ] **Step 3:** Revisión por agentes expertos del diff F2; aplicar must-fix.
- [ ] **Step 4:** Verificación en navegador final + screenshot. Commit de cierre.
