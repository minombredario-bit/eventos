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
echo "[entrypoint] SFTP/SSH activo en puerto 22 → host:2223"
# ─────────────────────────────────────────────────────────────────────────────

# Asegurar permisos de var/ para cache y logs
sh /var/www/html/scripts/fix_var_permissions.sh

run_as_www_data() {
    su -s /bin/sh -c "cd /var/www/html && $*" www-data
}

# Crear superadmin si no existe
if [ -f /var/www/html/bin/console ]; then
    run_as_www_data 'php bin/console app:ensure-superadmin --no-interaction' 2>/dev/null || true
fi

exec "$@"
