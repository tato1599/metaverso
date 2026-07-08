# F3: Calendario y reservas del alumno — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El alumno ve el calendario de sus grupos, reserva/cancela lugar con la transacción `FOR UPDATE` de `RESERVA_SECUENCIA.md`, y lanza el juego solo con reserva activa dentro de la ventana del evento. La sesión del juego se enlaza a la reserva vía ability `evento:{id}` (aditivo, contrato externo intacto).

**Architecture:** Tres controladores chicos bajo `/mi` (grupo `['auth','alumno']`): `CalendarioController@index`, `ReservaController@store/destroy`, `JugarController@__invoke` (chequeo de "jugar" centralizado — punto de extensión del guard global, spec §8). Cambios aditivos en `GameAuthController@redeem` (ability) y `GameSessionController@start` (enlace reserva).

**Tech Stack:** Laravel 12, Inertia v2 + React 19 (reusa `WeekCalendar`/`CupoPuntos`), Pest 4, PostgreSQL.

## Global Constraints

- Spec §3.3, §4 (reservar/cancelar/jugar/calendario), §6-F3, §7. Secuencia: `RESERVA_SECUENCIA.md`.
- **Desviación documentada de códigos:** en el portal web los conflictos (lleno, duplicada, evento no reservable, fuera de ventana, tarde para cancelar) se materializan como `ValidationException` (422 JSON / redirect-back con errores en Inertia) para UX correcta; los `403` de autorización (no inscrito, reserva ajena, sin reserva al jugar) sí son abort. Los 409 literales del spec aplican al contrato de la API JSON del juego, que NO cambia.
- Throttle `throttle:10,1` en POST reservas y jugar (keyed por usuario autenticado, default de Laravel).
- Contrato API del juego idéntico para sesiones sin reserva (test de §7).
- Pint tras PHP; suite completa verde.

---

### Task 1: Migración `sesiones_practica.id_reserva` + enlace por ability (TDD)

**Files:**
- Create: `database/migrations/2026_07_08_110000_add_id_reserva_to_sesiones_practica.php`
- Modify: `app/Models/SesionPractica.php` (relación `reserva()`), `app/Http/Controllers/Api/GameAuthController.php` (redeem: ability `evento:{id}`), `app/Http/Controllers/Api/GameSessionController.php` (start: enlace)
- Create: `tests/Feature/EnlaceSesionReservaTest.php`

**Interfaces:**
- Produces: columna nullable `sesiones_practica.id_reserva` FK→reservas; en redeem de `TokenJuego` con `id_evento`, el Bearer lleva abilities `['game', 'evento:{id}']`; en `start()`, si la ability coincide con el `id_evento` del body, se busca reserva activa (evento, alumno) y se asigna `id_reserva`.

- [ ] **Step 1: Tests que fallan** — helpers con prefijo `enlace*` (nombres globales reservados: ver enmienda 3 del plan F2). Casos:
  1. redeem de token con evento → el token Sanctum tiene abilities `game` y `evento:{id}` (assert vía `personal_access_tokens` o `/api/game/me` sigue 200).
  2. start con reserva activa del alumno en ese evento → `sesiones_practica.id_reserva` = la reserva; **respuesta JSON idéntica al contrato actual** (mismas claves).
  3. start sin reserva → `id_reserva` null, respuesta idéntica (contrato intacto — caso magic link del maestro).
  4. start con body `id_evento` distinto al de la ability → `id_reserva` null (no se enlaza con datos del cliente).
  5. `lti-redeem` no gana ability de evento y sigue funcionando (suite LTI existente cubre el resto).

- [ ] **Step 2:** correr filtro — FAIL.
- [ ] **Step 3: Implementación.** Migración (`foreignId('id_reserva')->nullable()->constrained('reservas', 'id_reserva')`). En `GameAuthController@redeem`, construir abilities: `$abilities = $token->id_evento ? ['game', 'evento:'.$token->id_evento] : ['game'];`. En `GameSessionController@start`, tras crear la sesión hoy:

```php
$abilities = optional($request->user()->currentAccessToken())->abilities ?? [];
$ligado = collect($abilities)->first(fn ($a) => str_starts_with($a, 'evento:'));
$idEventoLigado = $ligado ? (int) substr($ligado, 7) : null;
if ($idEventoLigado && $idEventoLigado === (int) $datos['id_evento']) {
    $reserva = Reserva::where('id_evento', $idEventoLigado)
        ->where('id_alumno', $alumno->id_alumno)
        ->where('estatus', 'activa')->first();
    // se guarda junto con la creación de la sesión (id_reserva en el create)
}
```

- [ ] **Step 4:** filtro PASS + suite completa (LTI/game intactos) + pint.
- [ ] **Step 5: Commit** — `git commit -am "feat(f3): enlace sesión↔reserva vía ability evento:{id}"`

---

### Task 2: Reservar / cancelar (TDD, corazón del sistema)

**Files:**
- Create: `app/Http/Controllers/Mi/ReservaController.php`
- Modify: `routes/web.php` (grupo `/mi`)
- Create: `tests/Feature/Mi/ReservarTest.php`

**Interfaces:**
- Produces: `POST /mi/reservas` (`{id_evento}`, name `mi.reservas.store`, throttle:10,1), `DELETE /mi/reservas/{reserva}` (name `mi.reservas.destroy`).

**Transacción de reservar (RESERVA_SECUENCIA.md):**

