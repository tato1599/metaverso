---
name: Metaverso · Campus de Prácticas
description: El panel de instrumento de un laboratorio virtual — placa de luz fría, tinta navy institucional y todo dato duro en mono tabular.
colors:
  paper: "#EEF2F9"
  paper-deep: "#DDE6F3"
  glass: "rgba(255,255,255,0.62)"
  glass-2: "rgba(255,255,255,0.40)"
  ink: "#0E1F3A"
  ink-2: "#2B4470"
  ink-3: "#45608F"
  signal: "#1A3A8F"
  signal-deep: "#132B6B"
  live: "#6BA6E8"
  alerta: "#B3123B"
  confirmado: "#0E6B4F"
  espera: "#8A5A00"
  rule: "rgba(14,31,58,0.14)"
  rule-soft: "rgba(14,31,58,0.075)"
  rule-hair: "rgba(14,31,58,0.045)"
typography:
  display:
    fontFamily: "'Libre Franklin Variable', 'Libre Franklin', 'Helvetica Neue', Helvetica, Arial, sans-serif"
    fontSize: "clamp(2.25rem, 5.2vw, 4rem)"
    fontWeight: 800
    lineHeight: 0.98
    letterSpacing: "-0.035em"
  marca:
    fontFamily: "'Libre Franklin Variable', 'Libre Franklin', 'Helvetica Neue', Helvetica, Arial, sans-serif"
    fontSize: "17px"
    fontWeight: 800
    lineHeight: 1
    letterSpacing: "-0.03em"
  headline:
    fontFamily: "'Libre Franklin Variable', 'Libre Franklin', 'Helvetica Neue', Helvetica, Arial, sans-serif"
    fontSize: "clamp(1.375rem, 2.4vw, 1.75rem)"
    fontWeight: 700
    lineHeight: 1.12
    letterSpacing: "-0.022em"
  title:
    fontFamily: "'Public Sans Variable', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 600
    lineHeight: 1.35
    letterSpacing: "-0.006em"
  body:
    fontFamily: "'Public Sans Variable', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: "-0.004em"
  label:
    fontFamily: "'JetBrains Mono Variable', ui-monospace, SFMono-Regular, monospace"
    fontSize: "0.6875rem"
    fontWeight: 500
    lineHeight: 1
    letterSpacing: "0.16em"
  dato:
    fontFamily: "'JetBrains Mono Variable', ui-monospace, SFMono-Regular, monospace"
    fontSize: "0.8125rem"
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: "0"
    fontFeature: "'tnum' 1, 'zero' 1"
rounded:
  canto: "2px"
  ctl: "4px"
  lamina: "6px"
  pill: "999px"
spacing:
  xs: "6px"
  sm: "10px"
  md: "20px"
  lg: "36px"
  xl: "64px"
components:
  button-primary:
    backgroundColor: "{colors.signal}"
    textColor: "#FFFFFF"
    typography: "{typography.title}"
    rounded: "{rounded.ctl}"
    padding: "0 20px"
    height: "42px"
  button-primary-hover:
    backgroundColor: "{colors.signal-deep}"
    textColor: "#FFFFFF"
  button-secondary:
    backgroundColor: "{colors.glass}"
    textColor: "{colors.ink}"
    typography: "{typography.title}"
    rounded: "{rounded.ctl}"
    padding: "0 18px"
    height: "42px"
  input-field:
    backgroundColor: "{colors.glass-2}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.ctl}"
    padding: "0 12px"
    height: "42px"
  lamina:
    backgroundColor: "{colors.glass}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lamina}"
    padding: "{spacing.md}"
  chip-estado:
    backgroundColor: "transparent"
    textColor: "{colors.ink-2}"
    typography: "{typography.label}"
    rounded: "{rounded.canto}"
    padding: "3px 7px"
---

# Design System: Metaverso · Campus de Prácticas

## Overview

**Creative North Star: "El Instrumento"**

