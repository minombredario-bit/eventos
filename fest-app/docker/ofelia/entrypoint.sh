#!/bin/sh
# Genera la configuración de Ofelia a partir de variables de entorno y arranca el daemon.
# Variables de entorno esperadas:
#   SUPERADMIN_EMAIL        → destinatario de las alertas de error
#   OFELIA_SMTP_HOST        → host SMTP (por defecto: mailpit en dev)
#   OFELIA_SMTP_PORT        → puerto SMTP (por defecto: 1025 en dev)
#   OFELIA_SMTP_USER        → usuario SMTP (opcional)
#   OFELIA_SMTP_PASSWORD    → contraseña SMTP (opcional)
#   OFELIA_MAIL_FROM        → remitente (por defecto: ofelia@festapp.local)

set -e

if [ "$#" -gt 0 ]; then
    exec ofelia "$@"
fi

if [ -f /etc/ofelia/config.ini ]; then
    exec ofelia daemon --config /etc/ofelia/config.ini
fi

CONFIG_FILE="/tmp/ofelia.ini"
JOB_FILE="/etc/ofelia/jobs.ini"

SMTP_HOST="${OFELIA_SMTP_HOST:-mailpit}"
SMTP_PORT="${OFELIA_SMTP_PORT:-1025}"
MAIL_FROM="${OFELIA_MAIL_FROM:-ofelia@festivapp.es}"
MAIL_TO="${SUPERADMIN_EMAIL:-superadmin@festivapp.es}"

if [ ! -f "${JOB_FILE}" ]; then
    echo "[ofelia] No se encontró ${JOB_FILE} ni /etc/ofelia/config.ini" >&2
    exit 1
fi

cat > "${CONFIG_FILE}" <<EOF
[global]
smtp-host = ${SMTP_HOST}
smtp-port = ${SMTP_PORT}
email-from = ${MAIL_FROM}
email-to = ${MAIL_TO}
mail-only-on-error = true
EOF

if [ -n "${OFELIA_SMTP_USER}" ]; then
    echo "smtp-user = ${OFELIA_SMTP_USER}" >> "${CONFIG_FILE}"
fi

if [ -n "${OFELIA_SMTP_PASSWORD}" ]; then
    echo "smtp-password = ${OFELIA_SMTP_PASSWORD}" >> "${CONFIG_FILE}"
fi

echo "" >> "${CONFIG_FILE}"

cat "${JOB_FILE}" >> "${CONFIG_FILE}"

echo "[ofelia] Config generada:"
cat "${CONFIG_FILE}"

exec ofelia daemon --config "${CONFIG_FILE}"
