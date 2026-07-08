# Diseño: Portal de agenda y reservas (alumno, docente, admin)

**Fecha:** 2026-07-08 · **Branch:** `feat/lti-moodle` · **Estado:** aprobado (alcance por Farid; 18 hallazgos de revisión multi-agente aplicados, veredicto del decisor: aprobado con must-fix incorporados).

## 1. Contexto y decisiones cerradas

Lo ya construido (LTI 1.3 completo, API del juego con magic links y sesiones, panel staff con acceso firmado, esquema académico) **no se rompe**. Este diseño cubre lo faltante decidido en `CONTEXTO.md`: reservas con control de cupo, agenda del docente, calendario del alumno y CRUD académico mínimo.

Decisiones (Farid 2026-07-08, salvo donde se indica al decisor delegado):

| Tema | Decisión |
|---|---|
| Acceso del alumno | Login propio (correo + contraseña sobre `usuarios`) **y** el launch LTI de Moodle sigue funcionando |
| CRUD académico | Sí, mínimo, roles fijos (Alumno/Maestro/Coordinador/Admin) |
| Alcance visual | Sistema de diseño unificado en toda la plataforma, incluido el panel existente |
| Reserva y juego | Reserva activa obligatoria **solo en el camino de agenda**; LTI directo y magic links del maestro no cambian |
| Stack UI | Inertia v2 + React 19 + Tailwind v4, **sin SSR** |
| RESERVA como entidad separada | Sí (recomendación de CONTEXTO §8, coherente con sus §4–§5). Tarea F2/F3: actualizar `DIAGRAMA-ER.md` (RESERVA, `eventos_agenda.cupo_maximo`, `sesiones_practica.id_reserva`) |
| Default de cupo | Clave de config + `.env` (`CUPO_DEFAULT_EVENTO`, default 5); sin tabla de configuración por ahora |
| Enlace sesión↔reserva | Aditivo y verificable: el evento se liga al Bearer en el redeem vía ability `evento:{id}` (decisor delegado) |

## 2. Arquitectura

Monolito Laravel 12. Dependencias nuevas (aprobadas): `inertiajs/inertia-laravel` v2, `@inertiajs/react`, `react` 19, `react-dom` 19, `@vitejs/plugin-react`.

- **Inertia + React:** todas las páginas de humano — login, portal del alumno, agenda del docente, CRUD académico y panel staff (migración en Fase 5).
- **Blade se queda** en páginas-máquina sin UI real: interstitiales LTI (`auto-post`, `abrir-juego`, `seleccionar-practica`), lanzador `/jugar/{token}` y harness `/demo`. Reciben solo los tokens visuales para no desentonar.
- **Contrato externo de la API del juego sin cambios** (endpoints, requests y responses idénticos). Internamente, `POST /api/game/redeem` y `POST /api/game/sessions` se extienden de forma **aditiva** para el enlace sesión↔reserva (§3.3). `POST /api/links` conserva su `X-Api-Key` (endurecerla sigue fuera de alcance).
- **`/demo` se gatea a `app()->environment('local')`** y la vista deja de prellenar la API key fuera de local. Hoy `/demo` es pública e imprime `LINKS_API_KEY` real (permite forjar magic links de cualquier alumno). Tarea de despliegue en F1: **rotar `LINKS_API_KEY`** en cualquier entorno donde `/demo` haya estado expuesta.

### Autenticación

- El provider `users` de `config/auth.php` ya apunta a `App\Models\Usuario`. Falta:
  - `Usuario::getAuthPasswordName(): string` → `'contrasena_hash'` para `Auth::attempt()`.
  - Página de login (Inertia) registrada con `->name('login')` (requisito del middleware `Authenticate` para redirigir invitados) + `POST /login` con `Auth::attempt(['correo' => …, 'password' => …])`, verificación de `activo`, rate limiting (5 intentos/min por correo+IP), `session()->regenerate()`.
  - Redirect por rol: Alumno → `/mi/calendario`; Maestro/Coordinador/Admin → `/panel`.