La página no es un documento con controles encima: **es el panel de un instrumento de laboratorio**. Láminas de vidrio a distinta profundidad flotando sobre una placa de luz fría, con una sola lámpara rasante que recorre la composición. Todo lo que el producto administra —horas, cupos, matrículas, calificaciones, telemetría de sesión— es una **lectura**, y una lectura se presenta con la precisión de un aparato de medición: en mono tabular, alineada a la derecha, junto a su rango o su tope.

El registro es **claro y frío**, no por defecto de categoría sino por la escena real de uso: un laboratorio de cómputo de una escuela pública mexicana, de día, bajo fluorescente y luz de ventana, en equipos modestos. Un panel oscuro en esa sala se convierte en un espejo. La luz aquí es ambiente: los objetos la reciben, la transmiten o la bloquean; **nada emite**.

La tinta es el **navy institucional del TecNM** y el azul de acción es su azul corporativo. La institución no aparece como un logo pegado en una esquina: aparece porque es el color con el que está escrito todo. El rojo del Tec no decora — es el estado de escasez y de error, que es donde un instrumento usa rojo.

**Key Characteristics:**
- Dos tonos de papel frío + tinta navy + un azul de acción. Nada más compite.
- Retícula grabada de 96 px, casi invisible, como faceplate de aparato.
- Reglas capilares en lugar de bordes de tarjeta; la estructura la da la línea, no la caja.
- Todo dato duro en mono tabular con `tnum`.
- Una sola lámpara. Una sola entrada orquestada.

## Colors

Paleta de dos tonos fríos, una tinta y un emisivo, con tres estados tomados de la señalización de instrumento.

### Primary
- **Azul TecNM** (`#1A3A8F`): el azul corporativo de la institución. Es la **acción y el foco**: botón primario, anillo de foco, enlace activo, subrayado del elemento de navegación en curso. Nunca es fondo de página ni relleno decorativo.
- **Azul TecNM Profundo** (`#132B6B`): únicamente el estado `hover`/`active` del anterior.

### Secondary
- **Azul de Señal Viva** (`#6BA6E8`): el emisivo. Trazo de la lámina activa, relleno de una barra de cupo, halo de una sesión en curso, punto de "en vivo". Mide **2.27:1 sobre papel** — reprobado como texto, y esa es exactamente su regla.

### Tertiary — estados de instrumento
- **Rojo TecNM** (`#B3123B`): sin cupo, error de validación, sesión fallida. 6.10:1 sobre papel.
- **Verde Confirmado** (`#0E6B4F`): reserva confirmada, sesión completada, calificación entregada. 5.78:1 sobre papel.
- **Ámbar de Espera** (`#8A5A00`): por comenzar, pendiente de calificar, cupo al límite. 5.28:1 sobre papel.

### Neutral
- **Papel de Placa** (`#EEF2F9`): el fondo de todo. Blanco azulado frío, nunca blanco puro ni crema.
- **Papel Profundo** (`#DDE6F3`): el pozo — campos en reposo, cabecera de tabla, franja inferior de la placa.
- **Vidrio** (`rgba(255,255,255,0.62)`) y **Vidrio Tenue** (`rgba(255,255,255,0.40)`): las láminas. Es blanco translúcido sobre la placa, no una tarjeta blanca sólida.
- **Tinta** (`#0E1F3A`): texto principal y títulos. 14.66:1.
- **Tinta 2** (`#2B4470`): texto secundario, etiquetas de campo. 8.65:1.
- **Tinta 3** (`#45608F`): metadatos, texto de ayuda, placeholder. 5.63:1 — el piso, medido, no supuesto.
- **Reglas** (`rgba(14,31,58,.14)` / `.075` / `.045`): capilar, suave y de retícula. Tres pesos, ninguno más grueso de 1 px.

### Named Rules
**La Regla del Emisivo.** `#6BA6E8` no toca texto sobre fondo claro jamás. Trazos, rellenos, halos y puntos de estado. Si necesitas escribir sobre él, el texto va en tinta, no en blanco.