```php
DB::transaction(function () use ($alumno, $idEvento) {
    $evento = EventoAgenda::whereKey($idEvento)->lockForUpdate()->firstOrFail();

    if ($evento->estatus !== 'programado' || $evento->fecha_hora_inicio->isPast()) {
        throw ValidationException::withMessages(['evento' => 'Este slot ya no acepta reservas.']);
    }
    $inscrito = Inscripcion::where('id_alumno', $alumno->id_alumno)
        ->where('id_grupo', $evento->id_grupo)->where('estatus', 'activa')->exists();
    abort_unless($inscrito, 403, 'No estás inscrito en este grupo.');

    if ($evento->reservasActivas()->where('id_alumno', $alumno->id_alumno)->exists()) {
        throw ValidationException::withMessages(['evento' => 'Ya tienes una reserva en este slot.']);
    }
    if ($evento->reservasActivas()->count() >= $evento->cupo_maximo) {
        throw ValidationException::withMessages(['evento' => 'Slot lleno, elige otro horario.']);
    }
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
});
```

**Cancelar:** solo dueño (403), solo `activa` y solo antes de `fecha_hora_inicio` (ValidationException); `estatus = 'cancelada'`.

- [ ] **Step 1: Tests que fallan** — helpers `reservar*`. Casos: feliz (redirect back + fila); no inscrito 403; inscripción `baja` 403; lleno (postJson 422, cupo 1 ocupado por otro); duplicada (postJson 422); evento cancelado/pasado (postJson 422); **último lugar secuencial** (cupo 2: dos alumnos entran, el tercero 422 y `reservasActivas == 2`); cancelar dueño OK; cancelar ajena 403; cancelar tras inicio 422; re-reservar tras cancelar OK; maestro en POST /mi/reservas → 403 (middleware `alumno`).
- [ ] **Step 2:** FAIL. **Step 3:** implementación + rutas con `->middleware('throttle:10,1')` en el POST. **Step 4:** PASS + suite + pint. **Step 5: Commit.**

---

### Task 3: Jugar desde la agenda (TDD)

**Files:**
- Create: `app/Http/Controllers/Mi/JugarController.php`
- Modify: `routes/web.php`, `app/Services/MagicLinkService.php` (NO — la invalidación va en el controlador; el service queda intacto para el panel)
- Create: `tests/Feature/Mi/JugarTest.php`

**Interfaces:**
- Produces: `POST /mi/eventos/{evento}/jugar` (name `mi.eventos.jugar`, throttle:10,1). Respuesta: `Inertia::location(url('/jugar/'.$token))` — en visita Inertia produce 409+`X-Inertia-Location` (full-page hacia el lanzador Blade del deeplink); en request normal, redirect 302.

**Reglas:** reserva activa del alumno en el evento (403 si no); `estatus !== 'cancelado'` y `now()` entre `[inicio, fin]` (ValidationException); antes de generar, invalidar tokens previos no canjeados del par (usuario, evento): `TokenJuego::where('id_usuario', $u->id_usuario)->where('id_evento', $evento->id_evento)->where('usado', false)->update(['usado' => true]);` luego `MagicLinkService::generar`.

- [ ] **Step 1: Tests que fallan** — helpers `jugar*`. Casos: sin reserva 403; reserva cancelada 403; antes de la ventana 422 (postJson); después de fin 422; evento cancelado 422; feliz → respuesta contiene `/jugar/` (assertRedirect o header `X-Inertia-Location` con `->withHeaders(['X-Inertia' => 'true'])` → status 409); feliz invalida el token previo (`usado=true` en el viejo, uno nuevo `usado=false`); el token generado abre sesión de juego real (redeem 200 con ability `evento:{id}` — amarra con Task 1).
- [ ] **Step 2:** FAIL. **Step 3:** implementación. **Step 4:** PASS + suite + pint. **Step 5: Commit.**

---

### Task 4: Calendario del alumno (UI + index)

**Files:**
- Create: `app/Http/Controllers/Mi/CalendarioController.php`
- Modify: `routes/web.php` (la ruta `mi.calendario` pasa del closure al controlador)
- Rewrite: `resources/js/Pages/Mi/Calendario.jsx` (reemplaza el stub de F1)
- Create: `tests/Feature/Mi/CalendarioTest.php`

**Props de index (semana navegable `?semana=`, mismo fallback que agenda):** `semana`, `eventos[]` de grupos con inscripción activa del alumno, cada uno: `id_evento, practica, materia, grupo, espacio, inicio, fin, estatus, cupo_maximo, reservas_activas, mi_reserva: {id_reserva, estatus} | null, puede_reservar, puede_jugar` (booleans calculados en servidor — la UI no reimplementa reglas); `misReservas[]` (activas y pasadas, con evento y estatus).

**Estados del chip (UI):** disponible (botón Reservar), lleno (badge), reservado (badge ok + Cancelar), en curso con reserva (botón **Jugar** — `router.post`, Inertia maneja el 409+location), finalizado, cancelado (opacidad).

- [ ] **Step 1: Tests** — helpers `calendario*`: solo eventos de mis grupos (AssertableInertia `has('eventos', n)`); `mi_reserva` presente cuando existe; `puede_jugar` true solo en ventana con reserva activa; invitado→login, maestro→403 (ya cubierto por middleware, un assert basta).
- [ ] **Step 2-4:** TDD + UI + `npm run build` + suite + pint. **Step 5: Commit.**

---

### Task 5: Cierre de fase

- [ ] Actualizar `DIAGRAMA-ER.md` (`SESION_PRACTICA.id_reserva`, relación RESERVA→SESION_PRACTICA).
- [ ] Suite completa + pint + build.
- [ ] Recorrido en navegador (Chrome DevTools): alumna reserva el último lugar, otro alumno ve "lleno", cancelación libera, botón jugar aparece en ventana y aterriza en `/jugar/{token}`.
- [ ] Revisión por agentes expertos del diff F3; aplicar must-fix. Commit de cierre.
