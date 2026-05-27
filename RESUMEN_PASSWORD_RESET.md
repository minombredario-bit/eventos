# RESUMEN EJECUTIVO - Solicitud de Cambio de Contraseña (Email o CIF)

## ✅ Funcionalidad Implementada

Se ha implementado el endpoint para **solicitar cambio de contraseña** con búsqueda flexible:

- **Con Email**: Usuario recibe enlace de recuperación directamente
- **Con CIF/Documento**: 
  - Si tiene email → Recibe enlace
  - Si NO tiene email → Admin de la entidad recibe notificación para resetear manualmente

---

## 📁 Archivos Modificados / Creados

### Backend (Symfony)

| Archivo | Tipo | Descripción |
|---------|------|-------------|
| `src/Dto/PasswordResetRequestInput.php` | **NUEVO** | DTO para validar entrada del usuario |
| `src/Service/PasswordResetService.php` | **MODIFICADO** | Método `requestResetByEmailOrDocument()` para buscar por email o CIF |
| `src/Controller/RegistroController.php` | **MODIFICADO** | Endpoint POST `/api/password/reset-request` |
| `templates/email/password_reset_admin_notification.html.twig` | **NUEVO** | Plantilla de email para notificación al admin |

---

## 🔌 API Endpoint

```http
POST /api/password/reset-request
Content-Type: application/json

{
  "identifier": "usuario@example.com",  // o "12345678X"
  "codigoEntidad": "ABC123XYZ"
}
```

**Respuesta (siempre 200 OK):**
```json
{
  "ok": true,
  "message": "Si el usuario existe, recibirá un email con instrucciones o se notificará al administrador."
}
```

---

## 🔐 Seguridad Implementada

- ✅ **Prevención de User Enumeration**: Siempre responde 200 OK
- ✅ **Validación de Entidad**: Verifica pertenencia a la entidad
- ✅ **Tokens Temporales**: Válidos 60 minutos, de un solo uso
- ✅ **Normalización**: Emails minúsculas, espacios eliminados
- ✅ **Actividad**: Solo usuarios activos

---

## 📧 Flujo de Emails

### Caso 1: Usuario CON Email
```
1. Usuario solicita reset (por email o documento)
2. Sistema busca usuario
3. Genera token temporal
4. Envía email con link de recuperación
└─ Usuario hace clic y resetea contraseña
```

### Caso 2: Usuario SIN Email
```
1. Usuario solicita reset (por documento)
2. Sistema busca usuario
3. NO tiene email → Busca admins de la entidad
4. Envía notificación al admin
└─ Admin resetea manualmente o contacta al usuario por otro medio
```

### Caso 3: No Existe
```
1. Usuario solicita reset
2. Sistema no lo encuentra o está inactivo
3. Responde 200 OK (silencioso)
└─ Nada ocurre (seguridad)
```

---

## 🧪 Testing Manual

```bash
# Reset por email
curl -X POST http://localhost:8000/api/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{"identifier":"user@example.com","codigoEntidad":"ABC123"}'

# Reset por documento
curl -X POST http://localhost:8000/api/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{"identifier":"12345678X","codigoEntidad":"ABC123"}'
```

**Procesar emails encolados:**
```bash
php bin/console app:email:process-queue
```

---

## 📋 Checklist de Integración

- [ ] Backend compilado sin errores
- [ ] Base de datos:
  - [ ] Campo `Usuario.documentoIdentidad` existe (nullable string)
  - [ ] Tabla `password_reset_token` contiene columna `email`
  - [ ] Tabla `cola_correo` lista para encolar
- [ ] Emails:
  - [ ] `password_reset.html.twig` (ya existe)
  - [ ] `password_reset_admin_notification.html.twig` (creada)
  - [ ] MAILER_DSN configurado en `.env` o `.env.local`
- [ ] Cron/Task:
  - [ ] `app:email:process-queue` ejecutado periódicamente
- [ ] Frontend:
  - [ ] Pantalla "Recuperar contraseña" implementada
  - [ ] Llamada HTTP a POST `/api/password/reset-request`
  - [ ] Mostrar mensaje genérico al usuario

---

## 🎯 Requisitos Cumplidos

| Requisito | Estado |
|-----------|--------|
| Buscar por email | ✅ |
| Buscar por CIF/DNI | ✅ |
| Enviar recovery email si tiene email | ✅ |
| Notificar admin si NO tiene email | ✅ |
| Prevención user enumeration | ✅ |
| Validación de entidad | ✅ |
| Tokens temporales | ✅ |

---

## 📚 Documentación Adicional

- `IMPLEMENTACION_PASSWORD_RESET.md` - Detalles técnicos
- `PASSWORD_RESET_DIAGRAMA.md` - Flujos y diagramas
- `test_password_reset.sh` - Scripts de prueba

---

## 🚀 Próximos Pasos (Opcional - Fase 2+)

- [ ] Pasarela de pago (TPV/Stripe)
- [ ] Lista de espera automática
- [ ] Notificaciones push
- [ ] SMS para usuarios sin email
- [ ] Dashboard de admin mejorado

---

**Implementación completada:** 2026-05-27  
**Stack:** Symfony 7 + API Platform 4  
**Autor:** GitHub Copilot