**La Regla de la Institución.** El azul y el rojo del TecNM no son decoración de marca: el azul es acción, el rojo es escasez. Si un rojo no significa "algo se salió del rango", no es este rojo.

**La Regla de los Dos Tonos.** Una composición tiene papel, papel profundo y vidrio. Cualquier cuarto tono de fondo es una excepción que hay que justificar por escrito.

## Typography

**Display Font:** Libre Franklin Variable (con Helvetica Neue, Arial)
**Body Font:** Public Sans Variable (con `ui-sans-serif`, `system-ui`)
**Label/Mono Font:** JetBrains Mono Variable (con `ui-monospace`, SFMono-Regular)

**Character:** Dos grotescas de la misma familia histórica —Franklin Gothic— en dos registros: Libre Franklin a peso 800 con tracking cerrado es la **placa de identificación** del aparato, ancha y rotunda; Public Sans es el texto de servicio, neutro y legible a 15 px en una pantalla de laboratorio. La mono no es disfraz de "técnico": aparece **solo** donde hay medición.

### Hierarchy
- **Display** (800, `clamp(2.25rem, 5.2vw, 4rem)`, 0.98, `-0.035em`): un solo titular por pantalla. La frase que dice qué es esto.
- **Marca** (800, 17px, 1, `-0.03em`): el logotipo "Metaverso" junto a las tres barras institucionales. Tamaño fijo, no escala con la pantalla — es una placa de identificación, no un título.
- **Headline** (700, `clamp(1.375rem, 2.4vw, 1.75rem)`, 1.12, `-0.022em`): título de sección o de página interior.
- **Title** (600, 15px, 1.35): título de lámina, nombre de grupo, encabezado de fila.
- **Body** (400, 15px, 1.6, medida 62–70ch): prosa. Rara: este producto casi no tiene prosa.
- **Label** (mono 500, 11px, `0.16em`, mayúsculas): rótulo de campo, encabezado de columna, nombre de estado.
- **Dato** (mono 500, 13px, `tnum` + `zero`): hora, cupo, matrícula, clave, calificación. **Siempre alineado a la derecha en tabla.**

### Named Rules
**La Regla de la Medición.** La mono se usa para código, datos y medición. Un texto en mono que no sea ninguna de las tres es un disfraz — reescríbelo en Public Sans.

**La Regla del Rótulo Único.** El rótulo mono en mayúsculas es un componente con significado (nombre de campo, nombre de columna, nombre de estado). No es un *eyebrow* decorativo encima de cada sección.

## Layout

Cascarón de `1288px` máximo con `padding-inline: clamp(20px, 4vw, 48px)`. Retícula modular de **96 px** grabada en el fondo con `rule-hair`, enmascarada radialmente para que se desvanezca en los bordes — el faceplate del aparato, visible solo si lo buscas.

El ritmo corre sobre la escala de Tailwind, no sobre una propia: `6 / 10 / 20 / 36 / 64` (`1.5 / 2.5 / 5 / 9 / 16`). Más espacio encima de un encabezado que debajo.

**La composición la decide el modo de la pantalla, no el sistema.** Una pantalla de tarea (acceso, un formulario, un diálogo) es **una sola columna centrada** de `26rem` como máximo, con la placa y su vacío alrededor: nada compite con la tarea, y una vitrina de producto al lado convierte un login en una portada. Una pantalla de operación (agenda, grupos, resultados) sí reparte en varias columnas y sube la densidad.

La medida de lectura de este producto es corta —`34–46ch`— porque casi no hay prosa: son rótulos, datos y frases de una línea. Reservar `62–75ch` sólo si algún día aparece un texto largo de verdad.

## Elevation & Depth

**Híbrido tonal + sombra direccional, nunca halo.** La profundidad viene de que las láminas son **vidrio translúcido sobre una placa iluminada**: se distinguen por transmisión y por el canto, no por una sombra suave multicapa.