- **Middleware:** `/mi/*` lleva `auth` + `EnsureAlumno` (invitado → redirect a `route('login')`; autenticado sin rol correcto → 403). Las rutas del panel — **incluidas las existentes** — pasan a `auth` + `panel` (invitado ahora recibe redirect a login en vez de 403; se actualiza el test de guard correspondiente). Coordinador/Admin ven todo el panel; Maestro solo lo suyo (regla existente).
- **Logout unificado:** `POST /logout` (todos los roles) invalida sesión, regenera token y redirige a `/login`. `/panel/salir` queda como alias con el mismo comportamiento (hoy redirige a `panel.acceso-invalido`, destino incorrecto para quien puede reloguearse con contraseña).
- **Sesión web solo la crean**: el login propio y el acceso al panel por URL firmada (único `Auth::login` actual). **El launch LTI no crea sesión web del portal y se conserva exactamente así** (va directo al juego). Si algún día el alumno LTI debiera aterrizar logueado en `/mi/*`, es decisión nueva fuera de este alcance.
- **Guardrail Sanctum:** nunca habilitar `statefulApi()`/`StartSession` sobre `/api/game/*` (con `sanctum.guard => ['web']`, una sesión web ganaría `TransientToken` que pasa `abilities:game`). Test de regresión en §7. Especialmente vigilar en F5, momento típico de agregar `statefulApi()` "para Inertia" — Inertia NO lo necesita (usa sesión web normal).
- Sin "recordarme" (no hay `remember_token`), sin registro público, sin password reset por correo (el admin asigna contraseñas desde el CRUD).

## 3. Modelo de datos (migraciones nuevas)

1. **`eventos_agenda.cupo_maximo`** — `ADD COLUMN unsignedSmallInteger NOT NULL DEFAULT 5` (fast-default en PG11+, sin rewrite) seguido, en la misma migración, de backfill `UPDATE … FROM espacios SET cupo_maximo = LEAST(5, espacios.capacidad)` para eventos con espacio de capacidad no nula. Prefill en UI al crear evento: `min(config('metaverso.cupo_default_evento'), espacio.capacidad ?? ∞)`, editable, mínimo 1. Config nueva: `'cupo_default_evento' => (int) env('CUPO_DEFAULT_EVENTO', 5)`.
2. **`reservas`** — `id_reserva` PK, `id_evento` FK→eventos_agenda, `id_alumno` FK→alumnos, `estatus` string default `'activa'` (`activa` | `cancelada`; extensible a `lista_espera` sin migración), timestamps (`created_at` = fecha de reserva). Índices: parcial único Postgres `UNIQUE (id_evento, id_alumno) WHERE estatus = 'activa'` (vía `DB::statement`; cubre también el conteo de cupo con index-only scan) + índice simple sobre `id_alumno` (para el calendario del alumno; Postgres no indexa FKs automáticamente). **La migración y el modelo `Reserva` nacen en F2** (tabla vacía) para que la validación de cupo del PUT funcione; los flujos llegan en F3.
3. **`sesiones_practica.id_reserva`** — FK nullable → reservas. Mecanismo verificable del enlace: en `POST /api/game/redeem`, si el `TokenJuego` trae `id_evento`, el Bearer de Sanctum se crea con abilities `['game', 'evento:{id}']`. En `POST /api/game/sessions`, el evento para buscar la reserva sale **solo** de esa ability (nunca del body): si `ability evento == id_evento del body`, se busca reserva activa (evento, alumno) y se enlaza si existe. Tokens sin evento (`lti-redeem`) y bodies que no coinciden con la ability no enlazan nada. El contrato del endpoint no cambia (el body sigue mandando `id_evento` para crear la sesión, como hoy).

`Reserva` con relaciones `evento()`, `alumno()`; `EventoAgenda` gana `reservas()` y helper `reservasActivas()`.

## 4. Reglas de negocio

### Reservar (POST /mi/reservas, `{id_evento}`)

Throttle 10/min por usuario. Transacción única, conforme a `RESERVA_SECUENCIA.md`:

1. `SELECT … FOR UPDATE` sobre la fila del evento (serializa el conteo).
2. Guardas en orden: evento existe, `estatus = 'programado'`, `fecha_hora_inicio > now()` (409/422); inscripción `activa` en el grupo del evento (403); sin reserva activa previa (409); `count(reservas activas) < cupo_maximo` (409 "slot lleno").
3. INSERT reserva → 201. El índice parcial único es el respaldo a nivel BD.

### Cancelar reserva (DELETE /mi/reservas/{reserva})

Solo el dueño, solo `activa`, solo antes de `fecha_hora_inicio`. `estatus = 'cancelada'` (sin borrar fila). Re-reservar después es válido.

### Evento cancelado o reprogramado por el docente

Las reservas no se tocan: el calendario del alumno muestra el estatus/horario vigente y el botón de jugar respeta la ventana. No hay "reactivar" evento.

