#!/bin/sh
set -e

echo "[entrypoint] Iniciando en modo producción..."

if [ -f /var/www/html/bin/console ]; then
    cd /var/www/html

    # Cache warmup
    php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || true

    # Migraciones automáticas (opcional; comenta si lo haces manualmente)
    # php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || true

    # Crear superadmin si no existe (requiere SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD)
    php bin/console app:ensure-superadmin --no-interaction 2>/dev/null || true

    echo "[entrypoint] Aplicación lista."
else
    echo "[entrypoint] AVISO: código Symfony no encontrado en el volumen."
    echo "[entrypoint] Sube los archivos por FTP primero."
fi

exec "$@"
