#!/bin/sh
set -e

cd /var/www/html

# Primer arranque tras clonar: instala dependencias y crea el .env
if [ ! -d vendor ]; then
    echo "[entrypoint] vendor/ no existe, ejecutando composer install..."
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ] && [ -f .env.example ]; then
    echo "[entrypoint] creando .env desde .env.example"
    cp .env.example .env
    php artisan key:generate --ansi
    php artisan jwt:secret --force --no-interaction
fi

exec "$@"
