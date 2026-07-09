# F5: Migración del panel a Inertia + pulido — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Todo el panel staff (dashboard, grupo, links, resultados, sesión) migra de Blade a Inertia + design system; un solo lenguaje visual en la plataforma; pulido de a11y y estados vacíos. **No se introduce `statefulApi()`** (guardrail del spec §2).

**Architecture:** Los controladores del panel cambian `view()` por `Inertia::render()` con props planas (mismo patrón de F2-F4). Las vistas Blade migradas se eliminan. Quedan en Blade solo: interstitiales LTI, `/jugar`, `/demo` y el root template de Inertia. `/` redirige a `/login`.

## Enmiendas (revisión experta 2026-07-08, decisor delegado)

1. Ruta GET companion en `/panel/grupos/{grupo}/eventos/{evento}/links` → redirect al grupo (tras el POST Inertia, refresh/back daría 405).
2. `Grupo.jsx` incluye el link a `panel.grupos.resultados` (única navegación hacia Resultados en toda la UI).
3. Porte de asserts: `viewData(...)` → `has('filas', n)`/comparación por ids en props; `assertSee($url)` NO porta (data-page escapa el JSON) → invariante de props con `str_starts_with(url('/jugar/'))`.
4. `ExampleTest` existe y toca `/`: cambiarlo a `assertRedirect(route('login'))` y eliminar `welcome.blade.php`.
5. `panel.acceso.invalido` es código muerto (sin consumidores): eliminar ruta y vista, NO crear página Inertia.
6. Task A no cambia firma/visibilidad de `autorizarGrupo()`/`gruposVisibles()` (los consumen PanelLink, PanelResultado y AgendaController). El botón "Generar links" se verifica en navegador tras el merge de B.
7. Checklist de paridad Blade→Inertia: Grupo (back-link, contador alumnos, columnas Práctica/Inicio/Estatus), Resultados (columna Inicio, link detalle, vacío), Sesion (fecha_fin, back-link, props con id_grupo), Links (back-link, CSV `links-grupo-{id}.csv`). Props planas mapeadas (patrón AgendaController), no modelos crudos.
8. Nav con `<Link>`: usar `usePage().url.split('?')[0]` en lugar de `window.location.pathname`.

## Global Constraints

- Spec §6-F5 y §2 (guardrail Sanctum — el test por invariantes ya existe y debe seguir verde).
- La lógica de autorización/queries de los controladores NO cambia — solo la capa de render.
- Tests del panel: se actualizan de assertSee-sobre-Blade a `AssertableInertia` (component + props), conservando los mismos casos y autorizaciones.
- El flujo LTI de "abrir juego" y los magic links NO se tocan.
- Nav de AppLayout: migrar de `<a>` a `<Link>` de Inertia SOLO cuando origen y destino sean Inertia (tras esta fase, todo el nav). El comentario ponytail del nav se elimina.
- `LoginController::store` conserva `Inertia::location` (funciona para ambos mundos y evita regresiones).
- Pint + suite completa + build + navegador + Lighthouse al cierre.

---

### Task A (paralelizable): dashboard y detalle de grupo

**Files:** `app/Http/Controllers/Panel/PanelController.php` (dashboard→`Panel/Dashboard`, show→`Panel/Grupo`), `resources/js/Pages/Panel/Dashboard.jsx` (cards de grupos: clave+materia, ciclo, alumnos count, link a detalle y a agenda), `resources/js/Pages/Panel/Grupo.jsx` (info del grupo + tabla de alumnos inscritos + tabla de eventos con link a `panel.eventos.show` + botón "Generar links" por evento → POST a `panel.grupos.eventos.links`), actualizar `tests/Feature/Panel/DashboardTest.php` y `GrupoDetalleTest.php` a AssertableInertia. Eliminar `resources/views/panel/dashboard.blade.php` y `grupo.blade.php`.

### Task B (paralelizable): links, resultados y sesión

**Files:** `app/Http/Controllers/Panel/PanelLinkController.php` (generar→`Panel/Links` con filas nombre/matrícula/url + botón copiar y descarga CSV client-side como hoy), `app/Http/Controllers/Panel/PanelResultadoController.php` (index→`Panel/Resultados`: tabla de sesiones con alumno, práctica, estatus badge, calificación mono; sesion→`Panel/Sesion`: detalle + telemetría `datos_resultado` en `<pre>` mono), páginas JSX correspondientes, actualizar `tests/Feature/Panel/GenerarLinksGrupoTest.php` y `ResultadosTest.php`. Eliminar `resources/views/panel/links.blade.php`, `resultados.blade.php`, `sesion.blade.php`.

### Task C (inline, tras A y B): shell, root y pulido

- `/` → `redirect()->route('login')` (welcome stock fuera; actualizar el test que esperaba 200 en `/` si existe).
- `panel/acceso-invalido` → página Inertia simple (`Auth/AccesoInvalido`) con link a `/login`; eliminar la vista Blade y el `Route::view`.
- `resources/views/panel/layout.blade.php` se elimina (ya sin consumidores).
- Nav AppLayout: `<a>` → `<Link>` (excepto logout, que sigue siendo botón POST).
- A11y: `name`/`aria-label` en el buscador de `Recurso.jsx`/`Usuarios.jsx`/`Grupos.jsx` (issue de consola detectado en F4), revisar labels de selects en modales.
- Lighthouse (Chrome DevTools) sobre `/login`, `/mi/calendario`, `/panel` migrado y `/admin/carreras`: sin fallas graves de a11y/best-practices; contraste AA.
- Recorrido completo en navegador de los tres roles + suite completa + pint + build.

### Task D (cierre de proyecto)

- Revisión experta del diff F5; aplicar must-fix.
- `git log` limpio; resumen final de las 5 fases para el equipo (en el mensaje final, no en un doc nuevo).
