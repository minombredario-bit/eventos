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

CONFIG_FILE="/tmp/ofelia.ini"

SMTP_HOST="${OFELIA_SMTP_HOST:-mailpit}"
SMTP_PORT="${OFELIA_SMTP_PORT:-1025}"
MAIL_FROM="${OFELIA_MAIL_FROM:-ofelia@festapp.local}"
MAIL_TO="${SUPERADMIN_EMAIL:-superadmin@festapp.local}"

cat > "${CONFIG_FILE}" << INIEOF
[global]
smtp-host = ${SMTP_HOST}
smtp-port = ${SMTP_PORT}
mail-from = ${MAIL_FROM}
mail-to   = ${MAIL_TO}
INIEOF

if [ -n "${OFELIA_SMTP_USER}" ]; then
    printf 'smtp-user = %s\n' "${OFELIA_SMTP_USER}" >> "${CONFIG_FILE}"
fi

if [ -n "${OFELIA_SMTP_PASSWORD}" ]; then
    printf 'smtp-password = %s\n' "${OFELIA_SMTP_PASSWORD}" >> "${CONFIG_FILE}"
fi

echo "[ofelia] Config generada — alertas de error → ${MAIL_TO}"
echo "[ofelia] SMTP: ${SMTP_HOST}:${SMTP_PORT}"

exec /usr/local/bin/ofelia daemon --docker --config "${CONFIG_FILE}"

