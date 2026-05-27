```
FLUJO DE SOLICITUD DE CAMBIO DE CONTRASEÑA
============================================

┌─────────────────────────────────────────────────────────────────────┐
│                    POST /api/password/reset-request                 │
│  { identifier: "user@email.com" o "12345678X", codigoEntidad: "..." }
└────────────────┬────────────────────────────────────────────────────┘
                 │
                 ▼
        ┌────────────────────┐
        │ RegistroController │
        └─────────┬──────────┘
                  │
                  ├─ Valida input (identifier + codigoEntidad)
                  │
                  ├─ Busca Entidad por codigoRegistro
                  │
                  └─ Llama PasswordResetService
                     │
                     └─ requestResetByEmailOrDocument(identifier, entidadId)
                        │
                        ├─ Busca Usuario por EMAIL
                        │
                        └─ Si no existe, busca por DOCUMENTO
                           │
                           └─ Valida: usuario existe, es activo, pertenece a entidad
                              │
                              ├─ OPCIÓN A: Usuario TIENE email
                              │  │
                              │  ├─ Genera token temporal (60 minutos)
                              │  │
                              │  ├─ Queue email: password_reset.html.twig
                              │  │  ✉ "Hola Juan, aquí está tu enlace de recuperación..."
                              │  │
                              │  └─ Usuario recibe email con link de reset
                              │
                              └─ OPCIÓN B: Usuario NO tiene email
                                 │
                                 ├─ Genera token especial (no-email-{userId})
                                 │
                                 ├─ Busca admins de la entidad (ROLE_ADMIN_ENTIDAD)
                                 │
                                 ├─ Si no hay admins, usa email contacto de entidad
                                 │
                                 ├─ Queue email: password_reset_admin_notification.html.twig
                                 │  ✉ "Admin, el usuario Juan solicita reset sin email..."
                                 │
                                 └─ Admin recibe email y puede:
                                    - Usar endpoint /admin para setear password temporal
                                    - O contactar al usuario por otro medio

                        │
                        └─ Flush base de datos
                           │
                           └─ RETORNA: 200 OK (siempre, incluso si usuario no existe)
                              {"ok": true, "message": "Si existe, recibirá instrucciones..."}


TABLA DE ESTADOS
================

┌─────────────┬──────────────────┬──────────────────┬────────────────────────────┐
│ Condición   │ Token Generado   │ Email Enviado    │ Destinatario               │
├─────────────┼──────────────────┼──────────────────┼────────────────────────────┤
│ Con email   │ Sí (normal)      │ Sí               │ Al usuario (su email)      │
│ Sin email   │ Sí (especial)    │ Sí               │ A los admins de entidad    │
│ No existe   │ No               │ No               │ N/A (error silencioso)     │
│ Inactivo    │ No               │ No               │ N/A (error silencioso)     │
│ Otra entid. │ No               │ No               │ N/A (error silencioso)     │
└─────────────┴──────────────────┴──────────────────┴────────────────────────────┘


SEGURIDAD EN DETALLE
====================

1. Prevención de User Enumeration
   ✓ Siempre responde 200 OK
   ✓ Nunca revela si existe usuario o no
   ✓ Mensajes genéricos al cliente

2. Validación de Entidad
   ✓ Verifica que usuario pertenece a la entidad solicitada
   ✓ Evita que alguien solicite reset para usuarios de otra entidad

3. Tokens Temporales
   ✓ Válidos solo 60 minutos
   ✓ De un solo uso
   ✓ Formato especial para usuarios sin email

4. Normalización
   ✓ Emails convertidos a minúsculas
   ✓ Espacios eliminados
   ✓ Case-insensitive en búsquedas

5. Validación de Actividad
   ✓ Solo usuarios activos pueden solicitar reset
   ✓ Solo entidades activas aceptan solicitudes


INTEGRACIÓN CON FRONTEND
=======================

El frontend debe:

1. Escena: Pantalla de Login
   ├─ Existe botón "¿Olvidaste tu contraseña?"
   └─ Lleva a: /auth/password-reset

2. Página: /auth/password-reset
   ├─ Campo 1: "Código de tu entidad"
   ├─ Campo 2: "Tu email o documento de identidad"
   ├─ Botón: "Solicitar recuperación"
   └─ Mensaje: "Recibirás instrucciones en tu email o el admin te contactará"

3. Después de envío:
   ├─ Mostrar: "Solicitud enviada. Revisa tu email."
   ├─ Redirigir a: /auth/login
   └─ Sin importar si usuario existe (por seguridad)

Ejemplo TypeScript:
```typescript
requestPasswordReset(identifier: string, codigoEntidad: string): Observable<any> {
  return this.http.post('/api/password/reset-request', {
    identifier,
    codigoEntidad
  });
}
```


DATOS REQUERIDOS EN USUARIO
============================

Para que esta funcionalidad funcione correctamente, los usuarios deben tener:

OPCIÓN A - Usuario con email:
├─ email (requerido para enviar enlace)
├─ nombre (para personalizar email)
└─ apellidos (para personalizar email)

OPCIÓN B - Usuario sin email:
├─ documentoIdentidad (requerido para búsqueda)
├─ nombre (para notificar admin)
├─ apellidos (para notificar admin)
└─ entidad con admin que tenga email


TROUBLESHOOTING
===============

❌ Usuario solicita reset pero no recibe email
   → Verificar que usuario.email no es null
   → Verificar que la entidad del usuario está activa
   → Revisar la cola de correos (ColaCorreo)
   → Confirmar que se ejecutó processPending() del EmailQueueService

❌ Admin solicita reset pero no recibe notificación
   → Verificar que el usuario NO tiene email (null)
   → Verificar que al menos un admin tiene ROLE_ADMIN_ENTIDAD
   → Verificar emails de los admins
   → Si no hay admins, debe usarse emailContacto de la entidad

❌ Token inválido al intentar resetear
   → Token expirado (> 60 minutos)
   → Token ya usado
   → Entidad inactiva
   → Usuario no existe o está inactivo
```

