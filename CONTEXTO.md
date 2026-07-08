# Contexto del proyecto — Campus virtual de prácticas

> Documento vivo. Reúne el objetivo del proyecto, el alcance, las decisiones ya
> tomadas y —muy importante— lo que **todavía no está definido**. Sirve para
> alinear al equipo y para dar contexto a herramientas de IA.
> Última revisión: pendiente de fecha.

---

## 1. Qué queremos lograr

Construir una plataforma para gestionar la **agenda de prácticas** de una escuela,
donde las prácticas se realizan dentro de un **juego** (actualmente en Unreal
Engine). El sistema debe permitir que los docentes programen prácticas, que los
alumnos reserven su lugar en un horario, y que el juego se autentique mediante un
**enlace dinámico** para lanzar la práctica y devolver resultados/calificación.

La plataforma se integra con el **LMS institucional (Moodle) vía LTI 1.3**: el
alumno entra desde Moodle a la actividad de la práctica y desde ahí abre el juego.

En una frase: *un puente entre la agenda académica, un LMS y un juego, donde cada
práctica agendada se convierte en una sesión jugable con resultados que regresan
al expediente del alumno.*

---

## 2. Actores y roles

- **Alumno** — consulta su calendario, reserva prácticas, las juega y recibe calificación.
- **Docente / Maestro** — programa prácticas en la agenda, define cupos, consulta reservas y resultados.
- **Coordinador / Administrador** — (previsto) gestión de carreras, materias, ciclos y usuarios.
- **Sistema** — actor no humano: envía recordatorios, valida cupos, dispara procesos automáticos.
- **Juego (cliente Unreal)** — actor externo que consume la API para autenticar, iniciar y cerrar sesiones de práctica.

---

## 3. Alcance funcional

### Académico
Gestión de carreras, materias (compartibles entre carreras), ciclos escolares,
grupos (materia + docente + ciclo) e inscripciones de alumnos a grupos.

### Agenda y calendario
El docente agenda **eventos** (una práctica, para un grupo, en un espacio y horario).
El alumno ve su calendario y **reserva** un lugar en un slot. Incluye reprogramar,
cancelar y consultar reservas.

### Prácticas y juego
Cada práctica pertenece a una materia y referencia una escena/nivel del juego. Al
reservar y llegar el momento, se genera un enlace dinámico que lanza el juego; al
terminar, el juego reporta la **sesión** (estatus, calificación, telemetría).

---

## 4. Modelo de datos (resumen)

El diagrama ER completo está en `campus_practicas_er.mermaid`. Entidades principales:

- **Identidad:** `ROL`, `USUARIO`, `ALUMNO` (1:1 con usuario), `MAESTRO` (1:1 con usuario).
- **Académico:** `CARRERA`, `MATERIA`, `MATERIA_CARRERA` (M:N), `CICLO_ESCOLAR`, `GRUPO`, `INSCRIPCION`.
- **Prácticas y agenda:** `PRACTICA`, `ESPACIO`, `EVENTO_AGENDA` (el "slot"), `RESERVA`, `SESION_PRACTICA`.
- **Integración con el juego:** `TOKEN_JUEGO` (enlace dinámico / magic link, desacoplado del motor).

Notas de diseño:
- La identidad usa una tabla base `USUARIO` + extensiones `ALUMNO` / `MAESTRO`.
- `SESION_PRACTICA.datos_resultado` es JSON (telemetría cruda del juego).
- `PRACTICA.escena_referencia` y `TOKEN_JUEGO.plataforma` mantienen el modelo
  **agnóstico al motor**, de modo que cambiar Unreal por otra cosa no rompa la BD.

---

## 5. Reglas de negocio clave

- **Límite de cupo por slot.** Tope de alumnos que pueden reservar un slot. Es una
  restricción de **recurso** (no saturar el servidor del juego), no académica. Es
  **general y configurable**, con valor por defecto **5**. Se guarda en
  `EVENTO_AGENDA.cupo_maximo` (default heredado de la configuración / capacidad del espacio).
- **Reserva a prueba de concurrencia.** La validación de cupo se hace dentro de una
  transacción con bloqueo de fila (`SELECT ... FOR UPDATE`) para evitar sobrevender
  el último lugar. Detalle en `reserva_practica_secuencia.mermaid`.
- **Verificación de inscripción.** Un alumno solo puede reservar prácticas de grupos
  en los que está inscrito.
- **Dos contextos de autenticación separados.** El portal usa la sesión del usuario;
  el juego usa `X-Api-Key` para pedir el enlace y luego un token/`access_token` de
  sesión de juego. No deben mezclarse.

