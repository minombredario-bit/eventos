# Implementación: Solicitud de Cambio de Contraseña (Email o CIF)

## Resumen

Se ha implementado la funcionalidad de **solicitud de cambio de contraseña** donde se puede buscar al usuario por:
- **Email** (si está registrado)
- **Documento de identidad (DNI/CIF)** (si está registrado)

La lógica es:
1. Si el usuario tiene email: se le envía un enlace de recuperación de contraseña
2. Si el usuario NO tiene email: se notifica a los administradores de la entidad para que restablezcan la contraseña manualmente

## Cambios Realizados

### 1. **DTO Request** - `PasswordResetRequestInput`
**Archivo:** `src/Dto/PasswordResetRequestInput.php`

Define la entrada esperada:
- `identifier`: Email o documento de identidad
- `codigoEntidad`: Código de registro de la entidad (requerido para validación)

### 2. **Servicio** - `PasswordResetService`
**Archivo:** `src/Service/PasswordResetService.php`

Se añadió el método `requestResetByEmailOrDocument()` que:
- Acepta un `identifier` (email o documento) y el ID de la entidad
- Busca primero por email, luego por documento
- Valida que el usuario esté activo y pertenezca a la entidad
- Si tiene email: envía un email de recuperación
- Si NO tiene email: notifica a los admins de la entidad
- Genera un token temporal (válido 60 minutos)

También se actualizó `resetPassword()` para manejar tokens especiales para usuarios sin email.

### 3. **Controller** - `RegistroController`
**Archivo:** `src/Controller/RegistroController.php`

Se añadió el endpoint:
```
POST /api/password/reset-request
```

**Request:**
```json
{
  "identifier": "user@example.com",  // O "12345678X"
  "codigoEntidad": "ABC123XYZ"
}
```

**Response:**
```json
{
  "ok": true,
  "message": "Si el usuario existe, recibirá un email con instrucciones o se notificará al administrador."
}
```

El endpoint:
- Valida los datos de entrada
- Busca la entidad por código de registro
- Llama al servicio de reset
- **Siempre devuelve 200 OK** (por seguridad, para evitar enumeración de usuarios)

### 4. **Plantilla Email** - Notificación al Admin
**Archivo:** `templates/email/password_reset_admin_notification.html.twig`

Se envía cuando un usuario sin email solicita reset:
- Notifica al admin sobre la solicitud
- Incluye datos del usuario (nombre, apellidos, documento)
- Proporciona instrucciones para resetear la contraseña manualmente

### 5. **Lógica de Envío de Emails**

El `EmailQueueService` ya existía y se utiliza para encolar los emails:
- **Con email:** Se envía directamente al usuario (usando la plantilla existente `password_reset.html.twig`)
- **Sin email:** Se envía a todos los admins de la entidad (ROLE_ADMIN_ENTIDAD) o al email de contacto de la entidad si no hay admins

## Flujo Completo

### Caso 1: Usuario CON email
```
1. Usuario solicita reset: POST /api/password/reset-request
2. Sistema busca por email o documento
3. Encuentra usuario con email
4. Genera token temporal
5. Envía email al usuario con enlace de recuperación
6. Usuario recibe email y puede resetear su contraseña
```

### Caso 2: Usuario SIN email
```
1. Usuario solicita reset: POST /api/password/reset-request
2. Sistema busca por documento (DNI/CIF)
3. Encuentra usuario sin email
4. Genera token temporal
5. Envía email a los admins de la entidad
6. Admin recibe notificación sobre la solicitud
7. Admin restablece manualmente la contraseña del usuario
   (puede usar /api/admin/usuarios/{id} o similar)
```

## Seguridad

✅ **User Enumeration Prevention:** El endpoint siempre devuelve OK (200), sin importar si el usuario existe o no

✅ **Validación de Entidad:** Se verifica que el usuario pertenece a la entidad solicitada

✅ **Token Temporal:** Válido solo 60 minutos y de un solo uso

✅ **Normalización de Input:** Emails en minúsculas, espacios trimmed

✅ **Actividad:** Solo usuarios activos pueden solicitar reset

## Testing

### Casos de Test Recomendados

1. **Reset por email existente**
   - POST `/api/password/reset-request`
   - Body: `{ "identifier": "user@example.com", "codigoEntidad": "ABC123" }`
   - Verificar: Email de recuperación enviado

2. **Reset por documento existente (sin email)**
   - POST `/api/password/reset-request`
   - Body: `{ "identifier": "12345678X", "codigoEntidad": "ABC123" }`
   - Verificar: Email a admins enviado

3. **Entidad inactiva**
   - POST `/api/password/reset-request`
   - Body: `{ "identifier": "user@example.com", "codigoEntidad": "BADCODE" }`
   - Verificar: 200 OK (silencioso)

4. **Usuario inactivo**
   - Crear usuario inactivo
   - POST con su email
   - Verificar: No se envía email (silencioso)

## Integración Frontend

El frontend debe llamar al endpoint:

```typescript
requestPasswordReset(identifier: string, codigoEntidad: string) {
  return this.http.post('/api/password/reset-request', {
    identifier,
    codigoEntidad
  });
}
```

Y mostrar un mensaje genérico al usuario:
> "Si el usuario existe en nuestra base de datos, recibirá un email con instrucciones para restablecer su contraseña, o el administrador será notificado."

## Notas Técnicas

- El campo `Usuario.documentoIdentidad` se utiliza para búsqueda por CIF/DNI
- Es nullable, por lo que no todos los usuarios necesitan tenerlo
- Los tokens sin email usan el formato `'no-email-' . $usuarioId` en la base de datos
- La búsqueda es case-insensitive para el email
- Admins notificados: usuarios con rol `ROLE_ADMIN_ENTIDAD` de la misma entidad, o si ninguno existe, el email de contacto de la entidad

