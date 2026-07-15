# Tipos de mini-juego — contrato para el cliente Godot

Este documento es el contrato entre el backend y el cliente Godot para los mini-juegos de
prácticas. Es la referencia que el equipo de Godot debe seguir para cargar la escena
correcta, configurarla con los parámetros que definió coordinación, y reportar el resultado.

La **fuente única de verdad** de los tipos y sus parámetros es
[`config/juegos.php`](../../config/juegos.php). Este documento refleja sus valores; si
divergen, `config/juegos.php` manda.

Para el flujo completo de endpoints (magic link → redeem → sesión → complete), ver
[`docs/api/flujo-del-juego.md`](./flujo-del-juego.md). Aquí solo se documenta el contrato
específico de cada tipo de mini-juego.

## Qué es un "tipo de mini-juego"

Cada práctica tiene un `escena_referencia` (uno de `recolecta`, `ensambla`, `circuito`) y un
`config` con los parámetros que coordinación definió para esa práctica en el panel.

Conexión con Godot:

1. Al canjear el magic link (`POST /api/game/redeem`), la respuesta incluye
   `practica.escena_referencia` — el **id de la escena que Godot debe cargar** — y
   `practica.config` — la **configuración resuelta** (defaults del tipo con lo guardado en
   la práctica encima) para inicializar esa escena.
2. Godot carga la escena identificada por `escena_referencia` y la configura con `config`.
3. El alumno juega. Al terminar, Godot llama a
   `POST /api/game/sessions/{id}/complete` con `calificacion` (0–100) y, opcionalmente,
   `datos_resultado` (un objeto JSON libre con detalle del resultado: tiempos, aciertos,
   errores, etc.).

## Ejemplo: respuesta de `POST /api/game/redeem`

Ejemplo con una práctica de tipo `recolecta` (los valores de `config` combinan defaults del
tipo con lo que coordinación haya guardado para esa práctica en particular):

```json
{
  "access_token": "1|abcdefghijklmnopqrstuvwxyz1234567890",
  "token_type": "Bearer",
  "alumno": {
    "id_alumno": 15,
    "numero_control": "21TI0001",
    "nombre": "Juan Pérez López",
    "semestre": 5,
    "id_grupo": 3
  },
  "practica": {
    "id_practica": 2,
    "id_materia": 1,
    "titulo": "Práctica 1 – Redes LAN virtuales",
    "descripcion": "Configuración de switches y VLANs en entorno virtual",
    "objetivos": "Identificar y segmentar dominios de colisión con VLANs",
    "duracion_estimada": 45,
    "orden": 1,
    "escena_referencia": "recolecta",
    "config": {
      "meta_objetos": 25,
      "tiempo_limite_seg": 120,
      "dificultad": "media"
    },
    "created_at": "2026-06-23T09:00:00.000000Z",
    "updated_at": "2026-07-15T10:00:00.000000Z"
  },
  "evento": {
    "id_evento": 7,
    "fecha_hora_inicio": "2026-06-23T10:00:00+00:00",
    "fecha_hora_fin": "2026-06-23T12:00:00+00:00",
    "estatus": "activo"
  }
}
```

Notas sobre `practica`:

- `escena_referencia` es siempre uno de los ids de tipo registrados en `config/juegos.php`
  (`recolecta`, `ensambla`, `circuito`).
- `config` es el resultado de `Practica::configResuelta()`: toma los defaults del tipo
  (`config/juegos.php.<tipo>.params[].default`) y sobrescribe cada clave con lo guardado en
  `practicas.config`, ignorando cualquier clave ajena al esquema del tipo. Si la práctica no
  tiene `config` guardado, `config` es exactamente los defaults del tipo.
- El mismo objeto `practica` (con `config` resuelto) se recibe también vía el flujo LTI en
  `POST /api/game/lti-redeem`.

## Tipos de mini-juego

### `recolecta` — junta objetos

- **Id de escena** (`escena_referencia`): `recolecta`
- **Descripción**: el alumno junta objetos correctos antes de que acabe el tiempo.

| Parámetro           | Tipo     | Rango / opciones                                  | Default |
|----------------------|----------|----------------------------------------------------|---------|
| `meta_objetos`        | number   | 1 – 200                                             | `10`    |
| `tiempo_limite_seg`   | number   | 10 – 3600                                           | `120`   |
| `dificultad`          | select   | `facil` \| `media` \| `dificil`                     | `media` |

**Calificación esperada**: porcentaje de objetos correctos recolectados respecto a
`meta_objetos`, con un bono por tiempo restante. Rango final 0–100.

### `ensambla` — ordena la secuencia

- **Id de escena** (`escena_referencia`): `ensambla`
- **Descripción**: el alumno ordena piezas/pasos en la secuencia correcta.

| Parámetro           | Tipo     | Rango / opciones | Default |
|-----------------------|----------|-------------------|---------|
| `num_piezas`           | number   | 2 – 50            | `5`     |
| `tiempo_limite_seg`    | number   | 10 – 3600         | `180`   |
| `reintentos`           | checkbox | `true` / `false`  | `true`  |

**Calificación esperada**: porcentaje de piezas en la posición correcta al finalizar. Rango
final 0–100.

### `circuito` — recorre estaciones

- **Id de escena** (`escena_referencia`): `circuito`
- **Descripción**: el alumno visita N estaciones del laboratorio, opcionalmente en orden.

| Parámetro           | Tipo     | Rango / opciones | Default |
|-----------------------|----------|-------------------|---------|
| `num_estaciones`       | number   | 1 – 50            | `4`     |
| `en_orden`             | checkbox | `true` / `false`  | `false` |
| `tiempo_limite_seg`    | number   | 10 – 3600         | `300`   |

**Calificación esperada**: porcentaje de estaciones completadas correctamente. Rango final
0–100.

## Agregar un tipo nuevo

Agregar un tipo de mini-juego nuevo requiere:

1. Una entrada nueva en [`config/juegos.php`](../../config/juegos.php) (id, `label` y
   `params` con tipo, default y rango/opciones de cada parámetro). Esa entrada alimenta
   automáticamente el formulario del panel, la validación del servidor y este contrato.
2. La escena correspondiente en Godot, identificada por el mismo id usado como clave en el
   registro (el valor que llegará en `escena_referencia`).

No se requiere ningún otro cambio de código en el backend: el registro es la fuente única de
verdad.
