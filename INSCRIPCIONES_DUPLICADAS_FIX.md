# Fix para Inscripciones Duplicadas - Documentación de Cambios

## Problema Identificado

El sistema permitía crear **múltiples inscripciones para el mismo usuario en el mismo evento**, violando el requisito del REQUIREMENTS.md:
- Línea 304: `usuario_id + evento_id` debe ser único
- Línea 440: "Un usuario no puede inscribirse dos veces al mismo evento"

### Ejemplo de Duplicados
```json
{
  "evento": "981a79b0-e639-4eef-a48f-84507a92901a",  // Evento 1
  "usuario": "user-123",
  "inscripcion-1": "170e49ed-92a4-412f-8429-41a2a2bc0089",
  "inscripcion-2": "cf9ae98c-b06b-4950-a4f0-8a24eb8c1ef4"  // ❌ DUPLICADA
}
```

---

## Root Cause

1. **Falta de constraint en Base de Datos**: Tabla `inscripcion` sin restricción única
2. **Falta de constraint en Entity**: `Inscripcion.php` sin `UniqueConstraint`
3. **Validación insuficiente en servicio**: No captura violaciones de constraint

---

## Cambios Implementados

### 1. ✅ Actualización de `Inscripcion.php`

**Archivo**: `backend/src/Entity/Inscripcion.php`

Se añadió `uniqueConstraints` a la tabla:

```php
#[ORM\Entity(repositoryClass: InscripcionRepository::class)]
#[ORM\Table(
    name: 'inscripcion',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_inscripcion_usuario_evento', columns: ['usuario_id', 'evento_id']),
    ],
)]
```

**Impacto**: Doctrine ahora conoce el constraint y lo validará en ORM.

---

### 2. ✅ Creación de Migración

**Archivo**: `backend/migrations/Version20260601000000.php`

La migración hace tres cosas:

1. **Limpia líneas de inscripción duplicadas**: Elimina las líneas asociadas a inscripciones que se borrarán
2. **Limpia pagos duplicados**: Elimina los pagos asociados a inscripciones que se borrarán
3. **Mantiene la inscripción más antigua**: Borra duplicados, conservando el primero (MIN id)
4. **Añade constraint única**: Crea la restricción en BD para prevenir futuros duplicados

```sql
-- Elimina duplicados, mantiene la más antigua para cada usuario+evento
ALTER TABLE inscripcion ADD CONSTRAINT uniq_inscripcion_usuario_evento UNIQUE (usuario_id, evento_id);
```

---

### 3. ✅ Mejora de Manejo de Errores

**Archivo**: `backend/src/Service/InscripcionService.php`

Se añadió:
- Import de `UniqueConstraintViolationException`
- Try-catch al hacer flush() para capturar violaciones de constraint
- Mensaje de error claro si se intenta crear duplicado

```php
try {
    $this->entityManager->flush();
} catch (UniqueConstraintViolationException $e) {
    if (str_contains($e->getMessage(), 'uniq_inscripcion_usuario_evento')) {
        throw new BadRequestHttpException('Ya existe una inscripción para este usuario en este evento. No puedes crear otra.');
    }
    throw $e;
}
```

---

## Instrucciones de Ejecución

### Paso 1: Aplicar la migración

```bash
cd backend
php bin/console doctrine:migrations:migrate
```

**Output esperado**:
```
Migrating up to DoctrineMigrations\Version20260601000000

  >> DoctrineMigrations\Version20260601000000
     Add unique constraint to inscripcion (usuario_id + evento_id) to prevent duplicates...
```

### Paso 2: Regenerar proxies (si es necesario)

```bash
php bin/console cache:clear
```

### Paso 3: Verificar la base de datos

```sql
-- Verificar que existe el constraint
SHOW CREATE TABLE inscripcion;

-- Verificar que no hay duplicados
SELECT usuario_id, evento_id, COUNT(*) as duplicados
FROM inscripcion
GROUP BY usuario_id, evento_id
HAVING COUNT(*) > 1;
-- Resultado esperado: 0 filas
```

---

## Beneficios

✅ **Prevención de duplicados futuro**: Constraint a nivel de BD evita cualquier duplicado

✅ **Validación en ORM**: Doctrine lanza excepción cuando se intenta crear duplicado

✅ **Mensajes claros**: Usuario recibe error informativo sin detalles técnicos

✅ **Compatible con requisitos**: Cumple línea 304 y 440 de REQUIREMENTS.md

---

## Test Manual (Después de migrar)

### Scenario 1: Crear una inscripción normal ✅

```
POST /api/inscripcions
{
  "evento": "/api/eventos/123",
  "usuario": "user-456",
  "lineas": [...]
}
→ 201 Created (OK)
```

### Scenario 2: Intentar crear duplicado ❌

```
POST /api/inscripcions
{
  "evento": "/api/eventos/123",
  "usuario": "user-456",  // Mismo usuario + evento
  "lineas": [...]
}
→ 400 Bad Request
   "Ya existe una inscripción para este usuario en este evento. No puedes crear otra."
```

---

## Rollback (Si es necesario)

```bash
php bin/console doctrine:migrations:migrate Version20260505141212
```

Esto revierte a la migración anterior, pero **NO restaura los datos eliminados** en el cleanup.

---

## Notas Técnicas

- La migración usa `MIN(i.id)` para identificar qué inscripción mantener (la más antigua por fecha de creación)
- Las líneas y pagos asociados a inscripciones duplicadas se borran en cascada (integridad referencial)
- El constraint se aplica **después** de la limpieza para evitar errores de integridad
- Doctrine regenera automáticamente los mapeos de entidades al migrar

---

## Archivos Modificados

```
backend/src/Entity/Inscripcion.php                          ✅ Modificado
backend/src/Service/InscripcionService.php                  ✅ Modificado
backend/migrations/Version20260601000000.php                ✅ Creado (NUEVO)
```

---

Fecha: 2026-06-01
Versión: 1.0
Estado: Listo para migración