Cada lámina tiene un **canto** superior de 9 px: una banda con un degradado especular que se desplaza según la posición de la lámina. Eso es lo que da el volumen, no el `box-shadow`.

### Shadow Vocabulary
- **Lámina en reposo** (seis capas): dos de proyección —`0 26px 52px -30px rgba(14,31,58,.34)` y `0 6px 14px -8px rgba(14,31,58,.16)`— y cuatro `inset` que dibujan los filos superior y laterales del vidrio. Las seis van juntas: quitar los `inset` deja una tarjeta, no una lámina.
- **Control presionado**: sin sombra, `translateY(1px)`.

No existe una sombra de *hover* para la lámina, y es deliberado: lo que responde aquí es el canto (`--ig`), no una sombra que crece.

### Named Rules
**La Regla de la Lámpara Única.** Una sola fuente de luz por composición, rasante y fija en el encuadre. Todo lo demás cede. Dos gradientes de luz compitiendo en la misma pantalla es un error, no una capa extra.

**La Regla del Halo Prohibido.** Una sombra sin desplazamiento y de color es decoración. Toda sombra lleva `offset` y `blur`.

## Shapes

Radios casi rectos: `2px` para cantos y chips, `4px` para controles y campos, `6px` para láminas. La **píldora (`999px`) se reserva a navegación y a CTA de portada**; el resto de la interfaz es de esquina casi recta.

Los separadores son reglas capilares de 1 px, nunca cajas. Una lista de eventos es una pila de filas separadas por `rule-soft`, no una colección de tarjetas.

## Components

### Buttons
- **Shape:** casi recto (`4px`), altura `42px`.
- **Primary:** fondo `#1A3A8F`, texto blanco (10.27:1), `padding: 0 20px`, peso 600, altura `42px` (la misma que el campo, para que columna de formulario y botón compartan módulo).
- **Hover / Focus:** fondo a `#132B6B` en `140ms`; foco con `outline: 2px solid #1A3A8F; outline-offset: 3px`.
- **Secondary:** lámina de vidrio con regla capilar de 1 px en `rule`; texto en tinta.
- **Ghost:** solo texto en `ink-2`, gana fondo `glass-2` al hover.
- **Disabled:** `opacity: .45`, sin puntero.
- **Ocupado ≠ deshabilitado.** Un botón enviando conserva su color pleno, cambia la etiqueta, marca `aria-busy` y toma `cursor-progress`. Atenuar el primario justo en el instante en que el instrumento responde lo deja en 2.42:1 y apaga la única respuesta de la pantalla.

### Cards / Containers — la Lámina
- **Corner Style:** `6px`.
- **Background:** `glass` con `backdrop-filter: blur(10px)`.
- **Border:** regla capilar de 1 px en `rule-soft`; **nunca** un borde de color grueso a la izquierda.
- **Canto:** banda superior de 9 px con el reflejo especular. Es la firma del sistema.
- **Shadow:** ver *Lámina en reposo*.
- **Padding:** `24px`.

### Inputs / Fields
- **Style:** fondo `glass-2`, regla de 1 px en `rule`, radio `4px`, altura `42px`, texto 15px en tinta.
- **Focus:** el fondo sube a blanco `.78`, la regla pasa a `signal` a 2 px, y el canto de la lámina que lo contiene se enciende.
- **Error:** regla `inset` de 1.5px en `alerta` vía `aria-invalid`, con el mensaje en un **resumen a nivel de formulario** dentro de una región `aria-live="polite"` — no debajo de cada campo, porque el backend devuelve un solo error de credenciales, no uno por campo. Cada campo inválido apunta al resumen con `aria-describedby`, y tras un fallo el foco vuelve al primer campo.
- **Placeholder:** `ink-3` (5.63:1), y siempre un ejemplo real del producto, no una repetición de la etiqueta.

### La frontera entre dato y control

En una barra donde conviven ambos, lo que se acciona se distingue por **tres** señales a la vez, no por una:

