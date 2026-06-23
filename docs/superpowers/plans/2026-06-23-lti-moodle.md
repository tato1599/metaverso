# Integración LTI 1.3 con Moodle — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que un alumno entre al Metaverso desde una actividad de Moodle (LTI 1.3, identidad automática), que el maestro elija la práctica por Deep Linking, y que la calificación del juego regrese sola a Moodle (AGS).

**Architecture:** Usamos la librería `packbackbooks/lti-1p3-tool` para OIDC login, validación del launch (JWT), Deep Linking y AGS. Implementamos sus interfaces (`IDatabase`, `ICache`, `ICookie`) sobre tablas propias (`lti_platforms`, `lti_nonces`, `lti_keys`). Endpoints nuevos `/lti/*`. Las sesiones por LTI se anclan a una práctica (`id_evento` nullable) y guardan los datos de AGS para devolver la nota desde el endpoint `complete` existente.

**Tech Stack:** Laravel 12, PHP 8.5, PostgreSQL, `packbackbooks/lti-1p3-tool` (^6), `firebase/php-jwt` (dependencia transitiva), Pest, Moodle 5.0.1 en Docker.

## Global Constraints

- **Paquete LTI:** `packbackbooks/lti-1p3-tool` (NO `lti-1-3-php-library`). Namespace `Packback\Lti1p3`. Fijar a la versión 6.x instalada.
- **Verificación de API de la librería:** para cada tarea que llame a la librería, el implementador DEBE confirmar las firmas reales contra `vendor/packbackbooks/lti-1p3-tool/src` antes de codificar; el código de la librería en este plan es una guía a reconciliar con la versión instalada. Los tests definen el comportamiento esperado.
- Tablas/PK en español donde aplica a lo existente; las tablas LTI usan `id` estándar (son infraestructura, no del dominio académico).
- `sesiones_practica.id_evento` pasa a **nullable**; sesiones LTI: `id_practica` obligatorio, `id_evento` NULL.
- `usuarios.lti_user_id` (string nullable, indexado) para auto-aprovisionamiento.
- Calificación 0–100 → AGS `scoreMaximum=100`.
- Redes locales: navegador y contenedor Moodle usan la MISMA URL de la herramienta vía `host.docker.internal` (se configura en la tarea de integración); server-to-server backend→Moodle usa `http://localhost:8080`.
- LTI sobre http SOLO para este entorno de desarrollo local.
- Cada tarea termina con tests verdes y commit. PostgreSQL local: `postgres`/`postgres`, DBs `metaverso`/`metaverso_test`. `php artisan config:clear` antes de tests si hay errores de conexión.

---

### Task 1: Instalar la librería + migraciones LTI + modelos

**Files:**
- Modify: `composer.json` (require `packbackbooks/lti-1p3-tool`)
- Create: `database/migrations/*_create_lti_platforms_table.php`, `*_create_lti_keys_table.php`, `*_create_lti_nonces_table.php`, `*_add_lti_fields_to_usuarios_table.php`, `*_make_id_evento_nullable_and_add_lti_to_sesiones.php`
- Create: `app/Models/LtiPlatform.php`, `LtiKey.php`, `LtiNonce.php`
- Test: `tests/Feature/Lti/SchemaLtiTest.php`

**Interfaces:**
- Produces: tablas `lti_platforms` (id, issuer, client_id, auth_login_url, auth_token_url, jwks_url, deployment_id, activo), `lti_keys` (id, kid, public_key, private_key, activo), `lti_nonces` (id, nonce, expira); columnas `usuarios.lti_user_id`, `sesiones_practica.{lti_platform_id, ags_lineitem_url, ags_endpoint}` + `id_evento` nullable. Modelos `LtiPlatform`, `LtiKey`, `LtiNonce` (PK `id`, `$guarded=[]`).

- [ ] **Step 1: Instalar la librería**

```bash
composer require packbackbooks/lti-1p3-tool
php artisan --version   # sanity
```
Confirmar que quedó en `composer.json` y anotar la versión instalada (esperado ^6).

- [ ] **Step 2: Escribir el test de esquema**

`tests/Feature/Lti/SchemaLtiTest.php`:
```php
<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea el esquema LTI', function () {
    foreach (['lti_platforms','lti_keys','lti_nonces'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue();
    }
    expect(Schema::hasColumns('lti_platforms', ['issuer','client_id','auth_login_url','auth_token_url','jwks_url','deployment_id','activo']))->toBeTrue();
    expect(Schema::hasColumns('lti_keys', ['kid','public_key','private_key','activo']))->toBeTrue();
    expect(Schema::hasColumn('usuarios','lti_user_id'))->toBeTrue();
    expect(Schema::hasColumns('sesiones_practica', ['lti_platform_id','ags_lineitem_url','ags_endpoint']))->toBeTrue();
});

it('permite id_evento nulo en sesiones_practica', function () {
    $col = Schema::getConnection()->getDoctrineColumn('sesiones_practica', 'id_evento');
})->skip('verificado por inserción en Task 6');
```

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php artisan test --filter=SchemaLtiTest`
Expected: FAIL.

- [ ] **Step 4: Migración `lti_platforms`**

```php
Schema::create('lti_platforms', function (Blueprint $t) {
    $t->id();
    $t->string('issuer');
    $t->string('client_id');
    $t->string('auth_login_url');
    $t->string('auth_token_url');
    $t->string('jwks_url');
    $t->string('deployment_id')->nullable();
    $t->boolean('activo')->default(true);
    $t->timestamps();
    $t->unique(['issuer', 'client_id']);
});
```

- [ ] **Step 5: Migración `lti_keys`**

```php
Schema::create('lti_keys', function (Blueprint $t) {
    $t->id();
    $t->string('kid')->unique();
    $t->text('public_key');
    $t->text('private_key');
    $t->boolean('activo')->default(true);
    $t->timestamps();
});
```

- [ ] **Step 6: Migración `lti_nonces`**

```php
Schema::create('lti_nonces', function (Blueprint $t) {
    $t->id();
    $t->string('nonce')->index();
    $t->dateTime('expira');
    $t->timestamps();
});
```

- [ ] **Step 7: Migración columnas en `usuarios`**

```php
Schema::table('usuarios', function (Blueprint $t) {
    $t->string('lti_user_id')->nullable()->index();
});
```

- [ ] **Step 8: Migración cambios en `sesiones_practica`**

Postgres permite cambiar a nullable sin doctrine/dbal en Laravel 11+ (`->nullable()->change()`), pero para evitar dependencias usar SQL crudo para el cambio de nullability:
```php
public function up(): void {
    Schema::table('sesiones_practica', function (Blueprint $t) {
        $t->foreignId('lti_platform_id')->nullable()->constrained('lti_platforms')->nullOnDelete();
        $t->string('ags_lineitem_url')->nullable();
        $t->string('ags_endpoint')->nullable();
    });
    DB::statement('ALTER TABLE sesiones_practica ALTER COLUMN id_evento DROP NOT NULL');
}
public function down(): void {
    Schema::table('sesiones_practica', function (Blueprint $t) {
        $t->dropConstrainedForeignId('lti_platform_id');
        $t->dropColumn(['ags_lineitem_url','ags_endpoint']);
    });
    DB::statement('ALTER TABLE sesiones_practica ALTER COLUMN id_evento SET NOT NULL');
}
```
(Importar `use Illuminate\Support\Facades\DB;` arriba.)

- [ ] **Step 9: Modelos**

`app/Models/LtiPlatform.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LtiPlatform extends Model {
    protected $table = 'lti_platforms';
    protected $guarded = [];
    protected $casts = ['activo' => 'boolean'];
}
```
`app/Models/LtiKey.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LtiKey extends Model {
    protected $table = 'lti_keys';
    protected $guarded = [];
    protected $casts = ['activo' => 'boolean'];
}
```
`app/Models/LtiNonce.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LtiNonce extends Model {
    protected $table = 'lti_nonces';
    protected $guarded = [];
    protected $casts = ['expira' => 'datetime'];
}
```

- [ ] **Step 10: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=SchemaLtiTest`
Expected: PASS. Luego correr la suite completa (no debe romper nada).

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat(lti): instalar libreria + esquema LTI (plataformas, llaves, nonces, sesion)"
```

---

### Task 2: Generar par de llaves de la herramienta (comando) + endpoint JWKS

**Files:**
- Create: `app/Console/Commands/LtiGenerarLlaves.php`
- Create: `app/Http/Controllers/Lti/JwksController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Lti/JwksTest.php`

**Interfaces:**
- Consumes: `LtiKey`.
- Produces: comando `metaverso:lti-generar-llaves` (crea un `LtiKey` activo con RSA 2048 + `kid` aleatorio). Ruta `GET /lti/jwks` (`lti.jwks`) → JSON JWKS con la(s) llave(s) pública(s) activas: `{ "keys": [ { kty, e, n, kid, alg:"RS256", use:"sig" } ] }`.

- [ ] **Step 1: Escribir el test**

`tests/Feature/Lti/JwksTest.php`:
```php
<?php
use App\Models\LtiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('genera un par de llaves con el comando', function () {
    $this->artisan('metaverso:lti-generar-llaves')->assertSuccessful();
    $k = LtiKey::first();
    expect($k)->not->toBeNull();
    expect($k->kid)->not->toBeEmpty();
    expect($k->private_key)->toContain('PRIVATE KEY');
    expect($k->public_key)->toContain('PUBLIC KEY');
});

