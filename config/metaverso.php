<?php

return [
    'magic_link_ttl_minutes' => (int) env('MAGIC_LINK_TTL_MINUTES', 120),
    'deeplink_scheme' => env('GAME_DEEPLINK_SCHEME', 'tecnm-metaverso'),
    'calificacion_min' => 0,
    'calificacion_max' => 100,
    'links_api_key' => env('LINKS_API_KEY'),
];