| | Información | Control |
|---|---|---|
| Color | tinta / tinta-3 | **azul de acción** (`signal`) |
| Contorno | ninguno | regla capilar de 1 px |
| Cursor | `auto` | `pointer` |

El caso que lo motivó: nombre de usuario, rol y "Salir" compartían rótulo mono y el mismo gris. Nadie podía saber cuál se pulsaba.

**Gotcha de Tailwind v4:** su Preflight dejó de poner `cursor: pointer` en `<button>` (sigue al agente de usuario). Sin la regla base de `app.css`, un `<button>` no avisa al pasar por encima y un `<a>` sí — dos afordancias distintas para la misma acción. Los deshabilitados quedan fuera: ahí el cursor tiene que decir que no.

### Navigation
- Rótulos en Public Sans 600 a 13px, no en mayúsculas.
- El elemento en curso se marca con una **regla de 2 px en `signal` bajo la línea base**, no con una píldora de fondo.
- Hover: fondo `glass-2`. Foco: `outline` de 2 px en `signal`.
- Móvil: la barra se convierte en una fila desplazable horizontalmente, sin menú hamburguesa hasta que haya más de seis destinos.

### Tables — la Lectura
- Cabecera: rótulo mono en mayúsculas a 11px en `ink-2` sobre `paper-deep`.
- Filas de ~68 px (dos líneas) separadas por `rule-soft`; sin cebra.
- A la derecha van las **cantidades** (cupo, calificación, conteos), en mono con `tnum`. Un identificador —matrícula, clave, folio— es mono pero se alinea a la izquierda: no se suma.
- Estado: chip de canto recto con punto de 6 px del color de estado + rótulo mono. Sin fondo tintado.

### Signature Component — la Lámina y su Canto

La lámina son **tres piezas apiladas**, no una caja con borde:

| Pieza | z | Qué es |
|---|---|---|
| `derrame` | 0 | La luz que la lámina deja caer sobre la placa. Elipse desenfocada 15 px bajo el borde inferior. |
| `canto` | 1 | El **grosor real del vidrio**, asomando 6 px por debajo de la cara. |
| `lamina-cara` | 2 | El vidrio: `backdrop-filter: blur(15px) saturate(1.32) brightness(1.035)` y las seis capas de sombra. |

El canto es la firma. Va **abajo**, no arriba, y su degradado es **vertical** —blanco arriba, azul institucional en el filo— porque eso es lo que se ve cuando miras un canto de vidrio de frente. Una banda de color arriba sería un borde decorativo; esto es un bisel. Lleva un `::before` con el reflejo especular que se desplaza según `--sx` y un `::after` de refracción en el emisivo.

Tres variables lo gobiernan, y las escribe el **observador de la lámpara** (`GuestLayout`), no el componente:

- `--ig` (0…1): cuánto la enciende el haz, según la distancia de la lámina a la línea de luz (42 % del alto de la ventana).
- `--sx` (0…1): dónde cae el reflejo. Se calcula con la posición **vertical y horizontal**: la lámpara es rasante, así que dos láminas a la misma altura pero en columnas distintas no reciben la luz en el mismo punto. Sin el término horizontal dejan de leerse como dos objetos.
- `--py`: paralaje por `data-depth`. Profundidades distintas se mueven distinto; eso es lo que las separa.

**Las láminas de una rejilla llevan `depth: 0`.** El paralaje se calcula desde el centro de cada lámina, así que dos hermanas de una fila con alturas distintas se desplazan distinto **aunque compartan `depth`**, y el resultado se lee como desalineación, no como profundidad. El paralaje solo tiene sentido entre láminas que el lector ve **apiladas en el eje del scroll**; en una fila, la diferencia la dan el canto y el derrame.

La entrada escalonada y el paralaje viven en **envoltorios distintos** a propósito: la animación termina en `transform: none` y su relleno pisaría el paralaje para siempre si compartieran elemento.

