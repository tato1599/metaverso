# Diseño — MVP Backend Metaverso Escolar (TecNM)

**Fecha:** 2026-06-23
**Autor:** Daniel Neri (developers@terabytio.com)
**Estado:** Aprobado para implementación

## 1. Contexto

Proyecto para el TecNM: un "metaverso escolar" donde los alumnos realizan
actividades/prácticas dentro de un juego de **Unreal Engine (escritorio)**. El
juego se conecta a un **backend web (Laravel 13 + PostgreSQL)** que administra
usuarios, materias, grupos, prácticas, agenda y calificaciones.

Los alumnos entran al juego mediante un **magic link** (token de un solo uso en
una URI). La integración con **Moodle vía LTI** queda fuera de este entregable
(fase 2).

Este documento cubre **únicamente el primer entregable: el MVP del backend y la
API que Unreal consumirá.**

## 2. Decisiones tomadas (brainstorming)

| Decisión | Elección |
|---|---|
| Primer entregable | Backend Laravel + API REST |
| Plataforma del juego | Unreal (escritorio); el backend NO genera el proyecto Unreal |
| Moodle / LTI | Fase 2 (fuera de alcance aquí) |
| Propósito del magic link | Lanzar el juego de Unreal (token de un solo uso por alumno + evento) |
| Calificación | Por sesión (campo `calificacion` en `sesiones_practica`) |
| Alcance del backend | MVP funcional (migraciones, auth magic link, endpoints clave, seeders, tests) |

## 3. Stack técnico

- **Laravel 13** (PHP)
- **PostgreSQL** — se usa `jsonb` para `datos_resultado` (telemetría cruda del juego)
- **Laravel Sanctum** — emite un Bearer token de sesión al juego tras canjear el magic link
- **Pest o PHPUnit** — pruebas del flujo crítico

## 4. Alcance

### Dentro del MVP
1. Migraciones de **toda** la base de datos del diagrama (es barato y deja el cimiento completo).
2. Modelos Eloquent con relaciones.
3. Generación de magic link (token de un solo uso por alumno + evento).
4. Flujo de juego por API (canje, inicio de sesión, envío de resultado).
5. Seeders con datos demo.
6. Tests del flujo crítico (token y sesión de práctica).

### Fuera del MVP (fase 2)
- Paneles CRUD de maestro/admin y agenda visual.
- Integración Moodle / LTI (traer alumnos y devolver calificaciones).
- Calificación agregada por materia/parcial (aquí solo nota por sesión).

## 5. Modelo de datos

Nombres de tabla en **español, plural, snake_case** (siguiendo el diagrama del
equipo). Estructura derivada del diagrama ER del compañero, con correcciones de
seguridad.

Tablas:
`roles, usuarios, alumnos, maestros, carreras, materias, materia_carrera,
ciclos_escolares, grupos, espacios, practicas, inscripciones, eventos_agenda,
sesiones_practica, tokens_juego`

### Correcciones al diagrama original
1. **`tokens_juego` guarda el HASH del token**, no el texto plano. El texto solo
   viaja dentro del magic link.
2. Campos del token: `token_hash` (UK), `id_usuario` (FK), `id_evento` (FK),
   `plataforma`, `fecha_expiracion`, `usado` (bool), `fecha_uso` (nullable),
   `ip_origen` (nullable, se registra al canjear).
   - **Expiración configurable** (env `MAGIC_LINK_TTL_MINUTES`), **default 120
     minutos (2 horas)**.
3. `sesiones_practica.datos_resultado` es `jsonb`.
4. `sesiones_practica.estatus`: `en_progreso | completada | abandonada`.
5. `eventos_agenda.estatus`: `programado | en_curso | finalizado | cancelado`.

### Relaciones clave para el flujo de juego
```
usuarios 1—1 alumnos
eventos_agenda —* tokens_juego        (un evento genera N tokens, uno por alumno)
eventos_agenda —* sesiones_practica
alumnos —* sesiones_practica
practicas —* sesiones_practica
```

