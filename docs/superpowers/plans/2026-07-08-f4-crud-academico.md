# F4: CRUD académico (8 recursos) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Coordinador/Admin gestionan carreras, materias (con asignación a carreras + semestre), prácticas, ciclos, espacios, grupos, inscripciones (por grupo) y usuarios, desde páginas Inertia con el design system.

**Architecture:** Middleware nuevo `admin` (`esCoordinadorOAdmin`). Un controlador chico por recurso bajo `app/Http/Controllers/Admin/`. Una página genérica `Admin/Recurso.jsx` (tabla + modal de formulario, schema-driven por props) cubre los recursos simples; páginas propias para grupos/inscripciones y usuarios. TODAS las rutas se registran de una vez en la Task 1 (los clusters posteriores solo agregan archivos nuevos — sin conflictos para paralelizar).

**Tech Stack:** igual que F1–F3.

## Enmiendas (revisión experta 2026-07-08, decisor delegado)

1. **BLOQUEANTE inscripciones:** unique (alumno,grupo) incondicional en BD → store hace upsert lógico: activa→422 "ya inscrito"; baja→reactivar (estatus activa + fecha_inscripcion=today); inexistente→crear. Test alta→baja→re-alta = UNA fila activa.
2. Ciclos: delete bloqueado si tiene grupos (422).
3. Carreras: delete bloqueado también por filas en materia_carrera (mensaje propio).
4. Materias: destroy hace detach del pivote en la misma transacción tras validar guards de grupos/prácticas.
5. Materias: campo `creditos` required|integer|min:0 (NOT NULL en BD).
6. Usuarios rol Alumno: agregar `semestre_actual` (1–15) y `generacion` (prefill año actual) — NOT NULL sin default.
7. Usuarios rol Maestro: `numero_empleado` required|unique.
8. Pivote: `Materia::carreras()` belongsToMany con withPivot('semestre')->withTimestamps(); sync con mapWithKeys; validar `carreras.*.id_carrera` distinct.
9. Contrato de `campos[]` de Recurso.jsx: tipo ∈ {text, number, date, select, checkbox, textarea}; Recurso.jsx es SOLO LECTURA para Tasks 2–5; `Select`/`Textarea` se exportan desde FormField.jsx en Task 1.
10. Task 1 crea TODOS los controladores restantes como stubs (métodos con abort(501)) junto con las rutas — route:list truena con controladores inexistentes.
11. Usuarios: nadie puede desactivarse a sí mismo (422, cualquier rol); `id_rol` NO editable en update.
12. Desactivar corta acceso vivo: `&& $u->activo` en EnsurePanelAccess, EnsureAlumno y EnsureCoordinadorOAdmin. Test: usuario desactivado → 403 en /panel, /mi/calendario y /admin/carreras.
13. Escrituras SOLO desde el array de validate() (nunca $request->all()); test de mass-assignment en usuarios (contrasena_hash/lti_user_id extra no mutan). Helpers de Task 1 con prefijo `admincarr*`.

## Global Constraints

- Spec §4 (CRUD académico), §6-F4, §7 y la nota de códigos (conflictos de negocio → 422 validación; autorización → 403).
- Sin borrado físico donde haya FKs: `activo`/estatus si existe; si no, bloquear delete con dependencias (422 con mensaje claro).
- Unicidad según esquema: `carreras.clave`, `materias.clave`, `ciclos_escolares.nombre` (no único en BD — validar en app), `usuarios.correo`, `alumnos.matricula`.
- Helpers Pest con prefijos nuevos: `admincat*` (catálogos), `adminmp*` (materias/prácticas), `admingi*` (grupos/inscripciones), `adminusr*`. Prohibido redeclarar helpers existentes.
- Tests por recurso: invitado→login; Maestro→403; Coordinador→200; crear feliz; validación de unicidad; delete bloqueado por dependencias (donde aplique). Con `AssertableInertia` para el index.
- Pint + suite completa verde + build al cierre de cada task.

---

### Task 1 (fundación, inline): middleware `admin`, rutas completas, página genérica y primer recurso (carreras) como patrón