### Jugar desde la agenda (POST /mi/eventos/{evento}/jugar)

Throttle 10/min por usuario. Requiere: reserva activa del alumno en ese evento **y** `now()` en `[fecha_hora_inicio, fecha_hora_fin]` **y** evento no cancelado. Antes de emitir un `TokenJuego` nuevo, se invalidan (`usado = true`) los tokens no canjeados previos del mismo (usuario, evento) — evita acumular N magic links vigentes de 120 min. Genera token con `MagicLinkService` y responde **`Inertia::location(url('/jugar/'.$token))`** (409 + `X-Inertia-Location`, fuerza full-page visit — `/jugar` es Blade y dispara un deeplink de esquema custom que exige navegación top-level; un `redirect()` normal dejaría el HTML dentro del modal de error de Inertia). `ponytail:` sin margen de tolerancia previo al inicio; si se pide, es una clave de config.

Los otros dos caminos (LTI directo, magic links del panel del maestro) **no cambian**.

### Agenda del docente

- **Autorización:** TODA acción sobre `/panel/eventos/{evento}` (GET detalle, PUT, DELETE) autoriza vía `evento->grupo` con el patrón `autorizarGrupo` existente de `PanelController`; Coordinador/Admin exentos. Ids secuenciales + rutas planas sin esto = IDOR (ver reservas ajenas con nombres/matrículas, reprogramar eventos ajenos).
- `GET /panel/agenda` — eventos de sus grupos (Coordinador/Admin: todos), vista semanal.
- `POST /panel/eventos` — práctica (de la materia del grupo), grupo (propio salvo staff superior), espacio opcional, inicio/fin (`fin > inicio`), cupo (prefill §3.1, mínimo 1).
- `PUT /panel/eventos/{evento}` — reprogramar fechas/espacio/cupo. **Si reduce cupo, usa la misma receta que reservar:** transacción + `lockForUpdate()` sobre la fila del evento → conteo de reservas activas → validar `cupo >= reservas` → UPDATE, mismo orden de locks (evento → reservas) para no introducir deadlocks. Sin lock, la validación es racy frente a un reservar concurrente.
- `DELETE /panel/eventos/{evento}` — cancelar (soft: `estatus = 'cancelado'`).
- `GET /panel/eventos/{evento}` — detalle con reservas activas (alumno, matrícula, fecha).
- Sin validación de empalmes ni guard global de concurrencia (abiertos en CONTEXTO §8).

### Calendario del alumno (GET /mi/calendario)

Vista semanal: eventos de grupos con inscripción activa, cupo restante, sus reservas (activas y pasadas), acciones según estado. Estados: disponible, lleno, reservado, en curso (jugar), finalizado, cancelado.

### CRUD académico (Coordinador/Admin)

**8 recursos** en páginas Inertia: carreras, materias (con asignación a carreras + semestre), **prácticas** (materia, título, descripción, orden, duración, `escena_referencia` como string libre — su formato sigue abierto en CONTEXTO §8; sin vía de alta, la agenda es inoperante en producción), ciclos escolares, espacios, grupos, inscripciones (por grupo), usuarios (crear con rol y contraseña, activar/desactivar, reset manual). Tablas con búsqueda simple + formularios. Unicidad según esquema. Sin borrado físico donde haya FKs: `activo`/estatus donde exista; si no, bloquear delete con dependencias.

## 5. UI / Diseño visual

- Dirección visual con `npx ui-skills start` (skills seleccionados: `dammyjay93/interface-design`, `jakubkrehel/oklch-skill`) + skill `frontend-design` al inicio de F1: identidad propia, tokens en `resources/css/app.css` vía `@theme` de Tailwind v4.
- Estructura: `resources/js/app.jsx`, `Pages/{Auth,Mi,Panel,Admin}/…`, `Layouts/{AppLayout,GuestLayout}`, `Components/` (Button, Card, Table, FormField, Badge, Modal, EmptyState, WeekCalendar).
- Calendario semanal como componente propio (grid CSS), sin librería de calendario.
- Accesibilidad básica: foco visible, labels, contraste, teclado en el calendario.

## 6. Fases de implementación

