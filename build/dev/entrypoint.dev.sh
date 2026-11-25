#!/bin/sh
set -e

cd /var/www/html

# Instalar dependencias si el volumen vendor está vacío
if [ ! -d vendor ] || [ -z "$(ls -A vendor 2>/dev/null || true)" ]; then
  echo "[entrypoint] vendor vacío, ejecutando composer install..."
  composer install
fi

# Ajustar permisos de Laravel
if [ -d storage ] && [ -d bootstrap/cache ]; then
  chown -R www-data:www-data storage bootstrap/cache || true
  chmod -R ug+rwx storage bootstrap/cache || true
fi

# Índices del inbox (MongoDB)
php artisan inbox:ensure-indexes || true

# Topología RabbitMQ si el driver es rabbitmq (default)
QUEUE_DRIVER="${NOTIFICATION_QUEUE_DRIVER:-rabbitmq}"
if [ "$QUEUE_DRIVER" = "rabbitmq" ]; then
  php artisan messenger:setup || true
fi

# Worker de notificaciones
if [ "$QUEUE_DRIVER" = "laravel" ]; then
  su -s /bin/sh www-data -c \
    "nohup php artisan queue:work --queue=email.send --sleep=3 --tries=3 > /dev/null 2>&1 &" || true
else
  su -s /bin/sh www-data -c \
    "nohup sh -c 'while true; do php artisan messenger:consume email --time-limit=3600; sleep 1; done' > /dev/null 2>&1 &" || true
fi

exec php-fpm