**Files:** `app/Http/Middleware/EnsureCoordinadorOAdmin.php` (alias `admin` en `bootstrap/app.php`), rutas de los 8 recursos en `routes/web.php` (grupo `['auth', 'admin']`, prefijo `/admin`, nombres `admin.{recurso}.{accion}`), `resources/js/Pages/Admin/Recurso.jsx` (genérica: props `titulo`, `columnas[]`, `filas[]`, `campos[]` schema del form, `rutaBase`; tabla con búsqueda client-side simple + modal crear/editar + delete con confirm), `app/Http/Controllers/Admin/CarreraController.php` (index/store/update/destroy), `tests/Feature/Admin/CarrerasTest.php`, nav "Administración" en `AppLayout.jsx` (solo Coordinador/Admin).

Rutas por recurso: `GET /admin/{recurso}` (index), `POST`, `PUT /{id}`, `DELETE /{id}`. Inscripciones viven bajo `/admin/grupos/{grupo}/inscripciones` (store/destroy).

Reglas carreras: clave/nombre required, clave única, duracion_semestres 1–15; delete bloqueado si tiene alumnos.

### Task 2 (paralelizable): catálogos restantes — ciclos y espacios

**Files:** `Admin/CicloController.php`, `Admin/EspacioController.php`, `tests/Feature/Admin/CatalogosTest.php` (helpers `admincat*`). Reusan `Admin/Recurso.jsx`.
- Ciclos: nombre único (validación app), fechas `after`, `activo` bool (checkbox; al activar uno NO se desactivan los demás — fuera de alcance).
- Espacios: nombre required, tipo `fisico|virtual`, capacidad nullable ≥1; delete bloqueado si tiene eventos.

### Task 3 (paralelizable): materias + prácticas

**Files:** `Admin/MateriaController.php` (con `carreras: [{id_carrera, semestre}]` sync al pivote `materia_carrera`), `Admin/PracticaController.php`, páginas `Admin/Materias.jsx` (form con asignación de carreras+semestre), reuso de `Recurso.jsx` para prácticas (campos: materia select, titulo, descripcion, objetivos, orden, duracion_estimada, escena_referencia string libre), `tests/Feature/Admin/MateriasPracticasTest.php` (helpers `adminmp*`).
- Materias: clave única; delete bloqueado si tiene grupos o prácticas.
- Prácticas: orden entero ≥1; delete bloqueado si tiene eventos o sesiones.

### Task 4 (paralelizable): grupos + inscripciones

**Files:** `Admin/GrupoController.php`, `Admin/InscripcionController.php` (store: alumno por matrícula o select, unique alumno+grupo → 422 "ya inscrito"; destroy: estatus `baja`, NO borrar fila), página `Admin/Grupos.jsx` (lista + form; detalle de inscripciones inline o expandible), `tests/Feature/Admin/GruposInscripcionesTest.php` (helpers `admingi*`).
- Grupos: materia/maestro/ciclo selects, clave required, cupo_maximo ≥1; delete bloqueado si tiene eventos o inscripciones.

### Task 5 (paralelizable): usuarios

**Files:** `Admin/UsuarioController.php`, página `Admin/Usuarios.jsx`, `tests/Feature/Admin/UsuariosTest.php` (helpers `adminusr*`).
- Crear: correo único, rol select, contraseña required min:8 (se muestra una vez), nombre/apellidos. Si rol Alumno: matrícula + carrera (crea `Alumno`); si Maestro: número de empleado (crea `Maestro`).
- Editar: datos básicos + activar/desactivar (`activo`), reset de contraseña (campo opcional).
- Sin borrado físico nunca (solo desactivar).
- Guard: un Coordinador no puede desactivarse a sí mismo (422).

### Task 6 (cierre, inline): integración

- Nav "Administración" verificada por rol; recorrido en navegador (crear carrera→materia→práctica→grupo→inscribir alumno→usuario nuevo puede loguearse); suite completa + pint + build; revisión experta del diff F4; aplicar must-fix; commit de cierre.
