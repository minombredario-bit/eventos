#!/bin/sh
set -e

# ── SSH / SFTP ────────────────────────────────────────────────────────────────
# Regenera claves de host si el volumen las perdió
ssh-keygen -A 2>/dev/null || true

# Contraseña del usuario SFTP (variable de entorno o valor por defecto dev)
SFTP_PASS="${SFTP_USER_PASS:-sftppass_dev}"
echo "sftpuser:${SFTP_PASS}" | chpasswd

# Directorio .ssh por si se usan claves públicas desde PhpStorm
mkdir -p /home/sftpuser/.ssh
chmod 700 /home/sftpuser/.ssh
chown sftpuser:sftpuser /home/sftpuser/.ssh

/usr/sbin/sshd
echo "[entrypoint] SFTP/SSH activo en puerto 22 → host:2222"
# ─────────────────────────────────────────────────────────────────────────────

# El código ya viene copiado en la imagen (COPY backend/ en el Dockerfile).
# El volumen app_data se siembra desde la imagen en la primera arrancada.
# Solo aseguramos permisos de var/ para cache y logs.
mkdir -p var/cache var/log
chown -R www-data:www-data var/

# Crear superadmin si no existe (requiere SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD)
if [ -f /var/www/html/bin/console ]; then
    cd /var/www/html
    php bin/console app:ensure-superadmin --no-interaction 2>/dev/null || true
fi

exec "$@"
