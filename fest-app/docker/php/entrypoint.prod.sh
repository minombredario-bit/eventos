#!/bin/sh
set -e

echo "[entrypoint] Iniciando en modo producción..."

cd /var/www/html

run_as_www_data() {
    su -s /bin/sh -c "cd /var/www/html && $*" www-data
}

# ── Espera activa a que el código esté disponible ─

# (por si el volumen tarda en montarse o el código
#  está en la imagen y el volumen lo sobreescribe vacío)
MAX_WAIT=30
WAITED=0
while [ ! -f bin/console ]; do
    if [ "$WAITED" -ge "$MAX_WAIT" ]; then
        echo "[entrypoint] ERROR: bin/console no encontrado tras ${MAX_WAIT}s. Abortando."
        exit 1
    fi
    echo "[entrypoint] Esperando código... (${WAITED}s)"
    sleep 2
    WAITED=$((WAITED + 2))
done

# ── Permisos de var/ ──────────────────────────────
sh /var/www/html/scripts/fix_var_permissions.sh

# ── Migraciones ───────────────────────────────────
echo "[entrypoint] Ejecutando migraciones..."
run_as_www_data 'php bin/console doctrine:migrations:migrate --no-interaction --env=prod' 2>&1 \
    && echo "[entrypoint] Migraciones OK" \
    || echo "[entrypoint] AVISO: migraciones fallaron (puede ser normal si ya están aplicadas)"

# ── Superadmin ────────────────────────────────────
run_as_www_data 'php bin/console app:ensure-superadmin --no-interaction' 2>/dev/null \
    && echo "[entrypoint] Superadmin OK" \
    || echo "[entrypoint] AVISO: ensure-superadmin falló (puede que ya exista)"

echo "[entrypoint] Aplicación lista."

exec "$@"
