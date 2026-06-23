# Metaverso Escolar TecNM — Backend

Backend Laravel + PostgreSQL con magic link y API para el juego de Unreal Engine.

> **Stack real:** Laravel 12.x (objetivo futuro: Laravel 13) · PHP ^8.3 · PostgreSQL 14+

---

## Requisitos

| Herramienta | Versión mínima |
|-------------|----------------|
| PHP         | 8.3            |
| Composer    | 2.x            |
| PostgreSQL  | 14             |
| Node.js     | 20 (solo assets) |

---

## Arranque rápido

```bash
# 1. Clonar y entrar al proyecto
git clone <repo-url> metaverso && cd metaverso

# 2. Instalar dependencias
composer install

# 3. Variables de entorno
cp .env.example .env
php artisan key:generate

# 4. Crear las bases de datos
createdb metaverso        # base principal
createdb metaverso_test   # base para tests

# 5. Migrar y sembrar datos demo
php artisan migrate --seed

# 6. Levantar servidor local
php artisan serve
```

Las credenciales por defecto del `.env.example` son `postgres`/`postgres` en `127.0.0.1:5432`.

---

## Probar el flujo completo

```bash
# Genera un magic link para el usuario 1 y el evento 1
php artisan metaverso:magic-link 1 1
```

Salida esperada:
```
URL:      http://localhost/jugar/<token>
Deeplink: tecnm-metaverso://play?token=<token>
Expira:   2026-06-23 09:09:36
```

1. Abre la **URL** en el navegador → muestra la página `/jugar/{token}`.
2. El botón "Abrir juego" dispara el deeplink `tecnm-metaverso://play?token=...` que Unreal Engine debe registrar como URL scheme.

> **Datos demo (DemoSeeder):** usuario `id=1` es la maestra Laura; usuarios `id=2,3,4` son alumnos Ana, Beto y Caro. El evento `id=1` es el único evento de agenda sembrado.

---

## Flujo de la API (lo que consume Unreal Engine)

### 1. Canjear magic link → Bearer token

```
POST /api/game/redeem
Content-Type: application/json

{ "token": "<token-del-deeplink>" }
```

Respuesta `200`:
```json
{
  "access_token": "...",
  "alumno": { "id_usuario": 2, "nombre": "Ana" },
  "practica": { "id_practica": 1, "nombre": "..." },
  "evento": { "id_evento": 1, "fecha_hora_inicio": "..." }
}
```

Errores: `401` token inválido · `410` token expirado o ya usado · `422` falta el campo.

### 2. Obtener perfil del alumno autenticado

```
GET /api/game/me
Authorization: Bearer <access_token>
```

### 3. Abrir sesión de práctica

```
POST /api/game/sessions
Authorization: Bearer <access_token>
Content-Type: application/json

{ "id_evento": 1 }
```

Respuesta `201`: `{ "id_sesion": 7 }`

### 4. Cerrar sesión con calificación y telemetría

```
POST /api/game/sessions/{id_sesion}/complete
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "calificacion": 85,
  "datos_resultado": { "tiempo_s": 320, "errores": 2 }
}
```

Respuesta `200`: confirmación del cierre.

Errores: `403` sesión de otro alumno · `409` sesión ya cerrada · `422` calificación fuera de [0,100].

### 5. Crear magic link desde el sistema docente (sin auth — MVP)

```
POST /api/links
Content-Type: application/json

{ "id_usuario": 2, "id_evento": 1 }
```

Respuesta `201`: `{ "url": "...", "deeplink": "...", "expira": "..." }`

---

## Configuración

Las siguientes variables se añaden en `.env` (ya incluidas en `.env.example`):

| Variable                    | Default           | Descripción                                      |
|-----------------------------|-------------------|--------------------------------------------------|
| `MAGIC_LINK_TTL_MINUTES`    | `120`             | Vigencia del magic link en minutos               |
| `GAME_DEEPLINK_SCHEME`      | `tecnm-metaverso` | URL scheme que Unreal Engine registra            |
| `SANCTUM_TOKEN_TTL_MINUTES` | `480`             | Vigencia del Bearer de sesión del juego          |
| `LINKS_API_KEY`             | _(vacío)_         | Clave para `POST /api/links` (header `X-Api-Key`). Si está vacía, el endpoint rechaza todo (fail-closed). |

---

## Documentación interactiva y demo visual

Con el servidor corriendo (`php artisan serve`):

| Recurso | URL | Qué es |
|---------|-----|--------|
| **Doc API interactiva** | `http://localhost:8000/docs` | Documentación generada con Scribe: todos los endpoints, ejemplos y "Try it out". También exporta Postman (`/docs.postman`) y OpenAPI (`/docs.openapi`). |
| **Demo visual** | `http://localhost:8000/demo` | Página interactiva que muestra el diagrama de flujo y ejecuta el flujo completo (generar link → redeem → iniciar → completar) en vivo. |
| **Diagrama de flujo** | `docs/api/flujo-del-juego.md` | Diagrama Mermaid del flujo (se renderiza en GitHub). |
| **Cómo funciona (sin tecnicismos)** | `docs/COMO-FUNCIONA.md` | Explicación completa del flujo para maestros y coordinadores. |
| **Integración con Moodle (LTI)** | `docs/INTEGRACION-MOODLE-LTI.md` | Cómo conectar con Moodle con el mínimo de trámites. |

