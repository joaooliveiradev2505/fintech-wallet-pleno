#!/usr/bin/env bash
set -e

PORT_TO_USE="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT_TO_USE}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT_TO_USE}>/g" /etc/apache2/sites-available/000-default.conf

if [[ -z "${APP_KEY:-}" ]]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
elif [[ "${APP_KEY}" != base64:* ]] && [[ "${#APP_KEY}" -ne 32 ]]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_KEY"), true));')"
fi

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec apache2-foreground
