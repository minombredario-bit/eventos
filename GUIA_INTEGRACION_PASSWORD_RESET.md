# Guía de Integración - Solicitud de Cambio de Contraseña

## Paso 1: Verificación de Base de Datos

Asegúrate de que estos campos existen en la tabla `usuario`:

```sql
-- Debe existir este campo (nullable)
SHOW COLUMNS FROM usuario WHERE Field = 'documento_identidad';

-- Resultado esperado:
-- Field: documento_identidad, Type: varchar(15), Null: YES
```

Si no existe, crear con migración:

```php
// migrations/Version[timestamp].php
#[ORM\Migration]
final class Version[timestamp] extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD COLUMN documento_identidad VARCHAR(15) DEFAULT NULL');
    }
}
```

Ejecutar:
```bash
php bin/console doctrine:migrations:migrate
```

---

## Paso 2: Verificar Estructura de Entidad

Entidad debe tener relación con usuarios:

```bash
# En Ubuntu/Mac
php bin/console doctrine:mapping:describe App\Entity\Entidad | grep usuarios

# Debería mostrar algo como:
# Column: usuarios (OneToMany)
```

---

## Paso 3: Verificar Configuración de Email (MAILER_DSN)

```bash
# Revisar .env o .env.local
cat .env | grep MAILER_DSN

# Configuraciones comunes:

# Para desarrollo (guardar en archivo):
MAILER_DSN=file:///var/log/mailer

# Para producción (SMTP real):
MAILER_DSN=smtp://user:password@smtp.example.com:587?encryption=tls

# Para testing (sendmail local):
MAILER_DSN=sendmail://default
```

---

## Paso 4: Verificar Plantillas de Email

Las plantillas deben estar en `templates/email/`:

```bash
ls -la templates/email/

# Resultados esperados:
# ✓ password_reset.html.twig (ya debería existir)
# ✓ password_reset_admin_notification.html.twig (creada)
# ✓ base.html.twig (plantilla base)
```

Si falta `password_reset_admin_notification.html.twig`, debe estar en:
```
templates/email/password_reset_admin_notification.html.twig
```

---

## Paso 5: Limpiar Cache y Compilar

```bash
php bin/console cache:clear
php bin/console cache:warmup
```

---

## Paso 6: Procesar Queue de Emails

Para que los emails se envíen realmente, ejecutar:

```bash
# Procesar emails pendientes (manual - para testing)
php bin/console app:email:process-queue

# O configurar cron para ejecutarla cada minuto (producción):
*/1 * * * * cd /var/www/festapp && php bin/console app:email:process-queue
```

Verificar emails en cola:

```bash
# Opción 1: CLI de Symfony
php bin/console doctrine:query:dql "SELECT c FROM App\Entity\ColaCorreo c WHERE c.estado = 'pendiente' ORDER BY c.createdAt DESC LIMIT 10"

# Opción 2: SQL directo
SELECT id, destinatario, asunto, estado, createdAt 
FROM cola_correo 
WHERE estado = 'pendiente'
ORDER BY createdAt DESC
LIMIT 10;
```

---

## Paso 7: Testing del Endpoint

### Test Local (sin envío real de email)

```bash
# Request básico
curl -X POST http://localhost:8000/api/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "usuario@example.com",
    "codigoEntidad": "ABC123XYZ"
  }'

# Respuesta esperada:
# {"ok":true,"message":"Si el usuario existe, recibirá un email..."}
```

### Test Completo (con email real)

```bash
# 1. Crear usuario de prueba (mediante fixture o admin)
# 2. Obtener su código de entidad
# 3. Enviar solicitud
curl -X POST http://localhost:8000/api/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "test@example.com",
    "codigoEntidad": "ABC123XYZ"
  }'

# 4. Procesar cola
php bin/console app:email:process-queue

# 5. Verificar que se envió
# Revisar servidor de email de prueba (MailHog, etc.)
# O verificar logs si está configurado file://
```

---

## Paso 8: Verificar Logs

```bash
# Logs generales
tail -f var/log/dev.log | grep -i password

# Logs de email (si está configurado con file://)
tail -f /var/log/mailer

# Logs de base de datos
php bin/console doctrine:query:dql "SELECT c FROM App\Entity\ColaCorreo c WHERE c.destinatario LIKE '%@%' ORDER BY c.createdAt DESC LIMIT 5"
```

---

## Paso 9: Integración Frontend

### Estructura de Component de Reset

```typescript
// src/app/auth/password-reset/password-reset.component.ts

export class PasswordResetComponent {
  @Input() codigoEntidad!: string;
  
  identifier = signal('');
  loading = signal(false);
  message = signal<string | null>(null);
  
  constructor(
    private authService: AuthService,
    private router: Router
  ) {}
  
  requestReset() {
    if (!this.identifier() || !this.codigoEntidad) return;
    
    this.loading.set(true);
    this.authService.requestPasswordReset(
      this.identifier(),
      this.codigoEntidad
    ).pipe(
      finalize(() => this.loading.set(false)),
      takeUntilDestroyed()
    ).subscribe({
      next: () => {
        this.message.set('✅ Solicitud enviada. Revisa tu email.');
        this.router.navigate(['/auth/login']);
      },
      error: (err) => {
        this.message.set('⚠️ Error al procesar solicitud');
      }
    });
  }
}
```

### Template HTML

