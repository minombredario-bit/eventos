#!/bin/bash
# Script para probar la funcionalidad de solicitud de cambio de contraseña

API_URL="http://localhost:8000/api"
CODIGO_ENTIDAD="ABC123XYZ"  # Cambiar por código de entidad válido
EMAIL_TEST="usuario@example.com"
DOC_TEST="12345678X"

echo "=========================================="
echo "Prueba 1: Reset por email"
echo "=========================================="
curl -X POST "$API_URL/password/reset-request" \
  -H "Content-Type: application/json" \
  -d "{
    \"identifier\": \"$EMAIL_TEST\",
    \"codigoEntidad\": \"$CODIGO_ENTIDAD\"
  }" \
  -v

echo ""
echo ""

echo "=========================================="
echo "Prueba 2: Reset por documento (DNI/CIF)"
echo "=========================================="
curl -X POST "$API_URL/password/reset-request" \
  -H "Content-Type: application/json" \
  -d "{
    \"identifier\": \"$DOC_TEST\",
    \"codigoEntidad\": \"$CODIGO_ENTIDAD\"
  }" \
  -v

echo ""
echo ""

echo "=========================================="
echo "Prueba 3: Código de entidad inválido"
echo "=========================================="
curl -X POST "$API_URL/password/reset-request" \
  -H "Content-Type: application/json" \
  -d "{
    \"identifier\": \"$EMAIL_TEST\",
    \"codigoEntidad\": \"INVALID_CODE\"
  }" \
  -v

echo ""
echo ""

echo "=========================================="
echo "Prueba 4: Datos vacíos (debe fallar validación)"
echo "=========================================="
curl -X POST "$API_URL/password/reset-request" \
  -H "Content-Type: application/json" \
  -d "{
    \"identifier\": \"\",
    \"codigoEntidad\": \"\"
  }" \
  -v

echo ""
echo ""

echo "=========================================="
echo "NOTA IMPORTANTE:"
echo "=========================================="
echo "✓ El endpoint SIEMPRE devuelve 200 OK por seguridad"
echo "✓ No revela si el usuario existe o no"
echo "✓ Los emails se encolan en ColaCorreo"
echo "✓ Ejecuta processPending() para enviar realmente los emails:"
echo ""
echo "  php bin/console app:email:process-queue"
echo ""
echo "✓ Revisa los emails encolados:"
echo ""
echo "  SELECT id, destinatario, asunto, estado, createdAt FROM cola_correo"
echo "  ORDER BY createdAt DESC;"