---

## 6. Stack tecnológico

| Tecnología | Uso en el proyecto |
|---|---|
| Laravel 12 (PHP 8.3+) | Framework del backend y API REST |
| PostgreSQL | Base de datos relacional (usa columnas `jsonb`) |
| Laravel Sanctum | Emisión de tokens Bearer para la sesión del juego |
| packbackbooks/lti-1p3 | Integración LTI 1.3 (OIDC, JWT, Deep Linking, AGS) |
| Moodle 5.0 | LMS institucional integrado vía LTI 1.3 |
| Docker | Instancia local de Moodle para pruebas |
| Unreal Engine (C++) | Cliente del juego que consume la API *(sujeto a cambios)* |
| Pest / PHPUnit | Pruebas automatizadas |

---

## 7. Integraciones

### Moodle / LTI 1.3
El alumno accede a la actividad de la práctica desde un curso en Moodle. La
integración usa OIDC + JWT, Deep Linking (para colocar la actividad) y AGS
(Assignment and Grade Services) para regresar la calificación al libro de
calificaciones de Moodle.

### Juego — flujo del enlace dinámico
Endpoints observados (nombres tentativos):

1. `POST /api/links` (con `X-Api-Key`) → devuelve `url`, `deeplink` y `expira`.
2. El alumno abre el deeplink con el token → el juego llama `POST /api/game/redeem`
   → devuelve `access_token`, `alumno`, `practica`, `evento`.
3. `GET /api/game/me` → devuelve `usuario` y `alumno`.
4. `POST /api/game/sessions` → crea la sesión, devuelve `id_sesion` en progreso.
5. `POST /api/game/sessions/{id}/complete` → marca la sesión como completada y envía la calificación.

Existe además un **modo demo sin Unreal** (simula la partida desde el navegador y
envía la calificación de regreso), útil para pruebas sin depender del juego.

---

## 8. Lo que aún NO está definido (decisiones abiertas)

Esta es la sección a revisar en equipo. Nada de aquí está cerrado.

### Sobre el juego / Unreal Engine
- **El motor puede cambiar.** Unreal es el cliente actual, pero está sujeto a cambios.
  Por eso el modelo se diseñó agnóstico (ver §4), pero falta definir:
  - ¿Qué formato tendrá exactamente `escena_referencia`? (¿id de nivel, nombre, URL?)
  - ¿Qué estructura tendrá la telemetría en `datos_resultado`? (esquema del JSON)
  - ¿El juego corre en un solo servidor, por cliente/local, o en la nube? Esto define
    si el límite de cupo es realmente contra concurrencia de servidor.
  - ¿Cómo se distribuye/actualiza el cliente del juego a los alumnos?

### Sobre el cupo y la concurrencia
- ¿Basta con "no empalmar horarios" o se necesita un **guard de sesiones concurrentes
  globales** en el momento de lanzar el juego? (Depende del punto anterior.)
- ¿Dónde vive el valor por defecto del cupo? (¿tabla de configuración, `.env`, ambas?)

### Sobre el modelo académico
- ¿Un alumno puede pertenecer a **más de una carrera** a la vez? (Cambia la relación `CARRERA–ALUMNO`.)
- ¿Se necesitan **prerrequisitos** entre materias?
- ¿Roles fijos (Alumno/Maestro/Coordinador/Admin) o un sistema de permisos más flexible?

### Sobre evaluación
- ¿La calificación de una práctica es un solo número o hay **rúbrica** / múltiples criterios?
- ¿Se promedian varias sesiones o cuenta la última / la mejor?

### Sobre reservas y agenda
- ¿`RESERVA` como entidad separada (permite cancelar antes de jugar) o `SESION_PRACTICA`
  hace doble función? (Recomendado: separada.)
- ¿Se marca **asistencia**? ¿Hay lista de espera cuando un slot se llena?
- ¿Recordatorios automáticos? ¿Por qué canal (correo, notificación en Moodle)?

### Sobre la relación con Moodle
- ¿El portal de agenda es una app propia, o vive **dentro** de Moodle como actividad LTI?
- ¿La fuente de verdad de usuarios/inscripciones es Moodle o la base propia? ¿Se sincronizan?

---

## 9. Artefactos generados hasta ahora

- `campus_practicas_er.mermaid` — Diagrama entidad-relación.
- Casos de uso — agenda (docente) y calendario/reservas (alumno).
- `reserva_practica_secuencia.mermaid` — Diagrama de secuencia del flujo de reserva.
- Diagrama de secuencia del lanzamiento del juego *(en el documento original del equipo)*.