> Para usar `/demo` y `/api/links` localmente, define `LINKS_API_KEY` en tu `.env`.

---

## Panel de Maestros

Panel web para maestros/coordinadores: ver grupos y alumnos, generar magic links
de todo un grupo y consultar calificaciones.

### Acceso (sin contraseña, magic link firmado)

```bash
# Genera un enlace de acceso para un usuario con rol Maestro/Coordinador/Admin
php artisan metaverso:panel-acceso <id_usuario>
```

Abre la URL impresa (válida 30 min, configurable con `PANEL_LOGIN_TTL_MINUTES`).
Caduca y entra a `/panel`. Los alumnos no pueden entrar.

### Rutas

- `/panel` — mis grupos (Maestro) o todos (Coordinador/Admin)
- `/panel/grupos/{grupo}` — alumnos + eventos del grupo
- `/panel/grupos/{grupo}/eventos/{evento}/links` — genera links del grupo (descarga de CSV realizada en el cliente, sin petición adicional al servidor)
- `/panel/grupos/{grupo}/resultados` — calificaciones y sesiones

### Compatibilidad con LTI

El inicio de sesión del panel comparte el método `PanelLoginController::establecerSesion`.
El launch de Moodle (LTI 1.3) reutiliza ese mismo método para establecer la sesión del alumno.

---

## Integración LTI (Moodle)

El backend expone una herramienta LTI 1.3 que conecta cualquier actividad de Moodle con el metaverso:

1. Moodle lanza al alumno mediante el flujo OIDC/LTI 1.3.
2. El backend auto-aprovisiona al alumno por su `lti_user_id` (crea `Usuario` + `Alumno` si no existen o reutiliza el existente).
3. El alumno entra al juego con su identidad ya resuelta — sin pasos extra.
4. Al terminar la práctica, la calificación regresa a Moodle automáticamente mediante **AGS** (Assignment and Grade Services).

### Endpoints LTI

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/lti/jwks` | JWKS público — Moodle lo usa para verificar los JWTs del tool |
| `GET \| POST` | `/lti/login` | Inicio del flujo OIDC (tercer paso de la negociación LTI 1.3) |
| `POST` | `/lti/launch` | Recepción del JWT de lanzamiento; inicia sesión de alumno |
| `GET \| POST` | `/lti/deeplink` | Flujo Deep Linking: selector de práctica + respuesta JWT a la plataforma |

### Comandos Artisan

```bash
# 1. Generar el par de llaves RSA del tool (ejecutar una sola vez o para rotar llaves)
php artisan metaverso:lti-generar-llaves

# 2. Registrar (o actualizar) una plataforma LTI (por ejemplo, Moodle)
php artisan metaverso:lti-registrar-plataforma \
  --issuer=https://moodle.example.com \
  --client-id=<CLIENT_ID> \
  --deployment-id=<DEPLOYMENT_ID> \
  --auth-login-url=https://moodle.example.com/mod/lti/auth.php \
  --auth-token-url=https://moodle.example.com/mod/lti/token.php \
  --jwks-url=https://moodle.example.com/mod/lti/certs.php
```

### Documentación adicional

| Documento | Descripción |
|-----------|-------------|
| [`docs/LTI-CONFIGURAR-MOODLE.md`](docs/LTI-CONFIGURAR-MOODLE.md) | Guía paso a paso para configurar el tool en la UI de Moodle |
| [`docs/INTEGRACION-MOODLE-LTI.md`](docs/INTEGRACION-MOODLE-LTI.md) | Estrategia y decisiones de diseño de la integración |

> **Nota:** La capa de integración con la librería LTI (`app/Lti/Libreria*` — validadores reales, cliente AGS) está desacoplada detrás de interfaces propias (`LaunchValidador`, `DeepLinkRespondedor`, `AgsCliente`). Esto mantiene la suite unitaria determinista; la integración real con la librería y con Moodle se verifica end-to-end contra el Moodle local de Docker (ver `docs/LTI-CONFIGURAR-MOODLE.md`).

---

## Tests

```bash
php artisan test
# o
composer test
```

Suite actual: **55 tests** (1 skipped) — todos en verde.

La base de datos de tests se configura con `DB_DATABASE=metaverso_test` en `phpunit.xml`.

---

## Estructura relevante

```
app/
  Http/Controllers/Api/
    GameAuthController.php     # POST /api/game/redeem
    GameSessionController.php  # GET /api/game/me, POST sessions, POST complete
    LinkController.php         # POST /api/links
  Services/
    MagicLinkService.php       # Generación de tokens con hash y TTL
  Console/Commands/
    GenerarMagicLink.php       # php artisan metaverso:magic-link
  Models/                      # Eloquent: Usuario, Alumno, Maestro, Grupo, …
database/
  migrations/                  # 19 migraciones (catálogo + operación)
  seeders/DemoSeeder.php       # Datos demo: roles, carrera, maestro, 3 alumnos, evento
resources/views/
  jugar.blade.php              # Página de apertura del juego con deeplink
```

---

## Fuera de alcance — Fase 2

- CRUD de grupos, prácticas y agenda desde el panel
- Agenda visual tipo calendario
- Integración Moodle / LTI — **implementada en `feat/lti-moodle`** (ver sección anterior)
- Calificación agregada y reportes avanzados
- Proteger `POST /api/links` con autenticación de maestro (actualmente sin auth en el MVP)