it('publica un JWKS valido con la llave publica', function () {
    $this->artisan('metaverso:lti-generar-llaves')->assertSuccessful();
    $r = $this->get('/lti/jwks');
    $r->assertOk()->assertJsonStructure(['keys' => [['kty','e','n','kid','alg','use']]]);
    expect($r->json('keys.0.alg'))->toBe('RS256');
    expect($r->json('keys.0.kid'))->toBe(LtiKey::first()->kid);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=JwksTest`
Expected: FAIL.

- [ ] **Step 3: Implementar el comando**

`app/Console/Commands/LtiGenerarLlaves.php`:
```php
<?php
namespace App\Console\Commands;

use App\Models\LtiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LtiGenerarLlaves extends Command {
    protected $signature = 'metaverso:lti-generar-llaves';
    protected $description = 'Genera un par de llaves RSA para firmar mensajes LTI de la herramienta';

    public function handle(): int {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $privatePem);
        $publicPem = openssl_pkey_get_details($res)['key'];

        $key = LtiKey::create([
            'kid' => (string) Str::uuid(),
            'private_key' => $privatePem,
            'public_key' => $publicPem,
            'activo' => true,
        ]);
        $this->info('Llave LTI generada. kid: '.$key->kid);
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Implementar el controlador JWKS**

El JWKS expone la pública en formato JWK (n, e en base64url). Construir con la propia librería si expone un helper (`Packback\Lti1p3\JwksEndpoint`); VERIFICAR la API instalada. Implementación independiente (no depende de la librería) para mayor robustez:
`app/Http/Controllers/Lti/JwksController.php`:
```php
<?php
namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Models\LtiKey;

class JwksController extends Controller {
    public function index() {
        $keys = LtiKey::where('activo', true)->get()->map(function (LtiKey $k) {
            $details = openssl_pkey_get_details(openssl_pkey_get_public($k->public_key));
            return [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $k->kid,
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ];
        })->values();
        return response()->json(['keys' => $keys]);
    }
}
```

- [ ] **Step 5: Registrar la ruta**

En `routes/web.php`:
```php
use App\Http\Controllers\Lti\JwksController;
Route::get('/lti/jwks', [JwksController::class, 'index'])->name('lti.jwks');
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=JwksTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(lti): comando de llaves RSA + endpoint JWKS"
```

---

### Task 3: Adaptadores de la librería (IDatabase, ICache, ICookie)

**Files:**
- Create: `app/Lti/LtiDatabase.php`, `app/Lti/LtiCache.php`, `app/Lti/LtiCookie.php`
- Test: `tests/Feature/Lti/LtiDatabaseTest.php`

**Interfaces:**
- Consumes: interfaces `Packback\Lti1p3\Interfaces\IDatabase`, `ICache`, `ICookie` (VERIFICAR métodos exactos en `vendor/packbackbooks/lti-1p3-tool/src/Interfaces`). `LtiPlatform`, `LtiKey`, `LtiNonce`.
- Produces:
  - `LtiDatabase implements IDatabase` — `findRegistrationByIssuer(string $iss, ?string $clientId = null): ?LtiRegistration` y `findDeployment(string $iss, string $deploymentId, ?string $clientId = null): ?LtiDeployment`. Construye `LtiRegistration` con `setIssuer/setClientId/setAuthLoginUrl/setAuthTokenUrl/setKeySetUrl/setKid/setToolPrivateKey` desde `LtiPlatform` + la `LtiKey` activa; `getKeySetUrl` apunta a la JWKS de la PLATAFORMA (`jwks_url`), y la `kid`/`toolPrivateKey` son de NUESTRA `LtiKey`.
  - `LtiCache implements ICache` — guarda/lee el `launch data` por nonce/key. Implementar sobre `cache()` de Laravel + `LtiNonce` para nonces.
  - `LtiCookie implements ICookie` — get/set sobre cookies de la request.

Nota de versión: en `packbackbooks/lti-1p3-tool` v6 los nombres de método pueden ser `findRegistrationByIssuer`, `findDeployment`, `cacheLaunchData`/`getLaunchData`, `cacheNonce`/`checkNonceIsValid`, `getCookie`/`setCookie`. CONFIRMAR contra el `vendor/` y ajustar firmas/return types a las de la interfaz instalada (los `implements` obligan a coincidir).

- [ ] **Step 1: Inspeccionar las interfaces instaladas (no es código, es prerequisito)**

```bash
ls vendor/packbackbooks/lti-1p3-tool/src/Interfaces
sed -n '1,80p' vendor/packbackbooks/lti-1p3-tool/src/Interfaces/IDatabase.php
sed -n '1,80p' vendor/packbackbooks/lti-1p3-tool/src/Interfaces/ICache.php
sed -n '1,80p' vendor/packbackbooks/lti-1p3-tool/src/Interfaces/ICookie.php
```
Anotar las firmas EXACTAS; el código de abajo se ajusta a ellas.

- [ ] **Step 2: Escribir el test del IDatabase**

`tests/Feature/Lti/LtiDatabaseTest.php`:
```php
<?php
use App\Models\{LtiPlatform, LtiKey};
use App\Lti\LtiDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resuelve un registration desde una LtiPlatform y la LtiKey activa', function () {
    LtiKey::create(['kid' => 'kid-1', 'public_key' => 'PUB', 'private_key' => "-----BEGIN PRIVATE KEY-----\nX\n-----END PRIVATE KEY-----", 'activo' => true]);
    $p = LtiPlatform::create([
        'issuer' => 'http://localhost:8080', 'client_id' => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1', 'activo' => true,
    ]);

    $db = new LtiDatabase();
    $reg = $db->findRegistrationByIssuer('http://localhost:8080', 'CID');
    expect($reg)->not->toBeNull();
    expect($reg->getClientId())->toBe('CID');
    expect($reg->getAuthLoginUrl())->toBe('http://localhost:8080/mod/lti/auth.php');
    expect($reg->getKid())->toBe('kid-1');

    $dep = $db->findDeployment('http://localhost:8080', 'DEP1', 'CID');
    expect($dep)->not->toBeNull();
});
```

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php artisan test --filter=LtiDatabaseTest`
Expected: FAIL.

- [ ] **Step 4: Implementar `LtiDatabase`**

`app/Lti/LtiDatabase.php` (ajustar a la interfaz real verificada en Step 1):
```php
<?php
namespace App\Lti;

use App\Models\LtiKey;
use App\Models\LtiPlatform;
use Packback\Lti1p3\Interfaces\IDatabase;
use Packback\Lti1p3\LtiRegistration;
use Packback\Lti1p3\LtiDeployment;

class LtiDatabase implements IDatabase {
    public function findRegistrationByIssuer($iss, $clientId = null): ?LtiRegistration {
        $q = LtiPlatform::where('issuer', $iss)->where('activo', true);
        if ($clientId) $q->where('client_id', $clientId);
        $p = $q->first();
        if (! $p) return null;
        $key = LtiKey::where('activo', true)->firstOrFail();

        return LtiRegistration::new()
            ->setIssuer($p->issuer)
            ->setClientId($p->client_id)
            ->setAuthLoginUrl($p->auth_login_url)
            ->setAuthTokenUrl($p->auth_token_url)
            ->setKeySetUrl($p->jwks_url)
            ->setKid($key->kid)
            ->setToolPrivateKey($key->private_key);
    }

    public function findDeployment($iss, $deploymentId, $clientId = null): ?LtiDeployment {
        $q = LtiPlatform::where('issuer', $iss)->where('deployment_id', $deploymentId)->where('activo', true);
        if ($clientId) $q->where('client_id', $clientId);
        if (! $q->exists()) return null;
        return LtiDeployment::new()->setDeploymentId($deploymentId);
    }
}
```

- [ ] **Step 5: Implementar `LtiCache` y `LtiCookie`**

`app/Lti/LtiCache.php` (ajustar nombres a la interfaz real):
```php
<?php
namespace App\Lti;

use Illuminate\Support\Facades\Cache;
use Packback\Lti1p3\Interfaces\ICache;

class LtiCache implements ICache {
    public function getLaunchData($key) { return Cache::get('lti_launch_'.$key); }
    public function cacheLaunchData($key, $jwtBody): void { Cache::put('lti_launch_'.$key, $jwtBody, now()->addHour()); }
    public function cacheNonce($nonce, $state): void { Cache::put('lti_nonce_'.$nonce, $state, now()->addHour()); }
    public function checkNonceIsValid($nonce, $state): bool {
        return Cache::pull('lti_nonce_'.$nonce) === $state;
    }
}
```
`app/Lti/LtiCookie.php`:
```php
<?php
namespace App\Lti;

use Packback\Lti1p3\Interfaces\ICookie;

class LtiCookie implements ICookie {
    public function getCookie($name) { return $_COOKIE[$name] ?? null; }
    public function setCookie($name, $value, $exp = 3600, $options = []): void {
        setcookie($name, $value, [
            'expires' => time() + $exp, 'path' => '/', 'samesite' => 'None', 'secure' => false, 'httponly' => true,
        ]);
        $_COOKIE[$name] = $value;
    }
}
```
(IMPORTANTE: los nombres/firmas de método deben coincidir EXACTAMENTE con las interfaces instaladas. Reconciliar con Step 1.)

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=LtiDatabaseTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(lti): adaptadores IDatabase/ICache/ICookie de la libreria"
```

---

### Task 4: OIDC login (`/lti/login`)

**Files:**
- Create: `app/Http/Controllers/Lti/LtiLoginController.php`
- Modify: `routes/web.php`, `bootstrap/app.php` (excluir `/lti/*` de CSRF)
- Test: `tests/Feature/Lti/OidcLoginTest.php`

**Interfaces:**
- Consumes: `Packback\Lti1p3\LtiOidcLogin` (VERIFICAR constructor y método de redirect en vendor), `LtiDatabase`, `LtiCache`, `LtiCookie`.
- Produces: rutas `GET /lti/login` y `POST /lti/login` (`lti.login`) que ejecutan el redirect OIDC de vuelta a `auth_login_url` de la plataforma con `redirect_uri = route('lti.launch')`. `/lti/*` excluidas de la verificación CSRF (Moodle hace POST cross-site).

- [ ] **Step 1: Excluir `/lti/*` de CSRF**

En `bootstrap/app.php`, dentro de `->withMiddleware(...)`:
```php
$middleware->validateCsrfTokens(except: ['lti/*']);
```

- [ ] **Step 2: Escribir el test**

`tests/Feature/Lti/OidcLoginTest.php`:
```php
<?php
use App\Models\{LtiPlatform, LtiKey};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    LtiKey::create(['kid' => 'k1', 'public_key' => 'PUB', 'private_key' => 'PRIV', 'activo' => true]);
    LtiPlatform::create([
        'issuer' => 'http://localhost:8080', 'client_id' => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1', 'activo' => true,
    ]);
});

it('redirige el OIDC login a la plataforma con los parametros requeridos', function () {
    $params = [
        'iss' => 'http://localhost:8080',
        'login_hint' => 'user-123',
        'target_link_uri' => route('lti.launch'),
        'client_id' => 'CID',
        'lti_deployment_id' => 'DEP1',
    ];
    $r = $this->get('/lti/login?'.http_build_query($params));
    $r->assertRedirect();
    expect($r->headers->get('Location'))->toContain('http://localhost:8080/mod/lti/auth.php');
    expect($r->headers->get('Location'))->toContain('redirect_uri=');
    expect($r->headers->get('Location'))->toContain('client_id=CID');
});
```

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php artisan test --filter=OidcLoginTest`
Expected: FAIL.

- [ ] **Step 4: Implementar el controlador**

`app/Http/Controllers/Lti/LtiLoginController.php` (reconciliar con la API de `LtiOidcLogin` instalada — en v6 suele ser `LtiOidcLogin::new($db, $cache, $cookie)->doOidcLoginRedirect($launchUrl, $request)` y el resultado expone `->getRedirectUrl()`):
```php
<?php
namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Lti\LtiCache;
use App\Lti\LtiCookie;
use App\Lti\LtiDatabase;
use Illuminate\Http\Request;
use Packback\Lti1p3\LtiOidcLogin;

class LtiLoginController extends Controller {
    public function login(Request $request) {
        $login = LtiOidcLogin::new(new LtiDatabase(), new LtiCache(), new LtiCookie());
        $redirect = $login->doOidcLoginRedirect(route('lti.launch'), $request->all());
        // VERIFICAR: el objeto Redirect de la libreria expone la URL (p.ej. getRedirectUrl()).
        return redirect()->away($redirect->getRedirectUrl());
    }
}
```

- [ ] **Step 5: Registrar rutas**

En `routes/web.php`:
```php
use App\Http\Controllers\Lti\LtiLoginController;
Route::match(['get','post'], '/lti/login', [LtiLoginController::class, 'login'])->name('lti.login');
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=OidcLoginTest`
Expected: PASS. Si la firma de `doOidcLoginRedirect`/`getRedirectUrl` difiere en la versión instalada, ajustar el controlador hasta que el test (redirect a `auth_login_url` con `redirect_uri` y `client_id`) pase.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(lti): endpoint OIDC login con redirect a la plataforma"
```

---

### Task 5: Servicio de aprovisionamiento de alumno

**Files:**
- Create: `app/Lti/AprovisionarAlumno.php`
- Test: `tests/Feature/Lti/AprovisionarAlumnoTest.php`

**Interfaces:**
- Consumes: `Usuario`, `Alumno`, `Rol`, `Carrera`.
- Produces: `AprovisionarAlumno::desdeLaunch(string $ltiUserId, string $nombre, string $apellidos, ?string $correo): Alumno`. Busca `Usuario` por `lti_user_id`; si no existe lo crea (rol Alumno, correo único — si falta o colisiona, genera uno determinístico tipo `lti+{ltiUserId}@metaverso.local`) junto con su `Alumno` (carrera "LTI/Externa" creada on-demand, matrícula = `LTI-{ltiUserId}`). Idempotente: segunda llamada con el mismo `ltiUserId` devuelve el mismo `Alumno`.

- [ ] **Step 1: Escribir el test**

`tests/Feature/Lti/AprovisionarAlumnoTest.php`:
```php
<?php
use App\Lti\AprovisionarAlumno;
use App\Models\{Usuario, Alumno};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea el alumno la primera vez y lo reutiliza despues', function () {
    $svc = new AprovisionarAlumno();
    $a1 = $svc->desdeLaunch('moodle-user-9', 'Ana', 'Ruiz', 'ana@correo.com');
    expect($a1)->toBeInstanceOf(Alumno::class);
    expect(Usuario::where('lti_user_id', 'moodle-user-9')->count())->toBe(1);

    $a2 = $svc->desdeLaunch('moodle-user-9', 'Ana', 'Ruiz', 'ana@correo.com');
    expect($a2->id_alumno)->toBe($a1->id_alumno);
    expect(Alumno::count())->toBe(1);
});

it('genera correo deterministico si no hay correo', function () {
    $svc = new AprovisionarAlumno();
    $a = $svc->desdeLaunch('mu-5', 'Beto', 'Diaz', null);
    expect($a->usuario->correo)->toContain('lti+mu-5@');
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=AprovisionarAlumnoTest`
Expected: FAIL.

- [ ] **Step 3: Implementar el servicio**

`app/Lti/AprovisionarAlumno.php`:
```php
<?php
namespace App\Lti;

use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class AprovisionarAlumno {
    public function desdeLaunch(string $ltiUserId, string $nombre, string $apellidos, ?string $correo): Alumno {
        return DB::transaction(function () use ($ltiUserId, $nombre, $apellidos, $correo) {
            $usuario = Usuario::where('lti_user_id', $ltiUserId)->first();
            if ($usuario && $usuario->alumno) {
                return $usuario->alumno;
            }

            $rolAlumno = Rol::firstOrCreate(['nombre' => 'Alumno']);
            $carrera = Carrera::firstOrCreate(
                ['clave' => 'LTI'],
                ['nombre' => 'Externa (LTI)', 'duracion_semestres' => 1]
            );

            if (! $usuario) {
                $correoFinal = $correo ?: "lti+{$ltiUserId}@metaverso.local";
                if (Usuario::where('correo', $correoFinal)->exists()) {
                    $correoFinal = "lti+{$ltiUserId}@metaverso.local";
                }
                $usuario = Usuario::create([
                    'id_rol' => $rolAlumno->id_rol,
                    'correo' => $correoFinal,
                    'nombre' => $nombre ?: 'Alumno',
                    'apellidos' => $apellidos ?: 'LTI',
                    'lti_user_id' => $ltiUserId,
                ]);
            }

            return Alumno::create([
                'id_usuario' => $usuario->id_usuario,
                'id_carrera' => $carrera->id_carrera,
                'matricula' => 'LTI-'.$ltiUserId,
                'semestre_actual' => 1,
                'generacion' => date('Y'),
            ]);
        });
    }
}
```
Nota: `date('Y')` es determinístico por año, aceptable aquí (no se usa Date::now aleatorio).

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=AprovisionarAlumnoTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(lti): aprovisionamiento idempotente de alumno desde el launch"
```

---

### Task 6: Launch (`/lti/launch`) — resource link → sesión + "Abrir juego"

**Files:**
- Create: `app/Http/Controllers/Lti/LtiLaunchController.php`
- Create: `resources/views/lti/abrir-juego.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Lti/LaunchTest.php`

**Interfaces:**
- Consumes: `Packback\Lti1p3\LtiMessageLaunch` (VERIFICAR constructor/validate/getLaunchData/isResourceLaunch/isDeepLinkLaunch/hasAgs/getAgs en vendor), `LtiDatabase`, `LtiCache`, `LtiCookie`, `AprovisionarAlumno`, `MagicLinkService`, `SesionPractica`, `Practica`.
- Produces: ruta `POST /lti/launch` (`lti.launch`). Para un resource-link launch: valida, extrae claims (sub=lti_user_id, name, email, custom `id_practica`, AGS endpoint/lineitem), aprovisiona alumno, crea `SesionPractica` (id_practica del custom, id_evento NULL, estatus en_progreso, lti_platform_id, ags_lineitem_url, ags_endpoint), genera un magic token de juego ligado y muestra `lti.abrir-juego` con el deeplink. Para un deep-linking launch: redirige a `lti.deeplink` (Task 7).

**Reto de testing:** validar un `LtiMessageLaunch` real requiere un `id_token` JWT firmado por una "plataforma" y que la librería pueda obtener su JWKS. Estrategia: en el test, generar un par RSA de prueba (la "plataforma"), exponer su JWKS vía un fake HTTP (Laravel `Http::fake()` no aplica si la librería usa cURL propio — por eso) usando un **`ServiceConnector` inyectable falso** o mockeando `LtiMessageLaunch`. El enfoque más robusto y desacoplado: **extraer la validación detrás de una interfaz propia** `LaunchValidador` con un método `validar(Request): DatosLaunch` y en los tests inyectar un doble que retorna un `DatosLaunch` fijo; la implementación real usa la librería. Así el controlador se testea sin criptografía.

- [ ] **Step 1: Definir el DTO y la interfaz de validación**

`app/Lti/DatosLaunch.php`:
```php
<?php
namespace App\Lti;

class DatosLaunch {
    public function __construct(
        public bool $esDeepLink,
        public string $issuer,
        public string $ltiUserId,
        public string $nombre,
        public string $apellidos,
        public ?string $correo,
        public ?int $idPractica,
        public ?string $agsLineitemUrl,
        public ?string $agsEndpoint,
        public ?int $ltiPlatformId,
    ) {}
}
```
`app/Lti/LaunchValidador.php` (interfaz):
```php
<?php
namespace App\Lti;

use Illuminate\Http\Request;

interface LaunchValidador {
    public function validar(Request $request): DatosLaunch;
}
```

- [ ] **Step 2: Escribir el test (con un validador doble)**

`tests/Feature/Lti/LaunchTest.php`:
```php
<?php
use App\Lti\{DatosLaunch, LaunchValidador};
use App\Models\{LtiPlatform, Materia, Practica, SesionPractica, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function plataformaDemo(): LtiPlatform {
    return LtiPlatform::create([
        'issuer' => 'http://localhost:8080', 'client_id' => 'CID',
        'auth_login_url' => 'http://localhost:8080/mod/lti/auth.php',
        'auth_token_url' => 'http://localhost:8080/mod/lti/token.php',
        'jwks_url' => 'http://localhost:8080/mod/lti/certs.php',
        'deployment_id' => 'DEP1', 'activo' => true,
    ]);
}

it('un resource launch crea sesion LTI y muestra Abrir juego', function () {
    $p = plataformaDemo();
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab LTI']);

    $datos = new DatosLaunch(
        esDeepLink: false, issuer: 'http://localhost:8080',
        ltiUserId: 'mu-77', nombre: 'Ana', apellidos: 'Ruiz', correo: 'ana@c.com',
        idPractica: $practica->id_practica,
        agsLineitemUrl: 'http://localhost:8080/mod/lti/services.php/.../lineitems/1/lineitem',
        agsEndpoint: 'http://localhost:8080/mod/lti/services.php', ltiPlatformId: $p->id,
    );
    $this->app->bind(LaunchValidador::class, fn () => new class($datos) implements LaunchValidador {
        public function __construct(private $d) {}
        public function validar($request): DatosLaunch { return $this->d; }
    });

    $r = $this->post('/lti/launch');
    $r->assertOk()->assertSee('Abrir juego');

    expect(Usuario::where('lti_user_id', 'mu-77')->count())->toBe(1);
    $sesion = SesionPractica::first();
    expect($sesion->id_practica)->toBe($practica->id_practica);
    expect($sesion->id_evento)->toBeNull();
    expect($sesion->ags_lineitem_url)->not->toBeNull();
    expect($sesion->lti_platform_id)->toBe($p->id);
});

it('un deep-link launch redirige al selector', function () {
    plataformaDemo();
    $datos = new DatosLaunch(true, 'http://localhost:8080', 'mu-1', 'M', 'X', null, null, null, null, null);
    $this->app->bind(LaunchValidador::class, fn () => new class($datos) implements LaunchValidador {
        public function __construct(private $d) {}
        public function validar($request): DatosLaunch { return $this->d; }
    });
    $this->post('/lti/launch')->assertRedirect(route('lti.deeplink'));
});
```

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php artisan test --filter=LaunchTest`
Expected: FAIL.

- [ ] **Step 4: Implementar el validador real (usa la librería)**

`app/Lti/LibreriaLaunchValidador.php` (reconciliar con API instalada de `LtiMessageLaunch`):
```php
<?php
namespace App\Lti;

use App\Models\LtiPlatform;
use Illuminate\Http\Request;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;

class LibreriaLaunchValidador implements LaunchValidador {
    public function validar(Request $request): DatosLaunch {
        $connector = new LtiServiceConnector(new LtiCache(), /* http client */ new \GuzzleHttp\Client());
        $launch = LtiMessageLaunch::new(new LtiDatabase(), new LtiCache(), new LtiCookie(), $connector)
            ->validate($request->all());

        $data = $launch->getLaunchData();
        $iss = $data['iss'];
        $platform = LtiPlatform::where('issuer', $iss)->first();

        $custom = $data['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? [];
        $ags = $data['https://purl.imsglobal.org/spec/lti-ags/claim/endpoint'] ?? [];

        return new DatosLaunch(
            esDeepLink: $launch->isDeepLinkLaunch(),
            issuer: $iss,
            ltiUserId: $data['sub'] ?? '',
            nombre: $data['given_name'] ?? ($data['name'] ?? 'Alumno'),
            apellidos: $data['family_name'] ?? 'LTI',
            correo: $data['email'] ?? null,
            idPractica: isset($custom['id_practica']) ? (int) $custom['id_practica'] : null,
            agsLineitemUrl: $ags['lineitem'] ?? null,
            agsEndpoint: $ags['lineitems'] ?? ($ags['lineitem'] ?? null),
            ltiPlatformId: $platform?->id,
        );
    }
}
```
Registrar el binding por defecto en `app/Providers/AppServiceProvider.php` (método `register`):
```php
$this->app->bind(\App\Lti\LaunchValidador::class, \App\Lti\LibreriaLaunchValidador::class);
```

- [ ] **Step 5: Implementar el controlador del launch**

`app/Http/Controllers/Lti/LtiLaunchController.php`:
```php
<?php
namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Lti\AprovisionarAlumno;
use App\Lti\LaunchValidador;
use App\Models\SesionPractica;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class LtiLaunchController extends Controller {
    public function launch(Request $request, LaunchValidador $validador, AprovisionarAlumno $aprovisionar, MagicLinkService $magicLink) {
        $datos = $validador->validar($request);

        if ($datos->esDeepLink) {
            session(['lti_issuer' => $datos->issuer]);
            return redirect()->route('lti.deeplink');
        }

        abort_if(! $datos->idPractica, 422, 'Esta actividad no tiene práctica asignada. Pide al maestro reconfigurarla.');

        $alumno = $aprovisionar->desdeLaunch($datos->ltiUserId, $datos->nombre, $datos->apellidos, $datos->correo);

        $sesion = SesionPractica::create([
            'id_practica' => $datos->idPractica,
            'id_evento' => null,
            'id_alumno' => $alumno->id_alumno,
            'fecha_inicio' => now(),
            'estatus' => 'en_progreso',
            'lti_platform_id' => $datos->ltiPlatformId,
            'ags_lineitem_url' => $datos->agsLineitemUrl,
            'ags_endpoint' => $datos->agsEndpoint,
        ]);

        // Token de juego ligado al usuario; el deeplink lo abre Unreal.
        $res = $magicLink->generar($alumno->id_usuario, $datos->idPractica, 'unreal-lti');
        // NOTA: generar(idUsuario, idEvento, plataforma) espera un id_evento; para LTI
        // no hay evento. Ver Step 6: se ajusta MagicLinkService para aceptar contexto LTI.

        return view('lti.abrir-juego', [
            'deeplink' => $res['deeplink'],
            'practicaId' => $datos->idPractica,
        ]);
    }
}
```

- [ ] **Step 6: Ajustar el flujo de token para LTI (sin evento)**

El `MagicLinkService::generar()` y la tabla `tokens_juego` requieren `id_evento` (FK NOT NULL). Para LTI no hay evento. Decisión mínima: en esta fase, **el token de juego para LTI se liga a la sesión** en vez del evento. Pero para no rediseñar `tokens_juego` ahora, la vista "Abrir juego" del LTI usará el **canje por sesión ya creada**: en lugar de un magic token nuevo, el deeplink lleva un token de sesión LTI corto. Implementación mínima sin tocar `tokens_juego`:
- Generar un token aleatorio, guardarlo hasheado en cache (`Cache::put('lti_play_'.hash, id_sesion, 2h)`).
- El deeplink = `{scheme}://play?lti_session_token={token}`.
- (El endpoint que canjea este token de sesión LTI y arranca/continúa la `SesionPractica` para Unreal se implementa junto con AGS en Task 8, o se reutiliza el redeem con una rama LTI. Para mantener Task 6 testeable, basta con que la vista muestre el deeplink con el token.)

Reemplazar en el controlador las dos líneas de `$magicLink->generar(...)` por:
```php
$token = \Illuminate\Support\Str::random(64);
\Illuminate\Support\Facades\Cache::put('lti_play_'.hash('sha256', $token), $sesion->id_sesion, now()->addHours(2));
$scheme = config('metaverso.deeplink_scheme', 'tecnm-metaverso');
$deeplink = "{$scheme}://play?lti_session_token={$token}";
return view('lti.abrir-juego', ['deeplink' => $deeplink, 'practicaId' => $datos->idPractica]);
```
(Quitar la inyección de `MagicLinkService` del método si ya no se usa.)

- [ ] **Step 7: Crear la vista**

`resources/views/lti/abrir-juego.blade.php`:
```blade
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Abrir juego — Metaverso TecNM</title>
<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#0b1020;color:#fff}.c{text-align:center;padding:2rem}a.btn{display:inline-block;margin-top:1rem;padding:1rem 2rem;background:#4f46e5;color:#fff;border-radius:.75rem;text-decoration:none;font-weight:600}</style>
</head><body><div class="c">
<h1>Tu práctica está lista</h1>
<p>Haz clic para abrir el juego en Unreal Engine.</p>
<a class="btn" href="{{ $deeplink }}">Abrir juego</a>
</div>
<script>window.location.href = @json($deeplink);</script>
</body></html>
```

- [ ] **Step 8: Registrar la ruta + placeholder de deeplink**

En `routes/web.php`:
```php
use App\Http\Controllers\Lti\LtiLaunchController;
Route::post('/lti/launch', [LtiLaunchController::class, 'launch'])->name('lti.launch');
// placeholder de Task 7 (para que el redirect del deep-link resuelva en el test):
Route::get('/lti/deeplink', fn () => 'ok')->name('lti.deeplink');
```

- [ ] **Step 9: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=LaunchTest`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat(lti): launch resource-link crea sesion y muestra Abrir juego"
```

---

### Task 7: Deep Linking (`/lti/deeplink`) — selector de práctica

**Files:**
- Create: `app/Http/Controllers/Lti/LtiDeepLinkController.php`
- Create: `resources/views/lti/seleccionar-practica.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Lti/DeepLinkTest.php`

**Interfaces:**
- Consumes: `Practica`, `Packback\Lti1p3\LtiDeepLinkResource` + el builder de respuesta del launch DL (VERIFICAR en vendor). Para test desacoplado, usar una interfaz `DeepLinkRespondedor` con `construirRespuestaJwt(int $idPractica): string` inyectable (doble en test).
- Produces:
  - `GET /lti/deeplink` (`lti.deeplink`) → vista con la lista de prácticas (form que postea la elegida).
  - `POST /lti/deeplink` (`lti.deeplink.responder`) → arma el DeepLinkingResponse (JWT) y lo auto-postea a la plataforma (form HTML con `JWT` y action = endpoint DL de la plataforma).

- [ ] **Step 1: Definir la interfaz del respondedor**

`app/Lti/DeepLinkRespondedor.php`:
```php
<?php
namespace App\Lti;

interface DeepLinkRespondedor {
    // Retorna [ 'jwt' => string, 'returnUrl' => string ] para auto-postear a Moodle.
    public function construir(int $idPractica): array;
}
```

- [ ] **Step 2: Escribir el test**

`tests/Feature/Lti/DeepLinkTest.php`:
```php
<?php
use App\Lti\DeepLinkRespondedor;
use App\Models\{Materia, Practica};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el selector lista las practicas', function () {
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab Uno']);
    Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab Dos']);

    $this->get(route('lti.deeplink'))->assertOk()->assertSee('Lab Uno')->assertSee('Lab Dos');
});

it('al elegir una practica responde con el JWT auto-posteado a la plataforma', function () {
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab Uno']);

    $this->app->bind(DeepLinkRespondedor::class, fn () => new class implements DeepLinkRespondedor {
        public function construir(int $idPractica): array {
            return ['jwt' => 'JWT-FAKE-'.$idPractica, 'returnUrl' => 'http://localhost:8080/mod/lti/return.php'];
        }
    });

    $r = $this->post(route('lti.deeplink.responder'), ['id_practica' => $practica->id_practica]);
    $r->assertOk()
        ->assertSee('JWT-FAKE-'.$practica->id_practica, false)
        ->assertSee('http://localhost:8080/mod/lti/return.php', false)
        ->assertSee('name="JWT"', false);
});
```

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php artisan test --filter=DeepLinkTest`
Expected: FAIL.

- [ ] **Step 4: Implementar el respondedor real**

`app/Lti/LibreriaDeepLinkRespondedor.php` (reconciliar con API instalada; en v6 el DeepLink se obtiene del launch validado — para Deep Linking el launch se revalida o se guarda en sesión. Implementación pragmática: revalidar el launch DL y usar `$launch->getDeepLink()->getResponseJwt([$resource])`):
```php
<?php
namespace App\Lti;

use App\Models\Practica;
use Illuminate\Http\Request;
use Packback\Lti1p3\LtiDeepLinkResource;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;

class LibreriaDeepLinkRespondedor implements DeepLinkRespondedor {
    public function __construct(private Request $request) {}

    public function construir(int $idPractica): array {
        $connector = new LtiServiceConnector(new LtiCache(), new \GuzzleHttp\Client());
        $launch = LtiMessageLaunch::new(new LtiDatabase(), new LtiCache(), new LtiCookie(), $connector)
            ->validate($this->request->all());

        $practica = Practica::findOrFail($idPractica);
        $resource = LtiDeepLinkResource::new()
            ->setUrl(route('lti.launch'))
            ->setTitle($practica->titulo)
            ->setCustomParams(['id_practica' => (string) $practica->id_practica]);

        $deepLink = $launch->getDeepLink();
        return [
            'jwt' => $deepLink->getResponseJwt([$resource]),
            'returnUrl' => $deepLink->getDeepLinkSettings()['deep_link_return_url'] ?? '',
        ];
    }
}
```
Registrar binding en `AppServiceProvider`:
```php
$this->app->bind(\App\Lti\DeepLinkRespondedor::class, \App\Lti\LibreriaDeepLinkRespondedor::class);
```

- [ ] **Step 5: Implementar el controlador**

`app/Http/Controllers/Lti/LtiDeepLinkController.php`:
```php
<?php
namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Lti\DeepLinkRespondedor;
use App\Models\Practica;
use Illuminate\Http\Request;

class LtiDeepLinkController extends Controller {
    public function seleccionar() {
        $practicas = Practica::orderBy('titulo')->get();
        return view('lti.seleccionar-practica', ['practicas' => $practicas]);
    }

    public function responder(Request $request, DeepLinkRespondedor $respondedor) {
        $data = $request->validate(['id_practica' => 'required|integer']);
        $res = $respondedor->construir($data['id_practica']);
        return view('lti.auto-post', ['jwt' => $res['jwt'], 'returnUrl' => $res['returnUrl']]);
    }
}
```

- [ ] **Step 6: Crear las vistas**

`resources/views/lti/seleccionar-practica.blade.php`:
```blade
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Elegir práctica</title>
<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem}.p{border:1px solid #e5e7eb;border-radius:.6rem;padding:1rem;margin:.5rem 0;display:flex;justify-content:space-between;align-items:center}.btn{background:#4f46e5;color:#fff;border:0;padding:.5rem 1rem;border-radius:.5rem;cursor:pointer}</style>
</head><body>
<h1>Elige la práctica para esta actividad</h1>
@foreach ($practicas as $practica)
<form method="POST" action="{{ route('lti.deeplink.responder') }}" class="p">@csrf
    <span>{{ $practica->titulo }}</span>
    <input type="hidden" name="id_practica" value="{{ $practica->id_practica }}">
    <button class="btn">Elegir</button>
</form>
@endforeach
</body></html>
```
`resources/views/lti/auto-post.blade.php` (auto-postea el JWT de vuelta a Moodle):
```blade
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Enviando…</title></head>
<body onload="document.forms[0].submit()">
<form method="POST" action="{{ $returnUrl }}">
    <input type="hidden" name="JWT" value="{{ $jwt }}">
    <noscript><button type="submit">Continuar</button></noscript>
</form>
</body></html>
```
Nota CSRF: `lti.deeplink.responder` es una ruta web propia (mismo sitio, con `@csrf` en el form del selector) → mantiene CSRF. El auto-post final va a Moodle (externo), no a nosotros.

- [ ] **Step 7: Registrar rutas (reemplazar placeholder de Task 6)**

En `routes/web.php`, reemplazar el placeholder `lti.deeplink`:
```php
use App\Http\Controllers\Lti\LtiDeepLinkController;
Route::get('/lti/deeplink', [LtiDeepLinkController::class, 'seleccionar'])->name('lti.deeplink');
Route::post('/lti/deeplink', [LtiDeepLinkController::class, 'responder'])->name('lti.deeplink.responder');
```

- [ ] **Step 8: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=DeepLinkTest`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat(lti): deep linking selector de practica + respuesta auto-posteada"
```

---

### Task 8: AGS — devolver la calificación a Moodle al completar

**Files:**
- Create: `app/Lti/EnviarCalificacionAgs.php` (servicio) + `app/Lti/AgsCliente.php` (interfaz)
- Modify: `app/Http/Controllers/Api/GameSessionController.php` (hook en `complete`), `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Lti/AgsTest.php`

**Interfaces:**
- Consumes: `SesionPractica` (con `ags_lineitem_url`, `ags_endpoint`, `lti_platform_id`, `alumno.usuario.lti_user_id`), la librería para AGS (`$launch->getAgs()` o `LtiLineitem`/`LtiGrade` + `LtiServiceConnector` con un access token). VERIFICAR API.
- Produces:
  - Interfaz `AgsCliente` con `enviar(SesionPractica $sesion): void` (envía la nota). Implementación real usa la librería; en tests se mockea.
  - `GameSessionController::complete` invoca `AgsCliente::enviar($sesion)` **solo si** `$sesion->ags_lineitem_url` no es null, después de marcar la sesión `completada`.

- [ ] **Step 1: Definir la interfaz**

`app/Lti/AgsCliente.php`:
```php
<?php
namespace App\Lti;

use App\Models\SesionPractica;

interface AgsCliente {
    public function enviar(SesionPractica $sesion): void;
}
```

- [ ] **Step 2: Escribir el test (con AgsCliente mockeado)**

`tests/Feature/Lti/AgsTest.php`:
```php
<?php
use App\Lti\AgsCliente;
use App\Models\{Rol, Usuario, Alumno, Carrera, Materia, Practica, SesionPractica, LtiPlatform};
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

function sesionLti(bool $conAgs): array {
    $rol = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a'.rand(1,99999).'@c.com', 'nombre' => 'A', 'apellidos' => 'B', 'lti_user_id' => 'mu-'.rand(1,99999)]);
    $car = Carrera::firstOrCreate(['clave' => 'LTI'], ['nombre' => 'Externa', 'duracion_semestres' => 1]);
    $al = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $car->id_carrera, 'matricula' => 'M'.rand(1,99999), 'semestre_actual' => 1, 'generacion' => '2026']);
    $mat = Materia::create(['clave' => 'C'.rand(1,99999), 'nombre' => 'M', 'creditos' => 5]);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab']);
    $plat = LtiPlatform::create(['issuer' => 'http://localhost:8080', 'client_id' => 'C', 'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x', 'deployment_id' => 'D', 'activo' => true]);
    $sesion = SesionPractica::create([
        'id_practica' => $practica->id_practica, 'id_evento' => null, 'id_alumno' => $al->id_alumno,
        'fecha_inicio' => now(), 'estatus' => 'en_progreso',
        'lti_platform_id' => $plat->id,
        'ags_lineitem_url' => $conAgs ? 'http://localhost:8080/.../lineitem' : null,
        'ags_endpoint' => $conAgs ? 'http://localhost:8080/mod/lti/services.php' : null,
    ]);
    return [$u, $sesion];
}

it('envia la calificacion por AGS al completar una sesion LTI', function () {
    [$u, $sesion] = sesionLti(conAgs: true);
    $mock = Mockery::mock(AgsCliente::class);
    $mock->shouldReceive('enviar')->once()->with(Mockery::on(fn ($s) => $s->id_sesion === $sesion->id_sesion));
    $this->app->instance(AgsCliente::class, $mock);

    Sanctum::actingAs($u, ['game']);
    $this->postJson("/api/game/sessions/{$sesion->id_sesion}/complete", ['calificacion' => 90, 'datos_resultado' => []])
        ->assertOk();
});

it('NO llama AGS si la sesion no es LTI (sin lineitem)', function () {
    [$u, $sesion] = sesionLti(conAgs: false);
    $mock = Mockery::mock(AgsCliente::class);
    $mock->shouldReceive('enviar')->never();
    $this->app->instance(AgsCliente::class, $mock);

    Sanctum::actingAs($u, ['game']);
    $this->postJson("/api/game/sessions/{$sesion->id_sesion}/complete", ['calificacion' => 70, 'datos_resultado' => []])
        ->assertOk();
});
```

- [ ] **Step 3: Correr el test (debe fallar)**

Run: `php artisan test --filter=AgsTest`
Expected: FAIL.

- [ ] **Step 4: Hook en `complete`**

En `app/Http/Controllers/Api/GameSessionController.php`, método `complete`, DESPUÉS de `$sesion->update([... 'estatus' => 'completada' ...])` y antes del `return`, agregar:
```php
if ($sesion->ags_lineitem_url) {
    app(\App\Lti\AgsCliente::class)->enviar($sesion->fresh());
}
```
(No cambiar la lógica existente de ownership/estado/validación.)

- [ ] **Step 5: Implementar el cliente AGS real**

`app/Lti/EnviarCalificacionAgs.php` (reconciliar con API instalada: `LtiLineitem`, `LtiGrade`, `LtiServiceConnector`, y obtención del access token vía `LtiRegistration`/`LtiServiceConnector`):
```php
<?php
namespace App\Lti;

use App\Models\SesionPractica;
use Packback\Lti1p3\LtiGrade;
use Packback\Lti1p3\LtiLineitem;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\LtiAssignmentsGradesService;

class EnviarCalificacionAgs implements AgsCliente {
    public function enviar(SesionPractica $sesion): void {
        $sesion->loadMissing('alumno.usuario');
        $ltiUserId = optional(optional($sesion->alumno)->usuario)->lti_user_id;
        if (! $ltiUserId || ! $sesion->ags_lineitem_url) return;

        $db = new LtiDatabase();
        $registration = $db->findRegistrationByIssuer(
            \App\Models\LtiPlatform::find($sesion->lti_platform_id)->issuer
        );
        $connector = new LtiServiceConnector(new LtiCache(), new \GuzzleHttp\Client());

        $scopes = [
            'https://purl.imsglobal.org/spec/lti-ags/scope/score',
            'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
        ];
        $ags = new LtiAssignmentsGradesService($connector, $registration, [
            'lineitem' => $sesion->ags_lineitem_url,
            'scope' => $scopes,
        ]);

        $grade = LtiGrade::new()
            ->setScoreGiven((float) $sesion->calificacion)
            ->setScoreMaximum(100.0)
            ->setActivityProgress('Completed')
            ->setGradingProgress('FullyGraded')
            ->setUserId($ltiUserId)
            ->setTimestamp(now()->toIso8601String());

        $lineitem = LtiLineitem::new()->setId($sesion->ags_lineitem_url);
        $ags->putGrade($grade, $lineitem);
    }
}
```
Registrar binding en `AppServiceProvider`:
```php
$this->app->bind(\App\Lti\AgsCliente::class, \App\Lti\EnviarCalificacionAgs::class);
```
(El constructor de `LtiAssignmentsGradesService` y `putGrade` varían por versión: VERIFICAR en `vendor/.../src/LtiAssignmentsGradesService.php`. El test usa un mock, así que la suite pasa aunque la firma real se ajuste; reconciliar antes del e2e en Task 9.)

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=AgsTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(lti): AGS - devolver calificacion a Moodle al completar (con hook en complete)"
```

---

### Task 9: Registro de la plataforma + configuración del Moodle local + e2e

**Files:**
- Create: `app/Console/Commands/LtiRegistrarPlataforma.php`
- Create: `docs/LTI-CONFIGURAR-MOODLE.md`
- Test: `tests/Feature/Lti/RegistrarPlataformaTest.php`

**Interfaces:**
- Consumes: `LtiPlatform`.
- Produces: comando `metaverso:lti-registrar-plataforma` con opciones `--issuer= --client-id= --deployment-id= --auth-login-url= --auth-token-url= --jwks-url=` que upserta una `LtiPlatform`. Doc paso a paso para registrar la herramienta en Moodle y obtener esos valores. Guía e2e.

- [ ] **Step 1: Escribir el test del comando**

`tests/Feature/Lti/RegistrarPlataformaTest.php`:
```php
<?php
use App\Models\LtiPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registra (upsert) una plataforma', function () {
    $this->artisan('metaverso:lti-registrar-plataforma', [
        '--issuer' => 'http://localhost:8080',
        '--client-id' => 'CID',
        '--deployment-id' => 'DEP1',
        '--auth-login-url' => 'http://localhost:8080/mod/lti/auth.php',
        '--auth-token-url' => 'http://localhost:8080/mod/lti/token.php',
        '--jwks-url' => 'http://localhost:8080/mod/lti/certs.php',
    ])->assertSuccessful();

    $p = LtiPlatform::where('issuer', 'http://localhost:8080')->where('client_id', 'CID')->first();
    expect($p)->not->toBeNull();
    expect($p->deployment_id)->toBe('DEP1');

    // upsert: segunda llamada no duplica
    $this->artisan('metaverso:lti-registrar-plataforma', [
        '--issuer' => 'http://localhost:8080', '--client-id' => 'CID', '--deployment-id' => 'DEP2',
        '--auth-login-url' => 'x', '--auth-token-url' => 'y', '--jwks-url' => 'z',
    ])->assertSuccessful();
    expect(LtiPlatform::where('issuer', 'http://localhost:8080')->where('client_id', 'CID')->count())->toBe(1);
    expect(LtiPlatform::where('client_id', 'CID')->first()->deployment_id)->toBe('DEP2');
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --filter=RegistrarPlataformaTest`
Expected: FAIL.

- [ ] **Step 3: Implementar el comando**

`app/Console/Commands/LtiRegistrarPlataforma.php`:
```php
<?php
namespace App\Console\Commands;

use App\Models\LtiPlatform;
use Illuminate\Console\Command;

class LtiRegistrarPlataforma extends Command {
    protected $signature = 'metaverso:lti-registrar-plataforma
        {--issuer=} {--client-id=} {--deployment-id=}
        {--auth-login-url=} {--auth-token-url=} {--jwks-url=}';
    protected $description = 'Registra o actualiza una plataforma LTI (Moodle)';

    public function handle(): int {
        foreach (['issuer','client-id','auth-login-url','auth-token-url','jwks-url'] as $req) {
            if (! $this->option($req)) { $this->error("Falta --{$req}"); return self::FAILURE; }
        }
        $p = LtiPlatform::updateOrCreate(
            ['issuer' => $this->option('issuer'), 'client_id' => $this->option('client-id')],
            [
                'deployment_id' => $this->option('deployment-id'),
                'auth_login_url' => $this->option('auth-login-url'),
                'auth_token_url' => $this->option('auth-token-url'),
                'jwks_url' => $this->option('jwks-url'),
                'activo' => true,
            ]
        );
        $this->info('Plataforma registrada: '.$p->issuer.' ('.$p->client_id.')');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php artisan config:clear && php artisan test --filter=RegistrarPlataformaTest`
Expected: PASS.

- [ ] **Step 5: Escribir la guía de configuración de Moodle**

`docs/LTI-CONFIGURAR-MOODLE.md` con:
- Cómo configurar la red local: agregar `127.0.0.1 host.docker.internal` a `/etc/hosts` del host; arrancar el backend con `php artisan serve --host=0.0.0.0 --port=8000`; usar `APP_URL=http://host.docker.internal:8000` en `.env`.
- En Moodle (admin): Site administration → Plugins → Activity modules → External tool → Manage tools → "configure a tool manually" (LTI 1.3):
  - Tool URL: `http://host.docker.internal:8000/lti/launch`
  - Initiate login URL: `http://host.docker.internal:8000/lti/login`
  - Public keyset URL: `http://host.docker.internal:8000/lti/jwks`
  - Redirection URI(s): `http://host.docker.internal:8000/lti/launch`
  - Public key type: "Keyset URL"
  - Services: AGS = "Use this service for grade sync…"; Deep Linking = activado.
- Tras guardar, Moodle muestra: client_id, deployment_id, platform_id (issuer), auth/login/token/keyset URLs (en "Tool configuration details") → correr:
  `php artisan metaverso:lti-registrar-plataforma --issuer=<...> --client-id=<...> --deployment-id=<...> --auth-login-url=<...> --auth-token-url=<...> --jwks-url=<...>`
- Guía e2e: crear curso → agregar actividad "External tool" → seleccionar la herramienta → "Select content" (Deep Linking) elige práctica → entrar como alumno de prueba → "Abrir juego" → completar en Unreal (o simular `complete` con un Bearer) → ver la nota en el libro de calificaciones.

- [ ] **Step 6: Configurar el Moodle local de verdad (acción del operador/controlador)**

El controlador (no un test): genera llaves (`metaverso:lti-generar-llaves`), ajusta la red, registra la herramienta en el Moodle de Docker siguiendo la guía, corre el comando de registro, y hace una prueba e2e real. Documentar el resultado (capturas/notas) en el reporte. (Este paso es manual y no bloquea la suite.)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(lti): comando de registro de plataforma + guia de configuracion Moodle"
```

---

### Task 10: Verificación integral + README

**Files:**
- Modify: `README.md`
- Test: toda la suite.

- [ ] **Step 1: Documentar LTI en el README**

Sección "Integración LTI (Moodle)": qué hace, comandos (`metaverso:lti-generar-llaves`, `metaverso:lti-registrar-plataforma`), endpoints `/lti/*`, y enlace a `docs/LTI-CONFIGURAR-MOODLE.md` y `docs/INTEGRACION-MOODLE-LTI.md`.

- [ ] **Step 2: Correr toda la suite**

Run: `php artisan config:clear && php artisan test`
Expected: PASS (todas las pruebas previas + las nuevas de LTI).

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "docs(lti): documentar integracion LTI en el README"
```

---

## Self-Review

**Cobertura del spec:**
- §3 librería → Task 1. §4 modelo de datos (tablas + cambios) → Task 1. §5 endpoints: JWKS→T2, login→T4, launch→T6, deeplink→T7. §6 flujo: registro→T9, deep linking→T7, juego/launch→T6, AGS→T8. §6.C auto-aprovisionamiento → T5. §7 errores (sin id_practica→422; AGS no rompe flujo) → T6, T8. §8 redes → T9 (guía + paso manual). §9 pruebas (5 escenarios: jwks, launch+provision, jwt inválido, deep linking, AGS) → T2,T5,T6,T7,T8 (nota: "jwt inválido →401" se cubre con el validador real/e2e; los tests unitarios usan el doble para desacoplar criptografía — la validación real se ejerce en e2e T9). §10 entregables → T1–T10.

**Placeholders de implementación:** las rutas `fn () => 'ok'` (lti.deeplink en T6) se reemplazan en T7. Sin placeholders finales.

**Riesgo conocido (declarado, no oculto):** las firmas exactas de `packbackbooks/lti-1p3-tool` (LtiOidcLogin, LtiMessageLaunch, LtiServiceConnector, LtiAssignmentsGradesService, getResponseJwt) varían por versión; cada tarea que las toca incluye un paso de verificación contra `vendor/`. Los tests desacoplan la criptografía detrás de interfaces propias (`LaunchValidador`, `DeepLinkRespondedor`, `AgsCliente`) para que la suite sea determinista; la integración real con la librería se valida en el e2e contra el Moodle de Docker (T9).

**Consistencia de tipos/nombres:** `DatosLaunch` (campos), `LaunchValidador::validar`, `DeepLinkRespondedor::construir`, `AgsCliente::enviar`, `AprovisionarAlumno::desdeLaunch` usados consistentemente entre tareas. Rutas: `lti.jwks`, `lti.login`, `lti.launch`, `lti.deeplink`, `lti.deeplink.responder`. `sesiones_practica.id_evento` nullable establecido en T1 y usado (NULL) en T6/T8.
