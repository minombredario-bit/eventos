#!/usr/bin/env bash
set -euo pipefail

# fix_var_permissions.sh
# Utility to fix ownership/permissions of Symfony's var/ directory and warmup cache.
# Intended to be executed inside the php container as root, or from host via
# `docker-compose exec --user root php bash -lc "/var/www/html/scripts/fix_var_permissions.sh"`

PROJECT_DIR="/var/www/html"
VAR_DIR="$PROJECT_DIR/var"

if [ "$(id -u)" -ne 0 ]; then
  echo "This script must be run as root (inside the container). Use --user root with docker-compose exec." >&2
  exit 1
fi

echo "[fix-var] ensuring $VAR_DIR exists"
mkdir -p "$VAR_DIR"

echo "[fix-var] setting owner to www-data:www-data (recursively)"
chown -R www-data:www-data "$VAR_DIR"

echo "[fix-var] setting directory permissions (2775) and file permissions (0664)"
find "$VAR_DIR" -type d -exec chmod 2775 {} +
find "$VAR_DIR" -type f -exec chmod 0664 {} +

echo "[fix-var] clearing old cache and logs (safe to keep, will ignore missing files)"
rm -rf "$VAR_DIR/cache"/* || true
rm -rf "$VAR_DIR/log"/* || true

echo "[fix-var] warming up Symfony cache as www-data"
if su -s /bin/bash -c 'php bin/console cache:warmup --env=prod' www-data 2>/dev/null; then
  echo "[fix-var] cache warmed up (www-data)"
else
  echo "[fix-var] warming up cache as www-data failed, trying as current user"
  php bin/console cache:warmup --env=prod
fi

echo "[fix-var] final permissions check"
ls -la "$VAR_DIR" || true
stat -c '%U:%G %a %n' "$VAR_DIR" "$VAR_DIR/cache" "$VAR_DIR/log" || true

echo "[fix-var] done"

