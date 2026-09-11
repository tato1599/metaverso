# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Alumno** — estudiante del Tecnológico Nacional de México inscrito a un grupo. Consulta su calendario, reserva su lugar en un slot de práctica, juega la práctica y recibe calificación. **Su camino real es Moodle → LTI**, no `/login`; el acceso directo es respaldo. Confirmado por el usuario: el flujo del alumno no es la prioridad del rediseño.
- **Maestro** — programa eventos de agenda (práctica + grupo + espacio + horario), define cupos, consulta reservas y resultados. Entra por `/login` y vive en `/panel`.
- **Coordinador / Admin** — gestiona carreras, materias, ciclos, grupos, espacios, prácticas y usuarios. Entra por `/login`, alcanza `/admin` y `/panel`.
- **Juego (cliente 3D)** — actor no humano. Canjea un enlace dinámico, abre sesión y reporta resultado vía API con `X-Api-Key` + token de sesión.

## Product Purpose

Un puente entre la agenda académica, el LMS institucional y un juego 3D: cada práctica agendada se convierte en una sesión jugable cuyo resultado regresa al expediente del alumno en Moodle.

Éxito = el maestro agenda sin fricción, el alumno entra desde Moodle y juega, y la calificación aterriza sola en el libro de calificaciones. Nadie captura nada dos veces.

## Positioning

Lo que un LMS solo no hace y un juego solo tampoco: **el cupo del slot es una restricción de recurso del servidor del juego, no una regla académica**, y la reserva se resuelve con bloqueo de fila para no sobrevender el último lugar. La plataforma administra la escasez de una sesión jugable con rigor de sistema de reservas, y la devuelve al LMS como calificación.

## Operating Context

- Escuela pública mexicana. Equipos de laboratorio de cómputo, no máquinas personales de gama alta. Luz de aula/laboratorio, pantallas variadas, navegadores de escritorio como caso dominante.
- El LMS institucional es **Moodle 5.0**, integrado por **LTI 1.3** (OIDC + JWT, Deep Linking para colocar la actividad, AGS para regresar la calificación).
- Correo institucional bajo el dominio `tecnm.mx`.
- Español de México en toda la interfaz.
- Existe un **modo demo sin motor de juego** que simula la partida desde el navegador y devuelve calificación, para probar sin depender del cliente 3D.

## Capabilities and Constraints

**Existe hoy:**
- Login por correo + contraseña, con rate limit de 5 intentos por minuto por (correo, IP) y filtro `activo = true`.
- Redirección por rol tras el login: staff → `/panel`, alumno → `/mi/calendario`.
- Agenda de eventos, reserva con verificación de inscripción y de cupo bajo transacción con `SELECT ... FOR UPDATE`.
- Enlace dinámico al juego, sesión de práctica y reporte de resultado.
- LTI 1.3 completo: login, launch, deep linking, JWKS.
- Administración de carreras, materias, ciclos, grupos, espacios, prácticas, usuarios.

**NO existe y el diseño no debe fingir que existe:**
- Recuperación de contraseña. No hay ruta, no hay correo. Las contraseñas las da coordinación.
- "Recordarme" / sesión persistente.
- Registro público de cuentas.
- Inicio de sesión con Google, Microsoft o cuenta institucional federada.
- Verificación en dos pasos.

**Constraints técnicas:** Laravel 12 + Inertia v2 + React 19 + Tailwind v4, sin SSR. PostgreSQL. El modelo es **agnóstico al motor de juego** a propósito (`PRACTICA.escena_referencia`, `TOKEN_JUEGO.plataforma`); el cliente actual del demo está hecho en Godot 4, y `CONTEXTO.md` todavía dice Unreal — el motor puede cambiar y el diseño no debe nombrarlo.

**Terminología del producto (usar estas palabras, no sinónimos):** práctica, evento de agenda (el "slot"), reserva, sesión de práctica, cupo, grupo, ciclo escolar, espacio.

## Brand Commitments

- No se recibieron especificaciones de diseño del cliente. La dirección visual es libre — confirmado por el usuario.
- Existe una identidad institucional **TecNM** ya usada en la portada (`resources/views/portada.blade.php`): franja azul `#1a3a8f` / rojo `#b3123b`, nombre "Metaverso Escolar". No es normativa impuesta, pero es la única señal institucional real del producto y el público de la demo son directivos del Tec.
- Nombre del producto: **Metaverso** · campus de prácticas.
- El usuario fijó como mundo visual de referencia el prototipo **`04-instrumento`** de su librería `/home/farid/repos/design-taste`, en registro claro. Esta es una restricción vinculante.

## Evidence on Hand

- **Datos demo reales y sembrados** en la base local (`CREDENCIALES-DEMO.md`): usuarios por rol bajo `@tecnm.mx`, curso `PROG-3A` en Moodle con la herramienta LTI ya configurada.
- **Sin material fotográfico propio**: no hay fotos del laboratorio, de alumnos ni del juego en `public/`. Cualquier imagen mostrada debe ser autoral (dibujada en código) o quedar marcada como pendiente de reemplazo.
- **Sin logo en archivo.** No existe isotipo ni logotipo de "Metaverso" en el repo; la portada dibuja una marca de tres barras en HTML.
- **Sin métricas, sin testimonios, sin clientes, sin precios.** No inventar ninguno.

## Product Principles

1. **El dato manda.** Horas, cupos, matrículas, claves y calificaciones son el contenido real del producto; se presentan como medición, no como adorno.
2. **Honestidad de capacidades.** La interfaz nunca ofrece una puerta que el backend no abre (recuperar contraseña, recordarme, registro).
3. **Moodle es un camino, no un competidor.** Donde el alumno llegue por LTI, la interfaz lo reconoce y lo devuelve ahí en lugar de pelear por ser la puerta principal.
4. **Un solo producto.** Portada, acceso y panel son el mismo sistema; hoy son tres universos visuales distintos y eso es deuda, no variedad.
5. **Aguantar el laboratorio.** Equipos modestos y pantallas variadas: el rendimiento y la legibilidad son parte del diseño, no un ajuste posterior.

## Accessibility & Inclusion

- Contraste AA como piso, medido y no supuesto. La base actual (`resources/css/app.css`) ya bajó `--color-tinta-3` a L 0.5 explícitamente para alcanzar 4.5:1; ese rigor se conserva.
- `prefers-reduced-motion` respetado de verdad, no declarado.
- Navegación completa por teclado con foco visible.
- Español de México, sin anglicismos innecesarios.
