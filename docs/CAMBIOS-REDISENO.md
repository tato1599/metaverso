# Rediseño de las vistas + reserva obligatoria

Guía para quien retoma el proyecto después del commit `6f326b8` (73 archivos,
+5887/−1220). Cubre **qué cambió**, **cómo correrlo** y **cómo trabajar encima**.

Documentos hermanos, que este no repite:

| Documento | Qué contiene |
|---|---|
| `PRODUCT.md` | Qué es el producto, quiénes lo usan y **qué NO hace** el backend |
| `DESIGN.md` | El sistema de diseño completo: paleta medida, tipografía, componentes |
| `docs/LTI-CONFIGURAR-MOODLE.md` | Configurar Moodle paso a paso |
| `docs/COMO-FUNCIONA.md` | El flujo explicado sin tecnicismos (para maestros y sistemas) |

---

## 1. Resumen en 30 segundos

Antes había **dos mundos visuales** conviviendo (el panel del maestro y la
portada no se parecían al resto) y ninguno decía a qué se parecía el producto.
Ahora hay uno solo —**"El Instrumento"**— y las 14+ vistas de Inertia están
reescritas sobre él.

Y cambiaron tres cosas de **comportamiento**, no solo de forma:

1. **La reserva es obligatoria** en el camino de la agenda. El alumno no entra
   al juego sin haber apartado horario.
2. **El launch LTI ya no aterriza en un interstitial**: inicia sesión, inscribe
   al alumno por el contexto del curso y lo manda a la vista de la práctica.
3. **Una reserva por práctica**, no por evento — antes se podía reservar dos
   veces la misma práctica desde "otras fechas".

---

## 2. Cómo correrlo

```bash
composer install
npm install                  # ← no lo saltes: las fuentes viven en node_modules
cp .env.example .env         # si aún no lo tienes
php artisan key:generate
php artisan migrate:fresh --seed
npm run build                # o `npm run dev` mientras desarrollas
php artisan serve
```

> **Si la pantalla se ve rota o sin estilos**, casi siempre es una de dos:
> no corriste `npm install` (faltan las fuentes) o `public/build/manifest.json`
> quedó viejo (`npm run build`). Nos pasó las dos veces.

### Cuentas de demo

Contraseña `password` para todas, **solo en `local` y `testing`** — en cualquier
otro entorno el seeder genera una aleatoria de 16 caracteres a propósito
(`DemoSeeder.php:27`), para que una instancia pública no quede logueable con
credenciales conocidas.

| Correo | Rol |
|---|---|
| `admin@tecnm.mx` | Admin |
| `coordinacion@tecnm.mx` | Coordinador |
| `maestro@tecnm.mx` | Maestro (Laura Gómez, grupo 3A) |
| `ana@tecnm.mx`, `beto@tecnm.mx`, `caro@tecnm.mx` | Alumnos |

### Datos de demo

`DemoAgendaSeeder` enriquece la base hasta que las pantallas se vean **con
varios registros** (un segundo grupo, varias prácticas, agenda repartida entre
semanas y reservas de distintos alumnos para que los cupos no salgan vacíos).

Es **idempotente** a propósito: se puede correr sobre una base ya sembrada sin
borrarla, que es justo lo que hace falta cuando tu instancia local ya tiene
usuarios y contextos creados por launches reales de Moodle.

```bash
php artisan db:seed --class=DemoAgendaSeeder   # sin tocar lo que ya existe
```

Las fechas son relativas a `now()`, así que la demo nunca queda en el pasado.

### Tests

```bash
php artisan test --compact                      # 261 pasan, 1 skipped
php artisan test --compact --filter=Reservar     # filtra por archivo o nombre
vendor/bin/pint --dirty                          # formato PHP antes de commitear
```

---

## 3. El sistema de diseño: cómo usarlo

El mundo se llama **"El Instrumento"**: placa fría de metal claro, láminas de
vidrio con canto real, **una sola** lámpara rasante, datos en mono tabular.
`DESIGN.md` lo explica entero; aquí va lo mínimo para escribir una pantalla
nueva sin romperlo.

