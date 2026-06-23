# Diseño — Integración LTI 1.3 con Moodle (Sub-proyecto 2)

**Fecha:** 2026-06-23
**Estado:** Aprobado para implementación

## 1. Contexto

El TecNM usa Moodle. Queremos que los alumnos entren al Metaverso **desde una
actividad de Moodle** (identidad automática, sin magic links manuales) y que la
**calificación del juego regrese sola a Moodle**. El maestro elige qué práctica
se juega al crear la actividad. Esto reemplaza al magic link como *puerta de
entrada* desde Moodle; el resto del flujo (Unreal canjea, juega, califica) no
cambia.

LTI 1.3 requiere **un único registro** de la herramienta en Moodle (lo hace el
admin una vez); después cada maestro solo agrega la actividad.

## 2. Decisiones tomadas (brainstorming)

| Decisión | Elección |
|---|---|
| Alcance | **Completo**: launch (identidad) + Deep Linking + calificación de regreso (AGS). |
| Selección de práctica | **Deep Linking**: el maestro elige de una lista al crear la actividad. |
| Registro en Moodle | Lo configura el equipo en el **Moodle local de Docker** (en el Moodle real, el admin una vez). |
| Identificación del alumno | **Auto-aprovisionamiento** por `lti_user_id`: si no existe, se crea `Usuario`+`Alumno`. |
| Librería | `packbackbooks/lti-1-3-php-library`. |
| Fuera de alcance | NRPS (sync de roster), multi-tenant (solo un Moodle registrado). |

## 3. Librería y por qué

`packbackbooks/lti-1-3-php-library` es la implementación estándar de LTI 1.3 en
PHP. Provee: OIDC login, validación del `id_token` (JWT), Deep Linking, AGS
(line items + grades) y NRPS. Requiere que implementemos su interfaz `Database`
(buscar plataforma por issuer/client_id, y un store de nonces) y un cache. No
reimplementamos criptografía.

## 4. Modelo de datos (tablas nuevas + cambios)

### Tablas nuevas
- **`lti_platforms`** — la plataforma (Moodle) registrada:
  `id`, `issuer` (URL base de Moodle), `client_id`, `auth_login_url`,
  `auth_token_url`, `jwks_url`, `deployment_id`, `activo`.
- **`lti_keys`** — par RSA de **nuestra herramienta**:
  `id`, `kid`, `public_key` (PEM), `private_key` (PEM), `activo`.
  (La privada firma; la pública se publica en `/lti/jwks`.)
- **`lti_nonces`** — anti-replay: `id`, `nonce`, `expira`. Limpieza por expiración.

### Cambios a tablas existentes
- **`sesiones_practica`**:
  - **`id_evento` pasa a NULLABLE.** Las sesiones por LTI se anclan a una
    **práctica** (`id_practica`, ya existente y obligatorio), no a un evento de
    agenda. Las sesiones del flujo de juego normal siguen llenando `id_evento`.
  - agrega (nullable, solo se llenan en sesiones por LTI):
    - `lti_platform_id` (FK nullable → `lti_platforms`),
    - `ags_lineitem_url` (string nullable) — a qué casilla de calificación devolver,
    - `ags_endpoint` (string nullable) — base del servicio AGS.
- **`usuarios`** agrega `lti_user_id` (string nullable, indexado) — el id del
  alumno en Moodle, para empatar/auto-aprovisionar.

> Implicación en el panel: la pantalla de resultados del panel lista sesiones por
> los **eventos** del grupo; las sesiones por LTI (sin `id_evento`) no aparecen
> ahí en esta fase. Es aceptable para el MVP (la nota va directo a Moodle). Una
> vista de "sesiones por práctica" queda como mejora futura.

## 5. Endpoints

| Método | Ruta | Auth | Para qué |
|---|---|---|---|
| `GET/POST` | `/lti/login` | pública (OIDC) | Inicio OIDC: Moodle nos llama; redirigimos de vuelta a su `auth_login_url`. |
| `POST` | `/lti/launch` | valida JWT | Recibe el launch; valida; auto-aprovisiona alumno; crea sesión; muestra "Abrir juego". Si es un Deep Linking request, redirige al selector. |
| `GET` | `/lti/deeplink` | dentro de launch DL | Muestra la lista de prácticas para que el maestro elija. |
| `POST` | `/lti/deeplink` | dentro de launch DL | Recibe la práctica elegida y devuelve a Moodle el `DeepLinkingResponse` (JWT firmado). |
| `GET` | `/lti/jwks` | pública | Publica nuestra(s) llave(s) pública(s) (JWKS). |

Notas:
- `/lti/login` y `/lti/launch` son llamados por el **navegador** (redirects), así
  que sus URLs deben resolver igual en navegador y en el contenedor de Moodle
  (ver §8 Redes).
- El `id_token` del launch incluye el claim de mensaje: `LtiResourceLinkRequest`
  (juego) o `LtiDeepLinkingRequest` (selección). El controlador ramifica.

## 6. Flujo completo

### A. Registro (una vez, lo hacemos en el Moodle local)
1. Generar el par RSA de la herramienta (`lti_keys`) — comando artisan.
2. En Moodle: crear "External tool" (LTI 1.3) con nuestras URLs
   (`/lti/login`, `/lti/launch`, `/lti/jwks`) y "Deep Linking" habilitado.
3. Moodle nos da `client_id`, `deployment_id`, y sus URLs (`auth_login_url`,
   `auth_token_url`, `jwks_url`, issuer) → se guardan en `lti_platforms`
   (comando artisan `metaverso:lti-registrar-plataforma`).