```html
<!-- src/app/auth/password-reset/password-reset.component.html -->

<form (ngSubmit)="requestReset()">
  <h2>Recuperar Contraseña</h2>
  
  <div class="form-group">
    <label for="codigo">Código de tu entidad:</label>
    <input 
      id="codigo"
      type="text" 
      [(ngModel)]="codigoEntidad"
      name="codigo"
      placeholder="Ej: ABC123XYZ"
    />
  </div>
  
  <div class="form-group">
    <label for="identifier">Email o Documento (DNI/CIF):</label>
    <input 
      id="identifier"
      type="text" 
      [(ngModel)]="identifier"
      name="identifier"
      placeholder="usuario@example.com o 12345678X"
    />
  </div>
  
  <button 
    type="submit"
    [disabled]="loading() || !identifier() || !codigoEntidad"
  >
    {{ loading() ? 'Enviando...' : 'Solicitar Recuperación' }}
  </button>
  
  @if (message()) {
    <div class="alert">{{ message() }}</div>
  }
</form>
```

### Servicio HTTP

```typescript
// src/app/core/api/auth.service.ts

requestPasswordReset(identifier: string, codigoEntidad: string): Observable<any> {
  return this.http.post(`${this.apiUrl}/password/reset-request`, {
    identifier,
    codigoEntidad
  });
}
```

---

## Paso 10: Testing End-to-End

### Escenario 1: Usuario con Email

```bash
# 1. Precondiciones
# - Usuario: "juan@example.com" con documento_identidad = NULL
# - Entidad: ABC123XYZ
# - Admin con ROLE_ADMIN_ENTIDAD

# 2. Ejecutar
curl -X POST http://localhost:8000/api/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{"identifier":"juan@example.com","codigoEntidad":"ABC123XYZ"}'

# 3. Verificar
php bin/console app:email:process-queue

# Resultado esperado:
# ✓ Email en cola_correo con estado 'pendiente'
# ✓ Después de procesar: estado 'enviado'
# ✓ PasswordResetToken creado
```

### Escenario 2: Usuario sin Email

```bash
# 1. Precondiciones
# - Usuario: "María García" con email = NULL, documento_identidad = "12345678X"
# - Entidad: ABC123XYZ con admin@example.com

# 2. Ejecutar
curl -X POST http://localhost:8000/api/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{"identifier":"12345678X","codigoEntidad":"ABC123XYZ"}'

# 3. Verificar
php bin/console app:email:process-queue

# Resultado esperado:
# ✓ Email en cola_correo dirigido a admin@example.com
# ✓ Asunto: "Solicitud de restablecimiento de contraseña..."
# ✓ Body contiene nombre del usuario sin email
```

### Escenario 3: Usuario No Existe

```bash
# 1. Ejecutar con email inexistente
curl -X POST http://localhost:8000/api/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{"identifier":"noexiste@example.com","codigoEntidad":"ABC123XYZ"}'

# 2. Verificar
# Resultado esperado:
# ✓ 200 OK (mismo que caso exitoso)
# ✓ Mensaje genérico: "Si el usuario existe..."
# ✓ NO se crea token
# ✓ NO se envía email
```

---

## Troubleshooting

### Problema: "Email not found" o similar

**Solución:**
```bash
# 1. Verificar que la entidad existe
php bin/console doctrine:query:dql "SELECT e FROM App\Entity\Entidad e WHERE e.codigoRegistro = 'ABC123'"

# 2. Verificar que el usuario existe
php bin/console doctrine:query:dql "SELECT u FROM App\Entity\Usuario u WHERE u.email = 'test@example.com'"

# 3. Verificar que el usuario es activo
php bin/console doctrine:query:dql "SELECT u FROM App\Entity\Usuario u WHERE u.email = 'test@example.com' AND u.activo = true"
```

### Problema: Emails no se envían

**Solución:**
```bash
# 1. Procesar la cola manualmente
php bin/console app:email:process-queue

# 2. Revisar si hay errores
php bin/console doctrine:query:dql "SELECT c FROM App\Entity\ColaCorreo c WHERE c.estado = 'error' ORDER BY c.createdAt DESC LIMIT 5"

# 3. Revisar MAILER_DSN
grep MAILER_DSN .env .env.local

# 4. Revisar logs
tail -f var/log/dev.log
```

### Problema: Admin no recibe notificación

**Solución:**
```bash
# 1. Verificar que usuario SIN email existe
SELECT nombre, apellidos, email, documento_identidad FROM usuario WHERE documento_identidad = '12345678X';

# 2. Verificar que entidad tiene admins con email
SELECT u.nombre, u.email, u.roles FROM usuario u 
WHERE u.entidad_id = '...' AND JSON_CONTAINS(u.roles, '"ROLE_ADMIN_ENTIDAD"');

# 3. Si no hay admins, verificar email_contacto de entidad
SELECT email_contacto FROM entidad WHERE id = '...';
```

---

## Configuración Final

### `.env.local` (Development)

```bash
# Email de prueba (archivos locales)
MAILER_DSN=file:///tmp/mailer

# O usar MailHog (recomendado)
MAILER_DSN=smtp://localhost:1025
```

### `.env` (Production)

```bash
# SMTP real
MAILER_DSN=smtp://usuario:contraseña@smtp.gmail.com:587?encryption=tls

# O SendGrid
MAILER_DSN=sendgrid+api://key@default

# O Amazon SES
MAILER_DSN=ses+smtp://access_key:secret_key@default
```

---

## Validación Final

Ejecutar estos comandos para confirmar integración:

```bash
# 1. Cache limpio
php bin/console cache:clear && php bin/console cache:warmup

# 2. Tests (si existen)
php bin/phpunit tests/

# 3. Verificar DI
php bin/console debug:container PasswordResetService

# 4. Rutas registradas
php bin/console debug:router | grep password

# Resultado esperado:
# POST /api/password/reset-request
```

---

**¡Listo para producción!** 🚀