### La regla que gobierna todo

> **Los datos se leen; los controles se tocan.** Si algo es información, no le
> pongas la forma de un botón. Si algo es un control, tiene que verse
> *pulsable* — borde, cursor y estado de foco.

Esto salió de un bug real: en el encabezado, el rol del usuario ("ALUMNO") se
veía idéntico al botón "Salir". Al arreglarlo descubrimos que **Tailwind v4
quita `cursor: pointer` de los `<button>`** en su Preflight, así que los 7
botones de la app se sentían muertos. Está corregido en la capa base de
`resources/css/app.css`; no lo vuelvas a poner botón por botón.

### Componentes

Todos en `resources/js/Components/`. Revisa si ya existe uno antes de escribir
otro.

```jsx
// El contenedor del sistema. Sustituye a la tarjeta blanca con sombra.
<Lamina depth={0} retardo={120}>…</Lamina>
```

| Componente | Props | Para qué |
|---|---|---|
| `Lamina` | `depth`, `retardo`, `className` | El contenedor de todo |
| `Encabezado` | `volver`, `rotulo`, `titulo`, `meta`, `acciones` | Cabecera de vista de alumno/maestro |
| `CabeceraAdmin` | `titulo`, `busqueda`, `setBusqueda`, `onAgregar`, `contador` | Cabecera de las pantallas de admin (+ `claseAccion` exportada) |
| `Table` | `head`, `derechas`, `suelta` | Tablas de datos |
| `Badge` | `tone` (`muted`/`ok`/`danger`/`warn`) | Estados |
| `Button` | `variant` (`primary`/`secondary`/`danger`/`ghost`) | Controles, altura 42px |
| `FormField` | `label`, `name`, `error`, `pista` | Campo de formulario (+ `TextInput`, `Select`, `Textarea`) |
| `Modal` | `open`, `onClose`, `title` | Diálogos |
| `EmptyState` | `title`, `hint`, `action` | Vacíos con salida |
| `WeekCalendar` | `semana`, `eventos`, `renderEvento` | Rejilla semanal |
| `PasoSemana` | `semana`, `limites`, `irASemana` | Navegación ‹ Hoy › con topes |
| `CupoPuntos` | `ocupados`, `cupo` | Cupo como puntos, no como "3/5" |
| `Placa.jsx` | `Placa`, `Franja`, `Marca`, `useLamparaRasante` | El fondo y la lámpara |

Un botón **enviando** lleva `aria-busy`, no `disabled`: conserva su color pleno
y solo cambia la etiqueta. Atenuarlo justo en el instante en que el instrumento
responde lo dejaba en 2.42:1 y apagaba la única respuesta de la pantalla.

Fechas y plurales: `resources/js/fechas.js` → `sumarDias`, `diaLargo`,
`rangoSemana`, `plural`. El español no tolera "1 grupos", por eso existe
`plural(n, 'grupo')`.

### Las tres trampas que ya nos costaron tiempo

**1. Tailwind v4 no genera clases construidas en runtime.**

```jsx
className={`text-${tono}`}        // ❌ nunca se genera, la clase no existe
className={TONOS[tono]}           // ✅ mapa con las clases escritas enteras
```

Esto rompió el prop `numericas` de `Table` (no hacía *nada*, silenciosamente) y
lo volví a hacer yo mismo en `Cursos.jsx` después de documentarlo. Si una clase
"no aplica" y no entiendes por qué, empieza por aquí.

**2. `depth: 0` en láminas hermanas de una misma rejilla.**

El paralaje de cada lámina se calcula desde **su propio centro**, así que dos
láminas con el mismo `depth` pero distinta altura igual se mueven distinto y se
ven desalineadas. En una rejilla, todas a `depth={0}`.

**3. Un `transform` crea scroll aunque no ocupe layout.**

La animación de entrada desplaza 14px hacia arriba. En una página de exactamente
`100dvh` eso generaba un scroll mínimo de 14px durante 16 frames en el login —
lo que reportaste como "hay un scroll pero es algo muy mínimo". La solución es
el token `--entra-viaje: 14px`, **reservado en el `min-height` del shell**. Si
creas otra pantalla a pantalla completa, reserva ese viaje igual.

