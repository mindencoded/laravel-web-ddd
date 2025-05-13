#!/bin/bash

set -e

echo "[entrypoint] Environment: $APP_ENV"
echo "[entrypoint] Octane server: ${OCTANE_SERVER}"
echo "[entrypoint] Host: ${OCTANE_HOST}"
echo "[entrypoint] Proxy Port: ${OCTANE_PROXY_PORT}"
echo "[entrypoint] RPC Port: ${OCTANE_RPC_PORT}"

# Comando base de Octane
OCTANE_CMD="php -d variables_order=EGPCS artisan octane:start \
  --server=${OCTANE_SERVER} \
  --host=${OCTANE_HOST} \
  --port=${OCTANE_PROXY_PORT} \
  --rpc-port=${OCTANE_RPC_PORT}"

# Desarrollo
if [ "$APP_ENV" == "local" ] || [ "$APP_ENV" == "development" ]; then
    echo "[entrypoint] Starting Octane in development mode..."
    exec $OCTANE_CMD --watch

# Producción
elif [ "$APP_ENV" == "production" ]; then
    echo "[entrypoint] Writing supervisor config..."
    echo "[program:laravel-octane]
command=$OCTANE_CMD
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/laravel.err.log
stdout_logfile=/var/log/supervisor/laravel.out.log
user=www-data
environment=APP_ENV='production'
" > /etc/supervisor/conf.d/laravel-octane.conf

    echo "[entrypoint] Starting supervisord..."
    exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

else
    echo "[entrypoint] Unknown APP_ENV: $APP_ENV. Defaulting to development..."
    exec $OCTANE_CMD --watch
fi
