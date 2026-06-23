# Introduction

API REST consumida por el cliente Unreal Engine del Metaverso Escolar TecNM. Permite generar magic links de sesión, canjearlos por tokens de acceso y registrar sesiones de práctica con calificación.

<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>

Esta documentación describe todos los endpoints de la API del Metaverso Escolar TecNM.

## Flujo general

El flujo completo es:

1. **Docente/Sistema** genera un magic link (`POST /api/links`) usando `X-Api-Key`.
2. El alumno abre la URL web (`/jugar/{token}`), que dispara el **deeplink** al cliente Unreal.
3. **Unreal** canjea el token (`POST /api/game/redeem`) y obtiene un Bearer token de Sanctum.
4. Con ese Bearer token, Unreal llama `GET /api/game/me`, `POST /api/game/sessions` y `POST /api/game/sessions/{id}/complete`.

## Diagrama de secuencia

```
sequenceDiagram
    participant D as Docente/Sistema
    participant W as Web (/jugar)
    participant U as Unreal
    participant API as Backend API
    D->>API: POST /api/links (X-Api-Key)
    API-->>D: { url, deeplink, expira }
    D->>W: comparte el link al alumno
    W->>U: deeplink tecnm-metaverso://play?token=...
    U->>API: POST /api/game/redeem { token }
    API-->>U: { access_token (Bearer), alumno, practica, evento }
    U->>API: POST /api/game/sessions { id_evento } (Bearer)
    API-->>U: { id_sesion, estatus: en_progreso }
    U->>API: POST /api/game/sessions/{id}/complete { calificacion, datos_resultado }
    API-->>U: { estatus: completada, calificacion }
```

## Autenticación

Los endpoints de `/api/game/*` (excepto `redeem`) requieren el header `Authorization: Bearer <token>` obtenido del canjeo del magic link. El token tiene una capacidad (`ability`) `game` y expira según `SANCTUM_TOKEN_TTL_MINUTES` (por defecto 480 minutos).