---

## 4. Lo que cambió de comportamiento

### 4.1 La reserva es obligatoria

Antes el alumno podía llegar al juego sin reservar. Ahora **una misma vista**
—`Mi/Evento.jsx`— cambia según el estado del alumno frente a esa práctica: si no
ha reservado muestra el bloque de reserva; si ya reservó muestra el acceso al
juego; si pasó, muestra qué ocurrió.

Ese estado **se decide en un solo lugar**, `app/Services/EstadoEventoAlumno.php`,
no en cada pantalla. El calendario semanal y la vista de la práctica muestran lo
mismo con distinto detalle, así que las reglas (puede reservar, está lleno,
puede cancelar, puede jugar) viven ahí y la UI **recibe booleanos ya decididos**.

> ⚠️ El orden de la cadena de estados importa: `finalizado` tiene que evaluarse
> **antes** que `mi_reserva`, o un evento ya pasado saluda con "Te esperamos".
> Ese bug apareció dos veces en sitios distintos.

### 4.2 Una reserva por práctica

El guard bloqueaba duplicados **en el mismo evento**, aunque su mensaje dijera
"en esta práctica". Como el maestro agenda la misma práctica varias veces
(fechas alternativas), desde "otras fechas" se podía reservar dos veces lo
mismo.

Ahora, en `Mi/ReservaController`:

- El guard es **por práctica**, sobre los eventos no terminados.
- Hay `lockForUpdate` con orden de lock consistente (**alumno → evento**) para
  serializar reservas concurrentes.
- Cambiar de fecha usa `cambiar_de`, un movimiento **atómico**: hay un test que
  prueba que si el horario nuevo se llena, la reserva original sobrevive.

### 4.3 El launch LTI

`Lti/LtiLaunchController::launch()` hace, en este orden:

1. `capturarContexto()` — upsert del curso de Moodle (atómico; dos launches
   concurrentes del mismo curso no truenan).
2. **`guardarVinculoAgs()`** — ver abajo, es el paso crítico.
3. `Auth::login()` + `session()->regenerate()` — el alumno ya navega la app, no
   un interstitial, así que necesita sesión web propia.
4. `grupoDelContexto()` — el grupo enlazado al curso de Moodle.
5. Auto-inscripción (`Inscripcion::firstOrCreate`) — el launch **prueba** que el
   alumno está en ese curso. Sin esto, quien entra antes de que el maestro
   sincronice el roster se topa con un 403.
6. `eventoDestino()` — la fecha que ya reservó; si no, la próxima sin terminar;
   y si todas pasaron, la última, para que vea qué ocurrió.
7. Redirect a `mi.eventos.show`.

**El paso 2 era el bloqueador de todo el rediseño.** Con reserva obligatoria, la
sesión de juego se crea *días después* de que Moodle cerró el launch — y para
entonces el endpoint AGS (a dónde devolver la calificación) ya no está
disponible. Por eso ahora se persiste en el launch:

- Tabla `lti_vinculos_actividad` — `unique(id_alumno, id_practica)`, guarda
  `ags_lineitem_url` y `ags_endpoint`.
- Modelo `App\Models\LtiVinculoActividad`.
- Cada launch **refresca** el vínculo.

Si el launch no puede continuar, no tira un error: renderiza
`Mi/PracticaNoDisponible` con el motivo (`curso-sin-grupo` o `sin-fechas`), que
dice qué falta, **quién** lo tiene que arreglar, y deja al alumno dentro de la
app con un enlace a su calendario en vez de en un callejón.

> **Caveat de cookies:** dentro de un iframe de Moodle aplican las reglas de
> SameSite. Está documentado al final de `docs/LTI-CONFIGURAR-MOODLE.md`.

### 4.4 Calendario

- Muestra la semana **completa aunque esté vacía** (es un calendario, no una
  lista de resultados).
