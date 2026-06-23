<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\configureStrategy;
use function Knuckles\Scribe\Config\removeStrategies;

return [
    'title' => 'API Metaverso Escolar TecNM',

    'description' => 'API REST consumida por el cliente Unreal Engine del Metaverso Escolar TecNM. Permite generar magic links de sesión, canjearlos por tokens de acceso y registrar sesiones de práctica con calificación.',

    'intro_text' => <<<'INTRO'
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
INTRO,

    'base_url' => 'http://localhost:8000',

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [
                'GET /api/user',
            ],
        ],
    ],

    'type' => 'laravel',

    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => 'http://localhost:8000',
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        'default' => false,
        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{TU_ACCESS_TOKEN}',
        'extra_info' => 'Obtén el token llamando a <code>POST /api/game/redeem</code> con un magic link válido. El token es de tipo Bearer y expira según <code>SANCTUM_TOKEN_TTL_MINUTES</code> (por defecto 480 minutos).',
    ],

    'example_languages' => [
        'bash',
        'javascript',
    ],

    'postman' => [
        'enabled' => true,
        'overrides' => [],
    ],

    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => [],
        'generators' => [],
    ],

    'groups' => [
        'default' => 'Endpoints',
        'order' => [
            'Generación de links (docente)',
            'Flujo de juego (Unreal)',
        ],
    ],

    'logo' => false,

    'last_updated' => 'Última actualización: {date:d \d\e F \d\e Y}',

    'examples' => [
        'faker_seed' => 1234,
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,
            Strategies\Responses\ResponseCalls::withSettings(
                only: [],
                config: [
                    'app.debug' => false,
                ]
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        'serializer' => null,
    ],
];