### B. El maestro crea la actividad (Deep Linking)
1. Maestro agrega la actividad → Moodle lanza un `LtiDeepLinkingRequest` a
   `/lti/launch`.
2. Mostramos `/lti/deeplink`: lista de prácticas.
3. Maestro elige → construimos un `DeepLinkingResponse` (JWT firmado con nuestra
   llave) con el `resource_link` apuntando a la práctica (custom param
   `id_practica`) → el navegador lo postea de vuelta a Moodle.

### C. El alumno juega
1. Alumno hace clic en la actividad → OIDC → `/lti/launch` (LtiResourceLinkRequest).
2. Validamos el JWT. Extraemos: `lti_user_id`, nombre, correo, rol, el
   `id_practica` (custom param), y los datos de **AGS** (`lineitem`, `endpoint`).
3. **Auto-aprovisionamiento:** buscamos `Usuario` por `lti_user_id`; si no existe
   lo creamos (rol Alumno) + su `Alumno`. (Inscripción a grupo: opcional/omitible
   en MVP — la sesión se ancla a la práctica del custom param.)
4. Creamos una `SesionPractica` (estatus `en_progreso`) con `id_practica` (del
   custom param) e `id_evento` = NULL, guardando `lti_platform_id`,
   `ags_lineitem_url`, `ags_endpoint`, y el `lti_user_id` del alumno.
5. Emitimos el Bearer de juego (igual que redeem) y mostramos "Abrir juego"
   (deeplink a Unreal con un token de juego ligado a esa sesión).
6. Unreal juega y llama `complete` como hoy.

### D. Calificación de regreso (AGS)
1. Al completar la sesión (en el endpoint `complete` existente), si la sesión
   tiene `ags_lineitem_url`, enviamos la nota a Moodle vía AGS
   (`LTI_Grade` server-to-server, OAuth2 client_credentials firmado con nuestra
   llave).
2. La nota se mapea: `calificacion` (0–100) → score con `scoreMaximum=100`.

## 7. Manejo de errores

- JWT inválido / nonce repetido / plataforma desconocida → 401 con mensaje claro.
- Falta `id_practica` en el launch (actividad mal configurada) → página de error
  amigable ("esta actividad no tiene práctica asignada; pide al maestro
  reconfigurarla").
- Fallo de AGS (Moodle no responde) → se registra y la nota queda en BD; se puede
  reintentar (log claro). No rompe el flujo del alumno.

## 8. Redes (entorno Docker local)

- Moodle corre en contenedor (`localhost:8080`); nuestro backend en el host
  (`php artisan serve` en `:8000`).
- Para que **navegador y contenedor** usen la **misma URL** de la herramienta,
  agregamos `host.docker.internal` al `/etc/hosts` del host (→ 127.0.0.1) y
  registramos las URLs de la herramienta como
  `http://host.docker.internal:8000/lti/...`.
- Server-to-server: backend→Moodle (JWKS/token/AGS) usa `http://localhost:8080`;
  Moodle→backend (nuestro JWKS) usa `http://host.docker.internal:8000`.
- LTI 1.3 sobre http es aceptable solo en este entorno de desarrollo local.

## 9. Pruebas

Tests de feature (sin depender de un Moodle real, usando JWT/llaves de prueba):
1. `/lti/jwks` publica una llave pública válida (JWK con `kid`).
2. Launch con `id_token` válido (firmado con una llave de plataforma de prueba) →
   auto-aprovisiona alumno por `lti_user_id` (lo crea una vez; reusa después) y
   crea la `SesionPractica` con los datos AGS.
3. Launch con JWT inválido/nonce repetido → 401.
4. Deep Linking: el selector lista prácticas; al elegir, la respuesta es un JWT
   de DeepLinkingResponse válido con el `id_practica` correcto.
5. AGS: completar una sesión con `ags_lineitem_url` invoca al cliente AGS
   (mockeado en test) con la nota correcta; sin `ags_lineitem_url` no lo invoca.

Verificación **end-to-end real** contra el Moodle de Docker (manual, documentada):
crear curso + actividad (Deep Linking elige práctica) → entrar como alumno →
abrir juego → completar → ver la nota en el libro de calificaciones de Moodle.

## 10. Entregables

- Dependencia `packbackbooks/lti-1-3-php-library` + implementación de su interfaz
  `Database`/cache sobre nuestras tablas.
- Migraciones: `lti_platforms`, `lti_keys`, `lti_nonces`; columnas en
  `sesiones_practica` y `usuarios`.
- Modelos `LtiPlatform`, `LtiKey`, `LtiNonce`.
- Controlador(es) LTI: `/lti/login`, `/lti/launch`, `/lti/deeplink`, `/lti/jwks`.
- Servicio de AGS (envío de nota) llamado desde `complete`.
- Comandos artisan: `metaverso:lti-generar-llaves`,
  `metaverso:lti-registrar-plataforma`.
- Configuración del Moodle local (registro) + ajuste de red `host.docker.internal`.
- Tests de feature (5 escenarios) + guía de verificación end-to-end.
- Doc de operación: cómo registrar la herramienta en un Moodle real (para el admin).

## 11. Fuera de alcance (siguientes fases)

- NRPS (sincronizar el roster del curso).
- Multi-tenant (varios Moodles a la vez).
- Inscripción automática a grupos desde el contexto del curso LTI.
- HTTPS/producción endurecida (este sub-proyecto es para el demo local).
