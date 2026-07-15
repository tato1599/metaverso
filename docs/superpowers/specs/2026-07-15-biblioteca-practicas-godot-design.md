# Biblioteca de prácticas configurables conectada a Godot — Diseño

Fecha: 2026-07-15

## Problema

Una práctica ya se conecta con el juego: al canjear el token (`POST /api/game/redeem`)
la API devuelve la práctica completa, incluida `escena_referencia`. Pero hoy
`escena_referencia` es texto libre, sin validación ni parámetros, y cada práctica
nueva implicaría contenido nuevo en el juego. Queremos que un maestro/coordinador
arme muchas prácticas desde el panel, sin tocar Godot, apuntando a **escenas
genéricas configurables por parámetros**.

## Alcance

Tres tipos de mini-juego genéricos, un formulario por tipo en el panel, la config
viajando a Godot en el redeem, validación en servidor, y un documento de contrato
para el equipo de Godot. La autoría de prácticas sigue siendo de Coordinación/Admin
(como hoy `/admin/practicas`); se puede extender a maestros más adelante.

Fuera de alcance: construir las escenas en Godot (las hace el equipo de juego contra
el contrato), bancos de contenido (preguntas/algoritmos específicos), y editor de
mini-juegos.

## Tipos de mini-juego (v1)

Los tres devuelven calificación 0–100 y `datos_resultado` con el detalle.

1. **`recolecta`** — juntar objetos correctos antes de que acabe el tiempo.
   - `meta_objetos` (int ≥ 1): cuántos juntar.
   - `tiempo_limite_seg` (int ≥ 1).
   - `dificultad` (enum: `facil` | `media` | `dificil`).
   - Calificación: % de objetos correctos, con bono por tiempo restante.

2. **`ensambla`** — ordenar piezas/pasos en la secuencia correcta.
   - `num_piezas` (int ≥ 2).
   - `tiempo_limite_seg` (int ≥ 1).
   - `reintentos` (bool).
   - Calificación: % de piezas en la posición correcta.

3. **`circuito`** — visitar N estaciones del laboratorio, opcionalmente en orden.
   - `num_estaciones` (int ≥ 1).
   - `en_orden` (bool).
   - `tiempo_limite_seg` (int ≥ 1).
   - Calificación: % de estaciones completadas correctamente.

## Arquitectura

### 1. Registro de tipos (fuente única de verdad)

`config/juegos.php` define los tres tipos. Cada uno: `id` (= `escena_referencia` que
Godot carga), `label`, y `params` (lista de campos con `name`, `tipo`,
`label`, `default`, `min`/`max` u `opciones`, `requerido`). Este registro alimenta
el formulario del panel, la validación del servidor y el documento de contrato.
Agregar un tipo = una entrada aquí + la escena correspondiente en Godot.

### 2. Modelo de datos

- `escena_referencia` se reutiliza como **id del tipo**, validado contra el registro
  (ya no texto libre).
- Nueva columna **`practicas.config` (jsonb, nullable)** con los valores de los
  parámetros, p. ej. `{"meta_objetos":10,"tiempo_limite_seg":120,"dificultad":"media"}`.
- El modelo `Practica` castea `config` a array.
- Migración aditiva; las prácticas existentes quedan con `config` null (el juego usa
  defaults del registro si falta un parámetro).

### 3. Panel — formulario por tipo

Página **dedicada** de Prácticas en React/Inertia (el `Recurso.jsx` genérico no
soporta campos dinámicos por tipo). Un selector "Tipo de práctica"
(recolecta/ensambla/circuito); al elegirlo, el formulario renderiza los campos de
ese tipo desde el registro, con validación. El maestro nunca escribe JSON.
`PracticaController` pasa el registro a la vista y valida la `config` contra el
esquema del tipo al guardar (store/update).

### 4. Contrato con Godot (API + doc)

- `POST /api/game/redeem` incluye en la práctica `escena_referencia` (el tipo) y
  `config`.
- Flujo Godot: redeem → carga la escena del tipo → la configura con `config` → juega
  → `POST /api/game/sessions/{id}/complete` con `calificacion` (0–100) +
  `datos_resultado`.
- Documento `docs/api/tipos-de-juego.md` con los tres tipos, sus parámetros exactos
  y la regla de calificación esperada, para que Godot implemente contra un contrato
  fijo.

### 5. Validación y errores

El servidor valida al guardar: `escena_referencia` es un tipo conocido y cada
parámetro de `config` respeta su tipo y rango (p. ej. `meta_objetos ≥ 1`, `dificultad`
en el enum). Un valor inválido se rechaza con mensaje claro en el formulario; nunca
llega roto a Godot. Godot, ante un tipo desconocido, muestra un error amable en vez
de una escena vacía.

### 6. Modo demo (probar sin Godot)

Se extiende la pantalla de modo demo para mostrar la `escena_referencia` y la `config`
que recibiría Godot y permitir enviar una calificación de prueba. Valida el pipeline
completo antes de que exista una escena en Godot.

## Pruebas

Pest, contra la base real:

- El registro rechaza una práctica con `escena_referencia` de tipo desconocido.
- Rechaza `config` con un parámetro fuera de rango o de tipo equivocado.
- Acepta y persiste una `config` válida; el formulario la recarga bien.
- `redeem` incluye `escena_referencia` y `config` en la respuesta.
- Una práctica sin `config` (heredada) no rompe el redeem.
