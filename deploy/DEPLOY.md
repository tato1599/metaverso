# Despliegue en el servidor del TecNM (Docker)

Kit de despliegue del backend Metaverso Escolar. Levanta **app (Laravel) +
PostgreSQL + Caddy (HTTPS automático)** con un par de comandos. Pensado para un
servidor on-premise donde Moodle vive en el mismo servidor o red.

## 1. Requisitos en el servidor
- Docker y Docker Compose v2.
- Un **(sub)dominio** apuntando al servidor (ideal: `metaverso.tuinstituto.tecnm.mx`).
  Aunque sea solo de red interna, usa un nombre, no una IP pelada (LTI necesita HTTPS).
- Puertos **80 y 443** accesibles (al menos dentro de la red).

## 2. Configuración (una vez)
```bash
git clone <repo> metaverso && cd metaverso/deploy
cp .env.example .env
# Edita deploy/.env: APP_DOMAIN, APP_URL, DB_PASSWORD, LINKS_API_KEY...
```
Genera la `APP_KEY` y pégala en `deploy/.env`:
```bash
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --show
```

## 3. Levantar
```bash
docker compose -f docker-compose.prod.yml up -d --build
```
Al arrancar, el contenedor espera a PostgreSQL, corre `migrate --force` y cachea
config/rutas/vistas. Caddy obtiene el certificado HTTPS automáticamente.

Verifica:
```bash
curl -k https://TU_DOMINIO/lti/jwks      # debe devolver un JSON con la llave
docker compose -f docker-compose.prod.yml logs -f app
```

## 4. HTTPS según el entorno
- **Servidor accesible desde internet + dominio público:** no hagas nada. Caddy
  emite el certificado con **Let's Encrypt** automáticamente.
- **Solo red interna (sin internet):** descomenta `tls internal` en `Caddyfile` y
  recrea Caddy. Caddy emitirá su propia CA interna; haz que **Moodle confíe** en
  ella (ver §7). Alternativa preferible: pide a sistemas un certificado de la **CA
  institucional** y móntalo en Caddy.

## 5. Preparar LTI (una vez)
```bash
C="docker compose -f docker-compose.prod.yml exec app php artisan"
$C metaverso:lti-generar-llaves
# Registra la herramienta en Moodle (admin) con tus URLs https://TU_DOMINIO/lti/...
# y luego registra la plataforma con los datos que Moodle genera:
$C metaverso:lti-registrar-plataforma \
  --issuer=https://TU_MOODLE --client-id=... --deployment-id=... \
  --auth-login-url=https://TU_MOODLE/mod/lti/auth.php \
  --auth-token-url=https://TU_MOODLE/mod/lti/token.php \
  --jwks-url=https://TU_MOODLE/mod/lti/certs.php
```
(En producción con HTTPS real **no** hace falta tocar `curlsecurityblockedhosts`
ni `allowedport` de Moodle ni el `leeway` de reloj: esos eran arreglos del entorno
local. Solo asegúrate de que ambos servidores se alcancen por sus URLs HTTPS.)

## 6. Datos iniciales
- Para una demo: `$C db:seed` (crea datos de ejemplo). **No** lo uses en una
  instancia real con alumnos.
- Acceso al panel docente: `$C metaverso:panel-acceso <id_usuario>` e imprime el
  enlace firmado.

## 7. Que Moodle confíe en la CA interna (solo si usaste `tls internal`)
```bash
# Extrae la raíz que generó Caddy:
docker compose -f docker-compose.prod.yml cp \
  caddy:/data/caddy/pki/authorities/local/root.crt ./caddy-root.crt
# Cópiala al contenedor de Moodle y actualiza su almacén de confianza:
docker cp ./caddy-root.crt <contenedor_moodle>:/usr/local/share/ca-certificates/caddy.crt
docker exec -u root <contenedor_moodle> update-ca-certificates
```
(Si Moodle es nativo y no en Docker, agrega la raíz al almacén del sistema operativo
del servidor de Moodle.)

## 8. Actualizar a una nueva versión
```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
# El entrypoint vuelve a migrar y a cachear automáticamente.
```

## 9. Respaldo de la base de datos
```bash
docker compose -f docker-compose.prod.yml exec postgres \
  pg_dump -U "$DB_USERNAME" "$DB_DATABASE" > backup_$(date +%F).sql
```

## 10. Un servidor por escuela
Este mismo kit se replica por escuela: clona el repo, usa un `deploy/.env` con su
propio `APP_DOMAIN` y credenciales, y `docker compose up`. Cada instancia es
independiente (su propia BD y su propio registro LTI en su Moodle).

## Checklist de seguridad
- `APP_DEBUG=false` y `APP_ENV=production`.
- Contraseñas fuertes en `DB_PASSWORD` y `LINKS_API_KEY`.
- HTTPS activo (Let's Encrypt o CA institucional).
- Firewall: exponer solo 80/443 (y 22 para administración).
- Respaldos periódicos de PostgreSQL.
