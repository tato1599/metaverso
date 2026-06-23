# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {TU_ACCESS_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Obtén el token llamando a <code>POST /api/game/redeem</code> con un magic link válido. El token es de tipo Bearer y expira según <code>SANCTUM_TOKEN_TTL_MINUTES</code> (por defecto 480 minutos).
