#!/usr/bin/env bash
# Arranque del contenedor: espera a PostgreSQL, migra y cachea, luego Apache.
set -e

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"

echo ">> Esperando a PostgreSQL en ${DB_HOST}:${DB_PORT} ..."
until php -r "exit(@fsockopen(getenv('DB_HOST')?:'postgres',(int)(getenv('DB_PORT')?:5432))?0:1);" 2>/dev/null; do
  sleep 2
done
echo ">> PostgreSQL disponible."

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo ">> Ejecutando migraciones..."
  php artisan migrate --force
fi

echo ">> Optimizando (config/route/view cache)..."
php artisan storage:link 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ">> Listo. Iniciando Apache."
exec "$@"