Es la única animación de marca del sistema y sustituye a cualquier brillo, borde animado o gradiente en movimiento. Bajo `prefers-reduced-motion` el observador no se engancha y limpia las variables: las láminas quedan en reposo y se leen igual.

### Otros componentes del mundo

- **`grabado`** — `text-shadow: 0 1px 0 rgba(255,255,255,.78)`. Todo lo que se apoya directamente en la placa (titular, rótulos, marca) lleva el filo de luz en el canto de la letra. Lo que va dentro de una lámina, no.
- **`barrido`** — el reflejo especular que cruza el titular **una sola vez** al cargar (3.6 s, `background-clip: text`). Es el momento de encendido de la página. `display: none` bajo `reduce`.
- **`filete`** — la regla del pie con ticks de 5 px y uno de 9 px cada cuatro. Cierra la página como la escala de un aparato.
- **Cáusticas** — dos capas de `feTurbulence` SVG sobre la placa, con deriva de 74 s y 103 s. Cero peticiones de red. Es lo que hace que la placa parezca iluminada y no pintada.

## Do's and Don'ts

### Do:
- **Do** presentar toda hora, cupo, matrícula, clave y calificación en JetBrains Mono con `font-variant-numeric: tabular-nums`, alineada a la derecha en tabla.
- **Do** medir el contraste antes de comprometer un color; la paleta de este archivo trae su ratio calculado.
- **Do** separar con reglas capilares de 1 px. La estructura es la línea.
- **Do** dar a cada lámina su canto especular; es lo que hace que esto sea un instrumento y no un formulario.
- **Do** orquestar la entrada: las piezas llegan escalonadas y en un orden que significa algo (primero la placa, luego la lámina, luego el dato, luego el rótulo).
- **Do** descontar `--entra-viaje` (14px) del alto del cascarón en cualquier pantalla de alto fijo. Un `transform` no ocupa sitio en el layout **pero sí genera área de scroll**: sin ese descuento, el último elemento en flujo asoma bajo el borde durante la animación y saca una barra de scroll en una página que cabe entera. El valor vive en un solo sitio y lo usan el keyframe y el cascarón.
- **Do** respetar `prefers-reduced-motion` desactivando la deriva de la luz y los escalonamientos, dejando todo visible.

### Don't:
- **Don't** vaciar la estructura de un calendario cuando la semana no trae eventos: los días existen igual. El hueco se dice DENTRO de la rejilla, no sustituyéndola. Un `EmptyState` que borra el calendario deja al usuario sin saber siquiera dónde está.
- **Don't** navegar entre semanas con `preserveState: false`: remonta la página entera y vuelve a disparar toda la entrada escalonada. Recarga parcial (`only`) + `key` en la rejilla: se mueve lo que cambia, y en la dirección en que cambia.
- **Don't** construir nombres de clase en tiempo de ejecución (`` `[&_td:nth-child(${i})]:text-right` ``). Tailwind solo genera las clases que ve escritas literalmente en el código: una clase compuesta con plantillas se ve bien en el JSX y **no existe** en el CSS. Si una columna se alinea, se escribe alineada.
- **Don't** usar `#6BA6E8` como color de texto sobre fondo claro. Reprueba a 2.27:1.
- **Don't** construir la estructura con tarjetas blancas de sombra suave y del mismo tamaño. Es exactamente lo que este sistema reemplaza.
- **Don't** poner un rótulo mono en mayúsculas encima de cada sección como *eyebrow*. El rótulo nombra un campo, una columna o un estado.
- **Don't** usar texto con degradado, ni numeración de secciones `01 / 02 / 03`, ni anillos de progreso o *sparklines* como sustituto de contenido.
- **Don't** encender dos fuentes de luz en la misma composición.
- **Don't** ofrecer en la interfaz una puerta que el backend no abre: no hay "recordarme", no hay recuperación de contraseña, no hay registro, no hay SSO.
- **Don't** nombrar el motor del juego en la interfaz. El modelo es agnóstico a propósito.
