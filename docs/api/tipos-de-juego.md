# Catálogo de juegos de Godot — contrato para el cliente

El cliente Godot recibe, al canjear el token, la escena que debe cargar. Su trabajo es
identificarla, cargarla, y al terminar reportar la calificación (0–100) de vuelta al backend.

La **fuente única de verdad** del catálogo de juegos/escenas es
[`config/juegos.php`](../../config/juegos.php). El admin enlaza cada práctica a uno de sus ids
desde el panel (Administración → Prácticas); ese id se guarda en `practicas.escena_referencia`.

Para el flujo completo de endpoints (redeem → sesión → complete) ver
[`flujo-del-juego.md`](./flujo-del-juego.md). Este documento solo cubre **qué escena cargar**.

## Cómo se conecta una práctica con su juego

1. El admin, en el panel, elige de un catálogo a qué **juego/escena de Godot** apunta la práctica.
   Se guarda en `practicas.escena_referencia` (un id del catálogo, no texto libre).
2. El maestro genera los magic links por alumno para un evento de esa práctica. Cada link
   (vía su token) queda ligado a la práctica, y por tanto a su escena.
3. El alumno abre su link; Godot canjea el token con `POST /api/game/redeem` y recibe la
   práctica. **`practica.escena_referencia` es el id de la escena que Godot debe cargar.**
4. No hay parámetros de jugabilidad: la práctica solo dice *cuál* escena, no *cómo* jugarla.
   Cualquier ajuste vive dentro de la escena de Godot.

## Ejemplo: respuesta de `POST /api/game/redeem`

```json
{
  "access_token": "12|abcdef...",
  "token_type": "Bearer",
  "alumno": { "id_alumno": 15, "matricula": "20250001" },
  "practica": {
    "id_practica": 2,
    "titulo": "Práctica 1: Variables",
    "descripcion": "Introducción",
    "escena_referencia": "recolecta"
  },
  "evento": {
    "id_evento": 7,
    "fecha_hora_inicio": "2026-08-01T10:00:00+00:00",
    "fecha_hora_fin": "2026-08-01T12:00:00+00:00",
    "estatus": "programado"
  }
}
```

- `practica.escena_referencia` es siempre uno de los ids del catálogo (`config/juegos.php`).
- El mismo objeto `practica` (con su `escena_referencia`) se recibe también vía el flujo LTI
  en `POST /api/game/lti-redeem`.

## Catálogo de juegos/escenas

Ids disponibles hoy (id de escena que Godot carga → etiqueta que ve el admin):

| `escena_referencia` | Etiqueta en el panel            |
|---------------------|---------------------------------|
| `recolecta`         | Recolecta — junta objetos       |
| `ensambla`          | Ensambla — ordena la secuencia  |
| `circuito`          | Circuito — recorre estaciones   |

Cada id corresponde a una escena que el equipo de Godot construye. Godot debe manejar con un
error amable el caso de un `escena_referencia` que no reconozca.

## Agregar un juego nuevo

1. Una entrada nueva en [`config/juegos.php`](../../config/juegos.php): `'<id>' => '<Etiqueta>'`.
   El id es lo que viajará en `escena_referencia`.
2. La escena correspondiente en el proyecto de Godot, que carga cuando recibe ese id.

Nada más: el panel muestra la nueva opción en el selector y la validación acepta el id nuevo
automáticamente.