- Topes de navegación desde `EventoAgenda::limitesSemana($visibles)`.
- Al cambiar de semana **solo se anima el movimiento**; antes se remontaba todo
  y se re-disparaban todas las animaciones de entrada.
- El flicker se debía a un `useState` para la dirección: provocaba un render al
  hacer clic, arrancando la animación con el contenido viejo → 85ms en blanco.
  Ahora es un `useRef`.

### 4.5 Vistas nuevas del alumno

- `mi/cursos` → **solo la lista de cursos**.
- `mi/cursos/{grupo}` → las prácticas de ese curso (solo lectura).
- El dashboard muestra **reservas activas, pendientes por reservar y cursos
  actuales**, en vez del historial de reservas que estaba antes.

### 4.6 Panel del maestro

`Panel/Grupo` agrupa **por práctica, no por evento**. Cuando el maestro agenda
la misma práctica 8 veces, esas son 8 *fechas alternativas*, no 8 prácticas; en
plano se leían como filas duplicadas. La cabecera ahora dice
"3 prácticas · 11 fechas".

### 4.7 El modo demo se mudó

Vivía en `resources/views/lti/abrir-juego.blade.php`. Al hacerse obligatoria la
reserva ese interstitial dejó de ser alcanzable, y con él se habría perdido la
única forma de probar el circuito completo —sesión → calificación → Moodle— **sin
el motor de juego instalado**. Ahora está en `resources/views/jugar.blade.php`,
que es la única entrada al juego que queda.

`jugar.blade.php` sigue siendo **Blade a propósito**, no Inertia: dispara un
deeplink de esquema propio, y eso exige navegación top-level y nada de SPA.

---

## 5. Mapa de lo nuevo

```
PRODUCT.md                                    verdad del producto
DESIGN.md  +  .impeccable/design.json         el sistema de diseño

app/Services/EstadoEventoAlumno.php           ← estado alumno↔evento, fuente única
app/Models/LtiVinculoActividad.php            ← destino AGS persistido
app/Http/Controllers/Mi/EventoController.php  ← la vista que cambia según estado
app/Http/Controllers/Mi/CursoController.php   ← index + show

resources/js/Components/Placa.jsx             Placa, Franja, Marca, useLamparaRasante
resources/js/Components/Lamina.jsx            el contenedor (3 piezas, 2 envoltorios)
resources/js/Components/Encabezado.jsx
resources/js/Components/CabeceraAdmin.jsx
resources/js/Components/PasoSemana.jsx
resources/js/fechas.js

resources/js/Pages/Mi/Evento.jsx              reservar / esperar / jugar
resources/js/Pages/Mi/Cursos.jsx  +  Curso.jsx
resources/js/Pages/Mi/PracticaNoDisponible.jsx

database/seeders/DemoAgendaSeeder.php         idempotente, fechas relativas a now()
database/migrations/..._create_lti_vinculos_actividad_table.php
tests/Pest.php                                helpers compartidos
```

**Helpers de test** (`tests/Pest.php`, no dentro de un archivo de test):
`crearEventoBasico()`, `reservarEscenario()`, `reservarNuevoAlumno()`.

---

## 6. Pendientes conocidos

- **La portada** (`resources/views/portada.blade.php`) sigue siendo un tercer
  universo visual, con su propio bundle aislado. No se rediseñó.
- **`POST /api/game/lti-redeem`** quedó inalcanzable con la reserva obligatoria.
  Sigue en su sitio con un comentario; borrarlo es decisión pendiente.
- **El flujo LTI no está verificado contra un Moodle real** en este entorno: el
  puerto 8080 lo ocupa un Keycloak de otro proyecto. La lógica está cubierta por
  tests, pero falta el end-to-end.
- **"Generar links"** en el panel del maestro es una salida de emergencia
  (repartir magic links por WhatsApp/pizarrón). Para el alumno el link ya es
  automático — `Mi/JugarController` lo acuña al presionar "Entrar a la
  práctica", y desde Moodle el launch lo lleva directo. Está a discusión si se
  quita de la vista.
