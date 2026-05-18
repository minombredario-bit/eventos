#!/bin/sh
set -e

# SSH / SFTP
ssh-keygen -A 2>/dev/null || true

SFTP_PASS="${SFTP_USER_PASS:-sftppass_dev}"
echo "sftpuser:${SFTP_PASS}" | chpasswd

mkdir -p /home/sftpuser/.ssh
chmod 700 /home/sftpuser/.ssh
chown sftpuser:sftpuser /home/sftpuser/.ssh

/usr/sbin/sshd
echo "[entrypoint] SFTP/SSH activo en puerto 22 → host:2223"

run_as_www_data() {
    su -s /bin/sh -c "cd /var/www/html && $*" www-data
}

# Permisos Symfony
# Asegurar que el directorio raíz tiene grupo www-data (SGID para herencia)
chown root:www-data /var/www/html
chmod 2775 /var/www/html

mkdir -p /var/www/html/public/uploads
mkdir -p /var/www/html/public/uploads/entidades
chown -R www-data:www-data /var/www/html/public/uploads
chmod 2775 /var/www/html/public/uploads /var/www/html/public/uploads/entidades
find /var/www/html/public/uploads -type d -exec chmod 2775 {} \;
find /var/www/html/public/uploads -type f -exec chmod 0664 {} \;
if [ -d /legacy-app-data/public/uploads ] && [ -z "$(ls -A /var/www/html/public/uploads 2>/dev/null)" ] && [ -n "$(ls -A /legacy-app-data/public/uploads 2>/dev/null)" ]; then
    echo "[entrypoint] Migrando uploads existentes al volumen compartido..."
    cp -a /legacy-app-data/public/uploads/. /var/www/html/public/uploads/
    chown -R www-data:www-data /var/www/html/public/uploads
fi

if [ -f /var/www/html/scripts/fix_var_permissions.sh ]; then
    sh /var/www/html/scripts/fix_var_permissions.sh
else
    mkdir -p /var/www/html/var/cache/dev /var/www/html/var/log
    chown -R www-data:www-data /var/www/html/var
    find /var/www/html/var -type d -exec chmod 2775 {} \;
    find /var/www/html/var -type f -exec chmod 0664 {} \;
fi

# Superadmin
if [ -f /var/www/html/bin/console ]; then
    run_as_www_data 'php bin/console app:ensure-superadmin --no-interaction' 2>/dev/null || true
fi

exec "$@"