## 6. Flujo del magic link → Unreal

```
Maestro genera link   →  https://SERVER/jugar/{TOKEN}
        │
Alumno hace clic      →  página con botón "Abrir juego"
        │                 dispara deep link: tecnm-metaverso://play?token=TOKEN
        ▼
Unreal recibe token   →  POST /api/game/redeem
        │                 valida (no expirado, no usado), marca usado,
        │                 guarda ip_origen y fecha_uso → devuelve datos del
        │                 alumno + práctica + Bearer token de sesión (Sanctum)
        ▼
Unreal inicia         →  POST /api/game/sessions            (estatus: en_progreso)
Unreal termina        →  POST /api/game/sessions/{id}/complete
                          (calificacion + datos_resultado jsonb → estatus: completada)
```

**Decisión de diseño:** el deep link con esquema propio (`tecnm-metaverso://`) es
el patrón estándar para lanzar una app de escritorio desde el navegador. El
backend permanece **agnóstico del transporte**: solo entrega y valida tokens. Si
en el futuro Unreal usa otro mecanismo (copiar/pegar token, polling), la API no
cambia.

## 7. API

Prefijo `/api/game` para lo que consume Unreal. Auth con Sanctum (Bearer)
excepto `redeem`, que se autentica con el propio magic token.

| Método | Ruta | Auth | Para qué |
|---|---|---|---|
| `POST` | `/api/game/redeem` | magic token (body) | Canjea el link; devuelve Bearer + datos del alumno y la práctica |
| `GET`  | `/api/game/me` | Bearer | Datos del alumno/práctica de la sesión actual |
| `POST` | `/api/game/sessions` | Bearer | Inicia una sesión de práctica (estatus `en_progreso`) |
| `POST` | `/api/game/sessions/{id}/complete` | Bearer | Envía resultado: `calificacion` + `datos_resultado` |
| `POST` | `/api/links` | (interno/maestro) | Genera un magic link para un alumno + evento |

### Reglas de validación
- `redeem`: token existe, `usado = false`, `fecha_expiracion > now()`. Si falla → 401/410.
  Al éxito: `usado = true`, `fecha_uso = now()`, `ip_origen = request ip`.
- `sessions/{id}/complete`: la sesión pertenece al alumno autenticado y está
  `en_progreso`. `calificacion` numérica en rango válido (0–100).
- El Bearer token de Sanctum se emite con expiración corta (configurable).
- Magic link: TTL configurable vía `MAGIC_LINK_TTL_MINUTES` (default 120 min).

## 8. Manejo de errores

- Respuestas JSON consistentes: `{ "message": "...", "errors": {...} }`.
- Códigos: 401 (token inválido), 410 (token expirado/usado), 422 (validación),
  403 (sesión de otro alumno), 404 (recurso inexistente).
- Errores no se filtran con stack traces en producción (config estándar Laravel).

## 9. Pruebas

Tests de feature sobre el flujo crítico:
1. Generar magic link → canjear → recibir Bearer y datos correctos.
2. Token expirado → 410.
3. Token ya usado → 410.
4. Iniciar sesión y completarla → se guarda `calificacion` y `datos_resultado`.
5. Completar sesión de otro alumno → 403.

## 10. Datos demo (seeders)

1 ciclo escolar activo, 1 carrera, 1 materia, 1 maestro, 1 grupo, 1 espacio,
1 práctica, varios alumnos, 1 evento de agenda, y un comando/seeder que genera un
magic link de ejemplo imprimible en consola para probar manualmente.

## 11. Entregables

- Proyecto Laravel 13 configurado para PostgreSQL.
- Migraciones, modelos y relaciones de todas las tablas.
- Endpoints de `/api/game/*` y `/api/links`.
- Seeders demo + comando para generar magic link de prueba.
- Suite de tests del flujo crítico.
- README con instrucciones de arranque local.