| Fase | Entrega | Verificación |
|---|---|---|
| **F1** | Inertia v2 + React 19; dirección visual (ui-skills); tokens + layouts + componentes base; login `->name('login')` + `POST /logout` unificado; middleware `auth` en `/mi/*` y panel; redirects por rol; `EnsureAlumno`; **gate de `/demo` a local + nota de rotación de `LINKS_API_KEY`** | Suite existente verde (con test de guard actualizado) + tests de login/logout + Chrome DevTools |
| **F2** | Migraciones `cupo_maximo` (backfill LEAST) **y `reservas` + modelo `Reserva`** (tabla, sin flujos); CRUD de eventos del docente con autorización `evento->grupo` y PUT con lock; `/panel/agenda`; detalle con reservas (vacías aún) | Tests de validación/autorización/IDOR/lock + navegador |
| **F3** | Flujo reservar/cancelar con `FOR UPDATE`; calendario del alumno; jugar con reserva (`Inertia::location`, throttle, invalidación de tokens previos); enlace sesión↔reserva vía ability `evento:{id}`; actualizar `DIAGRAMA-ER.md` | Tests de cupo/concurrencia/bordes/contrato API + navegador |
| **F4** | CRUD académico (8 recursos) | Tests de autorización/validación + navegador |
| **F5** | Migración del panel existente a Inertia + design system; estados vacíos; a11y; Lighthouse. **No introducir `statefulApi()`** | Suite completa verde + navegador + Lighthouse |

Cada fase termina con: `vendor/bin/pint`, `php artisan test --compact`, revisión por agentes expertos y verificación real en navegador (Chrome DevTools MCP).

## 7. Testing

- **Feature tests Pest** por endpoint: reservar (feliz, no inscrito 403, lleno 409, duplicada 409, evento pasado/cancelado, último lugar), cancelar (dueño/ajena/tarde), jugar (con/sin reserva, fuera de ventana, throttle, invalidación de tokens previos), agenda docente (maestro vs coordinador, práctica de otra materia, cupo < reservas con lock, **IDOR: evento de otro maestro → 403 en GET detalle, PUT y DELETE**), CRUD (unicidad, dependencias), login (correcto, inactivo, rate limit, redirect por rol), logout (todos los roles → /login).
- **Contrato API del juego:** sesión sin reserva (LTI / magic link del maestro) responde **idéntico a hoy**; sesión con reserva solo agrega el enlace interno. **Guardrail Sanctum:** `/api/game/me` con solo cookie de sesión web → 401.
- **Páginas Inertia** con `AssertableInertia`.
- **Concurrencia:** caso "último lugar" secuencial + revisión experta del código transaccional. `ponytail:` prueba de paralelismo real con dos conexiones pendiente; se agrega si aparece un incidente de sobreventa.
- Suite existente (LTI, game API, panel) verde en todas las fases.

> **Nota sobre códigos de estado (decisor delegado, F3):** en el portal web los conflictos
> de negocio (lleno, duplicada, fuera de ventana, tarde para cancelar, evento no reservable)
> se materializan como **422 / redirect-back con errores** (`ValidationException`, flujo
> nativo de formularios Inertia; un 409 se pintaría como modal de error crudo). Los **403**
> de autorización siguen siendo abort. Los 409/410 literales aplican solo a la API JSON del
> juego, cuyo contrato está congelado. F4/F5 no deben "corregir" los tests de vuelta a 409.

## 8. Puntos de extensión preparados (NO construir ahora)

| Pendiente (CONTEXTO §8) | Preparación en este diseño |
|---|---|
| Lista de espera | `reservas.estatus` es string extensible |
| Asistencia | `sesiones_practica.id_reserva` + binding verificable por ability ya enlazan quién reservó con quién jugó |
| Recordatorios | La creación de reservas queda centralizada en un solo action/service; cuando se construyan recordatorios, ahí se emitirá el evento de dominio. No se crea ninguna clase de evento ahora |
| Rúbricas / promedios | `calificacion` float + `datos_resultado` jsonb sin esquema impuesto |
| Multi-carrera, prerrequisitos | Sin cambios; pivotes nuevos en su momento |
| Guard global de sesiones concurrentes | El chequeo de "jugar" está centralizado en un solo action/service |
| Sync Moodle como fuente de verdad | Aprovisionamiento JIT existente ya es idempotente |

## 9. Fuera de alcance explícito

Registro público, password reset por correo, notificaciones, lista de espera, asistencia, rúbricas, empalme de horarios, SSR, PWA/offline, edición de telemetría, permisos granulares, sync bidireccional con Moodle, endurecer `/api/links` más allá de su `X-Api-Key`.
