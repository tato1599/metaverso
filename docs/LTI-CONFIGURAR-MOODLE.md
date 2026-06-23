# Configurar Moodle (Docker) como plataforma LTI 1.3

Esta guía cubre los pasos completos para registrar el metaverso como herramienta LTI 1.3 en un Moodle que corra en Docker, y realizar la prueba e2e.

---

## 1. Red local: hacer que Moodle alcance el backend Laravel

Moodle corre dentro de un contenedor Docker y necesita resolver la URL de tu máquina host.

### 1a. Agregar `host.docker.internal` al `/etc/hosts` del host (macOS/Linux)

En macOS Docker Desktop ya lo resuelve automáticamente. En Linux, agrega la línea:

```
# /etc/hosts del HOST (no del contenedor)
127.0.0.1   host.docker.internal
```

```bash
sudo sh -c 'echo "127.0.0.1 host.docker.internal" >> /etc/hosts'
```

### 1b. Ajustar `APP_URL` en `.env`

```dotenv
APP_URL=http://host.docker.internal:8000
```

### 1c. Levantar el backend escuchando en todas las interfaces

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

> El servidor debe estar corriendo cada vez que Moodle necesite contactar al metaverso (OIDC, JWKS, launch, AGS).

---

## 2. Generar las llaves RSA del metaverso (si no se ha hecho)

```bash
php artisan metaverso:lti-generar-llaves
```

Esto guarda `LTI_PRIVATE_KEY` / `LTI_PUBLIC_KEY` (o archivos PEM) según la configuración. El endpoint `/lti/jwks` las expone públicamente.

---

## 3. Registrar la herramienta en Moodle

### 3a. Navegar al panel de administración

1. Inicia sesión como administrador de Moodle.
2. Ve a **Site administration → Plugins → Activity modules → External tool → Manage tools**.
3. Haz clic en **"configure a tool manually"**.

### 3b. Completar el formulario con LTI 1.3

| Campo | Valor |
|---|---|
| **Tool name** | Metaverso TecNM |
| **Tool URL** | `http://host.docker.internal:8000/lti/launch` |
| **LTI version** | LTI 1.3 |
| **Public key type** | **Keyset URL** |
| **Public keyset URL** | `http://host.docker.internal:8000/lti/jwks` |
| **Initiate login URL** | `http://host.docker.internal:8000/lti/login` |
| **Redirection URI(s)** | `http://host.docker.internal:8000/lti/launch` |

### 3c. Pestaña Services

| Servicio | Configuración |
|---|---|
| **IMS LTI Assignment and Grade Services** | "Use this service for grade sync and column management" |
| **Deep Linking** | Activado (habilitado) |

### 3d. Guardar

Haz clic en **Save changes**.

---

## 4. Obtener los valores que Moodle generó

Después de guardar, Moodle muestra los **"Tool configuration details"** (en la vista de la herramienta, ícono de detalles o pestaña *Tool configuration*). Anota:

- **Platform ID (Issuer)**: algo como `http://localhost:8080` o la URL base de tu Moodle.
- **Client ID**: cadena alfanumérica generada por Moodle.
- **Deployment ID**: número asignado al crear el despliegue.
- **Authentication request URL** (`auth_login_url`): `http://<moodle>/mod/lti/auth.php`
- **Access token URL** (`auth_token_url`): `http://<moodle>/mod/lti/token.php`
- **Public keyset URL** (`jwks_url`): `http://<moodle>/mod/lti/certs.php`

---

## 5. Registrar la plataforma en el metaverso

Con los valores obtenidos en el paso anterior, ejecuta el comando de registro:

```bash
php artisan metaverso:lti-registrar-plataforma \
  --issuer=http://localhost:8080 \
  --client-id=<CLIENT_ID_DE_MOODLE> \
  --deployment-id=<DEPLOYMENT_ID_DE_MOODLE> \
  --auth-login-url=http://localhost:8080/mod/lti/auth.php \
  --auth-token-url=http://localhost:8080/mod/lti/token.php \
  --jwks-url=http://localhost:8080/mod/lti/certs.php
```

