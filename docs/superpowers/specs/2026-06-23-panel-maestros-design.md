# Diseño — Panel de Maestros (Fase 2a)

**Fecha:** 2026-06-23
**Estado:** Aprobado para implementación

## 1. Contexto

El backend del Metaverso Escolar ya tiene la API del juego, magic links y
calificaciones por sesión. Falta una **interfaz para maestros** que les permita,
sin tocar la base de datos ni la consola:
- ver sus grupos y alumnos,
- generar magic links para todo un grupo de un clic,
- ver los resultados/calificaciones de las sesiones.

Este panel es **independiente de LTI** y **compatible** con él: cuando se agregue
LTI, las sesiones lanzadas desde Moodle aparecerán en el mismo panel (mismas
tablas), y el inicio de sesión del panel se reutilizará desde el launch de LTI.

## 2. Decisiones tomadas (brainstorming)

| Decisión | Elección |
|---|---|
| Login del maestro | **Magic link de maestro** vía URL firmada temporal de Laravel (`temporarySignedRoute`). Sin tabla nueva ni SMTP obligatorio. |
| Compatibilidad LTI | El inicio de sesión del panel es **un solo método** (`Auth::login($usuario)`); hoy lo dispara el magic link, mañana el launch de LTI. |
| Roles con acceso | **Maestro** (ve lo suyo) y **Coordinador/Admin** (ven todo). Alumno NO entra. |
| Funciones v1 | Ver grupos y alumnos; generar links de todo un grupo; ver resultados/calificaciones. |
| Tecnología | Blade server-rendered, mismo estilo visual que `/demo`. |
| Fuera de alcance | Crear prácticas/eventos desde el panel; envío de notas a Moodle (eso es LTI). |

## 3. Autenticación

- **Guard web** usando el modelo `Usuario` (ya extiende `Authenticatable`). Se
  configura un provider `usuarios` en `config/auth.php` y el guard web lo usa.
- **Acceso al panel (magic link de maestro):**
  - Ruta firmada `GET /panel/acceso/{usuario}` generada con
    `URL::temporarySignedRoute('panel.acceso', now()->addMinutes($ttl), ['usuario' => $id])`.
  - TTL configurable: `PANEL_LOGIN_TTL_MINUTES`, default 30.
  - Al visitarla con firma válida: si el `Usuario` tiene rol Maestro/Coordinador/Admin,
    se hace `Auth::login($usuario)` y se redirige al dashboard. Si la firma es
    inválida/expirada → 403. Si el rol es Alumno → 403.
  - **En v1 el enlace se genera con el comando** `artisan metaverso:panel-acceso {id_usuario}`
    que imprime la URL firmada (sirve de bootstrap para el primer Coordinador/Admin
    y para los maestros). Generar enlaces de otros maestros *desde la UI del panel*
    queda fuera de v1.
- **Logout:** `POST /panel/salir`.
- **Middleware:** un middleware `panel` que exige sesión autenticada y rol en
  {Maestro, Coordinador, Admin}; rechaza Alumno con 403.

### LTI-readiness
El acto de "iniciar sesión en el panel" se encapsula en un método único
(p. ej. `Auth::login($usuario)` dentro de un `PanelLoginController::establecerSesion`).
El futuro `LtiLaunchController` validará el JWT de Moodle y llamará al mismo
método. El panel no cambia.

## 4. Autorización (qué ve cada quien)

- Helper en el controlador o policy: si el usuario es **Maestro**, las consultas
  se filtran por `grupos.id_maestro = <su maestro.id_maestro>`. Si es
  **Coordinador/Admin**, ve todos los grupos.
- Un Maestro que intente abrir un grupo que no es suyo → **403**.

## 5. Pantallas (Blade)

1. **Dashboard** `GET /panel`
   - Lista de grupos (míos / todos según rol): materia, clave, ciclo, # alumnos.
   - Enlace a cada grupo.
2. **Detalle de grupo** `GET /panel/grupos/{grupo}`
   - Datos del grupo, lista de alumnos inscritos.
   - Lista de eventos/prácticas del grupo.
   - Por evento: botón **"Generar links del grupo"**.
3. **Generar links** `POST /panel/grupos/{grupo}/eventos/{evento}/links`
   - Crea un magic link (vía `MagicLinkService::generar`) para **cada alumno
     inscrito** en el grupo, para ese evento.
   - Muestra una tabla: alumno · matrícula · link (botón copiar).
   - Botón **descargar CSV** (`GET .../links.csv`) con alumno, matrícula, url.
4. **Resultados** `GET /panel/grupos/{grupo}/resultados`
   - Tabla de `sesiones_practica` del grupo: alumno, práctica, estatus,
     calificación, fecha. Filtro opcional por evento/práctica.
   - Ver telemetría (`datos_resultado`) de una sesión: `GET /panel/sesiones/{sesion}`.

## 6. Seguridad

- Todas las rutas `/panel/*` (excepto `panel.acceso`) bajo middleware `panel`.
- Maestro no accede a grupos/sesiones de otro maestro (autorización por dueño).
- Enlaces de acceso firmados y con caducidad; rol Alumno bloqueado.
- `contrasena_hash` nunca se serializa (ya está en `$hidden`).
- La generación de links desde el panel reutiliza `MagicLinkService` (token
  hasheado, un solo uso, TTL del juego).

## 7. Manejo de errores

- Firma inválida/expirada → 403 con página amigable ("enlace caducado, pide otro").
- Acceso a recurso ajeno → 403.
- Grupo/sesión inexistente → 404.

## 8. Pruebas (TDD)

1. Acceso con enlace firmado válido (rol Maestro) → inicia sesión, 200 en dashboard.
2. Enlace con firma manipulada o expirada → 403.
3. Usuario con rol Alumno con enlace válido → 403 (no entra al panel).
4. Maestro ve solo sus grupos; abrir grupo de otro maestro → 403.
5. Coordinador ve todos los grupos.
6. "Generar links del grupo" crea exactamente N tokens (uno por alumno inscrito)
   para el evento.
7. Resultados muestra las sesiones del grupo.

## 9. Entregables

- Config de guard/provider `usuarios` en `config/auth.php`.
- Middleware `panel` + controladores (`PanelLoginController`, `PanelController`,
  `PanelLinkController`, `PanelResultadoController`).
- Vistas Blade con layout compartido (estilo del `/demo`).
- Comando `artisan metaverso:panel-acceso {id_usuario}`.
- `PANEL_LOGIN_TTL_MINUTES` en `.env.example` (default 30).
- Suite de tests de feature de los 7 escenarios.

## 10. Fuera de alcance (siguientes fases)

- Crear/editar prácticas y eventos desde el panel.
- Integración LTI 1.3 con Moodle (sub-proyecto 2: login/launch/jwks + AGS).
- Gestión de usuarios/altas masivas.
