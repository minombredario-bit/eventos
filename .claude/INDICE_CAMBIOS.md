# Índice de Cambios - Solicitud de Cambio de Contraseña

## Resumen Rápido

**Funcionalidad:** Recuperación de contraseña por email O documento (CIF/DNI)

**Status:** ✅ Implementado

**Archivos cambiados:** 2  
**Archivos creados:** 4  
**Líneas de código:** ~250

---

## Archivos del Backend

### 🔧 Modificados

#### 1. `src/Service/PasswordResetService.php`
**Líneas modificadas:** +80 aprox.  
**Cambios:**
- Nuevo método `requestResetByEmailOrDocument(string $identifier, string $entidadId): bool`
- Nuevo método privado `notifyAdminsForPasswordReset(Usuario $usuario, string $tokenString): void`
- Actualización de `resetPassword()` para manejar tokens especiales
- Lógica para buscar usuario por email O documento
- Lógica para enviar email al admin si usuario no tiene email

#### 2. `src/Controller/RegistroController.php`
**Líneas modificadas:** +40 aprox.  
**Cambios:**
- Nuevas dependencias inyectadas: `PasswordResetService`, `EntidadRepository`, `ValidatorInterface`
- Nuevo método `requestPasswordReset(Request $request): JsonResponse`
- Endpoint: `POST /api/password/reset-request`
- Validación de entrada con DTO
- Búsqueda de entidad por código
- Llamada al servicio de reset

### ✨ Creados

#### 3. `src/Dto/PasswordResetRequestInput.php`
**Líneas:** 20  
**Contenido:**
```php
class PasswordResetRequestInput {
  public string $identifier = '';  // Email o DNI/CIF
  public string $codigoEntidad = '';
}
```

Propósito: Validar entrada del usuario

#### 4. `templates/email/password_reset_admin_notification.html.twig`
**Líneas:** 30 aprox.  
**Contenido:**
- Template de email Twig
- Notificación para admin cuando usuario sin email solicita reset
- Variables renderizadas:
  - `usuarioNombre` / `usuarioApellidos`
  - `usuarioEmail`
  - `usuarioDocumento`
  - `entidadNombre`
  - `instrucciones`

---

## Archivos de Documentación

```
festapp/
├── RESUMEN_PASSWORD_RESET.md                    ← Resumen ejecutivo
├── IMPLEMENTACION_PASSWORD_RESET.md              ← Detalles técnicos completos
├── PASSWORD_RESET_DIAGRAMA.md                    ← Flujos y diagramas
├── GUIA_INTEGRACION_PASSWORD_RESET.md            ← Paso a paso integración
├── test_password_reset.sh                        ← Scripts curl para testing
└── THIS_FILE.md                                  ← Índice de cambios
```

---

## Flujo de Cambios del Código

```
Usuario solicita reset
         ↓
RegistroController::requestPasswordReset()
         ↓
Valida entrada (PasswordResetRequestInput)
         ↓
Busca Entidad por código
         ↓
PasswordResetService::requestResetByEmailOrDocument()
         ├─ Busca Usuario por email
         └─ Si no existe, busca por documento
                    ↓
         ¿Usuario tiene email?
         ├─ SÍ → EmailQueueService::enqueue(password_reset.html.twig)
         │              ↓
         │       Usuario recibe email con link
         │
         └─ NO → notifyAdminsForPasswordReset()
                       ↓
                  Busca admins con ROLE_ADMIN_ENTIDAD
                       ↓
                  EmailQueueService::enqueue(password_reset_admin_notification.html.twig)
                       ↓
                  Admin recibe notificación
```

---

## Cambios por Capa

### 🌐 API Layer

**Endpoint creado:**
```
POST /api/password/reset-request
Content-Type: application/json

Request:
{
  "identifier": "usuario@example.com" | "12345678X",
  "codigoEntidad": "ABC123XYZ"
}

Response (200 OK siempre):
{
  "ok": true,
  "message": "Si el usuario existe, recibirá un email..."
}
```

### 🔧 Service Layer

**Métodos nuevos/actualizados:**

```php
// NUEVO
PasswordResetService::requestResetByEmailOrDocument(
  string $identifier,
  string $entidadId
): bool

// ACTUALIZADO
PasswordResetService::resetPassword(
  string $tokenString,
  string $newPassword
): void
// Ahora soporta tokens especiales para usuarios sin email

// NUEVO (privado)
PasswordResetService::notifyAdminsForPasswordReset(
  Usuario $usuario,
  string $tokenString
): void
```

### 📦 DTO Layer

```php
// NUEVO
PasswordResetRequestInput
├── identifier: string (validado @NotBlank)
└── codigoEntidad: string (validado @NotBlank, @Length)
```

### 📧 Email Layer

**Templates:**
- ✅ `password_reset.html.twig` (ya existía)
- ✨ `password_reset_admin_notification.html.twig` (nueva)