> Si ya existe una plataforma con el mismo `issuer` + `client_id`, el comando la **actualiza** (upsert) sin crear duplicados. Puedes volver a ejecutarlo para cambiar el `deployment_id` u otras URLs.

Salida esperada:

```
Plataforma registrada: http://localhost:8080 (CID_GENERADO)
```

---

## 6. Verificar los endpoints

Comprueba que los tres endpoints responden antes de la prueba e2e:

```bash
# JWKS público
curl http://localhost:8000/lti/jwks

# OIDC login (espera redirect, no 500)
curl -I http://localhost:8000/lti/login

# Launch (espera 422 sin JWT — es correcto)
curl -I http://localhost:8000/lti/launch
```

---

## 7. Prueba e2e

### 7a. Crear un curso en Moodle

1. En Moodle, ve a **Site home → Add a new course**.
2. Completa nombre y categoría, guarda.
3. Inscribe un usuario de prueba como **Student**.

### 7b. Agregar la actividad "External tool"

1. Dentro del curso, activa la edición (**Turn editing on**).
2. **Add an activity → External tool**.
3. En **Preconfigured tool**, selecciona **Metaverso TecNM**.
4. Escribe un nombre de actividad y guarda.

### 7c. Deep Linking: seleccionar la práctica

1. Al abrir la actividad (como instructor), haz clic en **Select content**.
2. El metaverso muestra el **selector de prácticas** (`/lti/deeplink`).
3. Elige la práctica deseada y confirma.
4. Moodle guarda el `resource_link` con la práctica seleccionada.

### 7d. Entrar como alumno

1. Cierra sesión o usa un navegador diferente.
2. Inicia sesión con el usuario Student inscrito.
3. Abre el curso y haz clic en la actividad External tool.
4. El flujo OIDC (login → launch) autentica al alumno y crea (o reutiliza) su registro en el metaverso.
5. Debes ver la pantalla **"Abrir juego"**.

### 7e. Completar la sesión

**Opción A — Desde Unreal Engine / cliente real:**
- El cliente llama `POST /api/sesiones/{id}/completar` con `Bearer <token>` y `{"calificacion": 95, "telemetria": {...}}`.

**Opción B — Simular con curl:**
```bash
# 1. Obtener bearer (canjeando el magic link del alumno)
TOKEN=$(curl -s -X POST http://localhost:8000/api/tokens/redeem \
  -H "Content-Type: application/json" \
  -d '{"token": "<TOKEN_MAGICO>"}' | jq -r '.access_token')

# 2. Iniciar sesión de práctica
SESION_ID=$(curl -s -X POST http://localhost:8000/api/sesiones \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"practica_id": 1, "evento_id": null}' | jq -r '.id')

# 3. Completar con calificación
curl -X POST http://localhost:8000/api/sesiones/$SESION_ID/completar \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"calificacion": 95, "telemetria": {"tiempo_segundos": 120}}'
```

### 7f. Ver la calificación en Moodle

1. En Moodle, ve a **Grades** (libro de calificaciones) del curso.
2. La columna de la actividad External tool debe mostrar la nota enviada por AGS.
3. Si la calificación no aparece de inmediato, espera unos segundos y recarga — el envío AGS es síncrono al `completar`.

---

## Resolución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| Moodle no puede contactar `/lti/jwks` | Backend no escucha en `0.0.0.0` o URL incorrecta | Verificar `--host=0.0.0.0` y `APP_URL` |
| Error "Invalid issuer" en launch | `LtiPlatform.issuer` no coincide con el JWT de Moodle | Re-ejecutar el comando de registro con el issuer correcto |
| No aparece la calificación | AGS desactivado o `lineitem` vacío | Verificar que "IMS LTI AGS" esté activado en la configuración de la herramienta |
| "The command does not exist" | Comando no registrado | Verificar que `LtiRegistrarPlataforma` esté en `app/Console/Commands/` |
| OIDC loop / cookie error | `APP_URL` no coincide con la URL que usa el navegador | Asegurar que `APP_URL=http://host.docker.internal:8000` y que el navegador también use esa URL |
