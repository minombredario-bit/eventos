#!/bin/sh
set -e

echo "[entrypoint] Iniciando en modo producción..."

cd /var/www/html

run_as_www_data() {
    su -s /bin/sh -c "cd /var/www/html && $*" www-data
}

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

echo "[fix-var] Fijando permisos de var/..."
mkdir -p var/cache/prod var/log
chown -R www-data:www-data var/
find var/ -type d -exec chmod 2775 {} +
find var/ -type f -exec chmod 0664 {} +
echo "[fix-var] Permisos OK"

# ── Cache warmup (con output completo para debug) ─────────────
echo "[entrypoint] Calentando cache de Symfony..."
WARMUP_OUTPUT=$(run_as_www_data 'php bin/console cache:warmup --env=prod --no-debug 2>&1')
WARMUP_EXIT=$?
if [ $WARMUP_EXIT -ne 0 ]; then
    echo "[entrypoint] ERROR: cache:warmup falló (exit $WARMUP_EXIT):"
    echo "$WARMUP_OUTPUT"
    exit 1
fi
echo "[entrypoint] Cache OK"

# ── Migraciones ───────────────────────────────────────────────
echo "[entrypoint] Ejecutando migraciones..."
set +e
MIGRATION_OUTPUT=$(run_as_www_data 'php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>&1')
MIGRATION_EXIT=$?
set -e
echo "$MIGRATION_OUTPUT"
if [ $MIGRATION_EXIT -ne 0 ]; then
    echo "[entrypoint] AVISO: migraciones fallaron (exit $MIGRATION_EXIT) — continuando"
fi

# ── JWT keys ─────────────────────────────────────
echo "[entrypoint] Verificando claves JWT..."

mkdir -p config/jwt

if [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
    echo "[entrypoint] Generando claves JWT..."

    run_as_www_data 'php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction'
else
    echo "[entrypoint] Claves JWT OK"
fi

chown -R www-data:www-data config/jwt
chmod 640 config/jwt/*.pem 2>/dev/null || true

# ── Superadmin ────────────────────────────────────────────────
echo "[entrypoint] Verificando superadmin..."
set +e
SUPERADMIN_OUTPUT=$(run_as_www_data 'php bin/console app:ensure-superadmin --no-interaction 2>&1')
SUPERADMIN_EXIT=$?
set -e
echo "$SUPERADMIN_OUTPUT"
if [ $SUPERADMIN_EXIT -ne 0 ]; then
    echo "[entrypoint] AVISO: ensure-superadmin falló (exit $SUPERADMIN_EXIT) — continuando"
fi

echo "[entrypoint] Arrancando proceso principal: $*"
exec "$@"