---

## Dependencias Agregadas

Todas las clases utilizadas ya existían en el proyecto:

| Clase | Origen | Uso |
|-------|--------|-----|
| `EntityManagerInterface` | Doctrine | Persistencia |
| `PasswordResetTokenRepository` | App | Queries de tokens |
| `UsuarioRepository` | App | Queries de usuarios |
| `EntidadRepository` | App | Queries de entidades |
| `UserPasswordHasherInterface` | Symfony | Hash de contraseñas |
| `EmailQueueService` | App | Enqueue de emails |
| `ValidatorInterface` | Symfony | Validación |
| `AbstractController` | Symfony | Base controller |
| `JsonResponse` | Symfony | Responses HTTP |
| `Request` | Symfony | HTTP Request |

**Conclusión:** ✅ Sin nuevas dependencias externas

---

## Cambios en la Base de Datos

**Campos requeridos (ya deben existir):**

```sql
-- Tabla usuario
ALTER TABLE usuario ADD COLUMN documento_identidad VARCHAR(15) DEFAULT NULL;

-- Tabla password_reset_token
-- Ya debe tener:
-- - id (INT, PK)
-- - token (VARCHAR(128), UNIQUE)
-- - email (VARCHAR(180))  ← Se usa también para "no-email-{userId}"
-- - expires_at (DATETIME)
-- - used_at (DATETIME, NULL)
-- - created_at (DATETIME)
```

**Cambios de datos:** Ninguno  
**Migraciones requeridas:** Ninguna (si la estructura ya existe)

---

## Testing Coverage Recomendado

### Unit Tests (Servicio)
```php
// tests/Unit/Service/PasswordResetServiceTest.php
- testRequestResetByEmailSucceeds()
- testRequestResetByDocumentSucceeds()
- testRequestResetNotifiesAdminWhenNoEmail()
- testRequestResetFailsSilentlyForInactiveUser()
- testRequestResetFailsSilentlyForWrongEntity()
- testResetPasswordWithSpecialToken()
```

### Functional Tests (API)
```php
// tests/Functional/Api/PasswordResetTest.php
- testResetRequestByEmailReturns200()
- testResetRequestByDocumentReturns200()
- testResetRequestWithBadCodeReturns200()
- testResetRequestEnqueuesEmail()
- testResetPasswordUsesValidToken()
```

---

## Notas de Implementación

### ✅ Decisiones Técnicas

1. **Siempre 200 OK:** Previene user enumeration
2. **Búsqueda flexible:** Email o documento según disponibilidad
3. **Admin notification:** No deja usuarios sin opción de reset
4. **Tokens especiales:** Formato "no-email-{userId}" para persistencia
5. **Queue de emails:** Utiliza sistema existente (ColaCorreo)

### ⚠️ Limitaciones Actuales

1. No hay SMS para usuarios sin email (Fase 2+)
2. No hay interfaz de admin para ver solicitudes pendientes (Fase 2+)
3. Admin debe resetear manualmente (no automático)
4. No hay histórico de intentos de reset

### 🔮 Mejoras Futuras

- [ ] SMS como alternativa si no hay email
- [ ] Dashboard admin para ver solicitudes de reset pendientes
- [ ] Reset automático con contraseña temporal
- [ ] Auditoría de intentos de reset fallidos
- [ ] Rate limiting en la API

---

## Checklist de Review

- [x] Código sigue convenciones del proyecto
- [x] Sin dependencias nuevas
- [x] Validaciones completas en la entrada
- [x] Seguridad: user enumeration prevention
- [x] Manejo de excepciones
- [x] Documentación inline
- [x] Documentación externa completa
- [x] Scripts de testing proporcionados
- [x] Integración con sistema existente de emails
- [x] Soporte para usuarios sin email

---

## Estimación de Esfuerzo

| Tarea | Horas | Status |
|-------|-------|--------|
| Backend - Servicio | 1.5 | ✅ |
| Backend - Controller | 1 | ✅ |
| Backend - DTOs | 0.5 | ✅ |
| Email - Plantilla | 0.5 | ✅ |
| Documentación | 2 | ✅ |
| Testing | (A hacer) | |
| Frontend | (A hacer) | |
| **TOTAL BACKEND** | **5.5h** | ✅ |

---

## Contacto / Preguntas

Para dudas sobre la implementación, revisar:
1. `RESUMEN_PASSWORD_RESET.md` - Resumen rápido
2. `IMPLEMENTACION_PASSWORD_RESET.md` - Detalles técnicos
3. `GUIA_INTEGRACION_PASSWORD_RESET.md` - Paso a paso
4. `PASSWORD_RESET_DIAGRAMA.md` - Flujos visuales

---

**Implementación completada:** 27 de mayo de 2026  
**Version:** 1.0

