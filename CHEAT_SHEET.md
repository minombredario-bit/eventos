# 📋 CHEAT SHEET - Password Reset Feature

## 🚀 Quick Start

### Backend Endpoint
```bash
POST /api/password/reset-request
Content-Type: application/json

{
  "identifier": "usuario@example.com",  # o "12345678X"
  "codigoEntidad": "ABC123"
}
```

### Response
```json
{
  "ok": true,
  "message": "Si el usuario existe, recibirá un email..."
}
```

---

## 📁 Files Changed

| File | Type | Lines |
|------|------|-------|
| `src/Service/PasswordResetService.php` | ✏️ Modified | +80 |
| `src/Controller/RegistroController.php` | ✏️ Modified | +40 |
| `src/Dto/PasswordResetRequestInput.php` | ✨ New | 20 |
| `templates/email/password_reset_admin_notification.html.twig` | ✨ New | 30 |

---

## 🔑 Key Methods

### PasswordResetService

```php
// NUEVO - Buscar por email O documento
public function requestResetByEmailOrDocument(
  string $identifier,      // Email o CIF/DNI
  string $entidadId        // UUID de entidad
): bool

// ACTUALIZADO - Ahora soporta tokens especiales
public function resetPassword(
  string $tokenString,     // Token del email
  string $newPassword      // Nueva contraseña (min 8 chars)
): void
```

### RegistroController

```php
// NUEVO - Endpoint de solicitud
#[Route('/password/reset-request', methods: ['POST'])]
public function requestPasswordReset(Request $request): JsonResponse
```

---

## 🔄 Logic Flow

```
User requests reset
    ↓
Controller validates input
    ↓
Find Entity by codigoRegistro
    ↓
Service searches by email or document
    ↓
HAS EMAIL? → Send recovery link to user
         ↓
DOESN'T HAVE EMAIL? → Send notification to admins
                       ↓
                       Admin resets manually
```

---

## ✅ Scenarios

### Scenario A: User with Email
```
1. POST /api/password/reset-request
   identifier: "user@example.com"

2. Email queued to user: "Click here to reset"

3. User clicks link → Can reset password

✓ Email sent ✓ User can self-service
```

### Scenario B: User without Email
```
1. POST /api/password/reset-request
   identifier: "12345678X"

2. Email queued to admin: "User 'Juan García' requests reset..."

3. Admin resets password manually

✓ Admin notified ✓ User supported via admin
```

### Scenario C: User Not Found
```
1. POST /api/password/reset-request
   identifier: "nonexistent@example.com"

2. SILENT (200 OK, no email queued)

✓ Secure (prevents user enumeration)
```

---

## 🧪 Quick Test

### Curl Test
```bash
curl -X POST http://localhost:8000/api/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{
    "identifier":"user@example.com",
    "codigoEntidad":"ABC123"
  }'
```

### Check Database
```sql
-- See queued emails
SELECT destinatario, asunto, estado 
FROM cola_correo 
WHERE createdAt > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
ORDER BY createdAt DESC;

-- See tokens
SELECT * FROM password_reset_token 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE);
```

### Process Queue
```bash
php bin/console app:email:process-queue
```

---

## 🔐 Security Features

| Feature | Description |
|---------|-------------|
| **User Enumeration** | Always 200 OK (no way to tell if user exists) |
| **Entity Validation** | User must belong to requested entity |
| **Token TTL** | 60 minutes only |
| **One-time Use** | Token marked as used after password reset |
| **Input Normalization** | Lowercase emails, trimmed spaces |
| **Active Only** | Only active users and entities accepted |

---

## 🐛 Troubleshooting

### Email not sent?
```bash
# 1. Process the queue
php bin/console app:email:process-queue

# 2. Check if queued
SELECT * FROM cola_correo WHERE estado = 'pendiente';

# 3. Check MAILER_DSN
grep MAILER_DSN .env*
```

### Admin not notified?
```sql
-- Check if user has email
SELECT id, email, documento_identidad 
FROM usuario 
WHERE documento_identidad = '12345678X';

-- Check if entity has admins
SELECT u.email 
FROM usuario u 
WHERE u.entidad_id = '...' 
AND JSON_CONTAINS(u.roles, '"ROLE_ADMIN_ENTIDAD"');
```

### Token invalid?
```bash
# User may need:
# - Valid token (not expired: < 60 min)
# - Unused token
# - Reset with password 8+ chars
```

---

## 📚 Full Docs

| Document | Purpose |
|----------|---------|
| `RESUMEN_PASSWORD_RESET.md` | 📄 Executive summary |
| `IMPLEMENTACION_PASSWORD_RESET.md` | 🔧 Technical details |
| `PASSWORD_RESET_DIAGRAMA.md` | 📊 Diagrams & flows |
| `GUIA_INTEGRACION_PASSWORD_RESET.md` | 📋 Step-by-step setup |
| `INDICE_CAMBIOS.md` | 📑 Change log |

---

## 💻 Frontend Integration

### Service Method
```typescript
requestPasswordReset(
  identifier: string,
  codigoEntidad: string
): Observable<any> {
  return this.http.post('/api/password/reset-request', {
    identifier,
    codigoEntidad
  });
}
```

### Component Template
```html
<form (ngSubmit)="onSubmit()">
  <input [(ngModel)]="codigoEntidad" 
         placeholder="Código de entidad" />
  <input [(ngModel)]="identifier" 
         placeholder="Email o DNI" />
  <button type="submit">Solicitar</button>
</form>
```

---

## 📞 Config Needed

### .env Variables
```bash
# For email to work:
MAILER_DSN=smtp://user:pass@smtp.example.com:587

# Or local testing:
MAILER_DSN=file:///tmp/mailer
```

### Cron Job (Production)
```bash
# Process queued emails every minute
*/1 * * * * php /path/to/app/bin/console app:email:process-queue
```

---

## ✨ Features Summary

✅ Search by email or CIF/DNI  
✅ Send recovery link if has email  
✅ Notify admin if no email  
✅ Prevent user enumeration  
✅ 60-minute token expiry  
✅ One-time token use  
✅ Entity validation  
✅ Existing email queue system  

---

**Implementation Date:** 2026-05-27  
**Status:** ✅ Implemented  
**Ready for:** Testing & Frontend Integration

