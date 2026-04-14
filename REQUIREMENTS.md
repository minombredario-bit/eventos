# Requisitos funcionales y técnicos — Festapp

> Versión 1.2 — Franjas de comida por evento y compatibilidad de menú por tipo de persona

---

## 1. Objetivo del sistema

Aplicación móvil multiplataforma (Android + iPhone vía PWA instalable) para gestionar comidas, almuerzos, meriendas y cenas de fallas, comparsas y entidades festivas similares, incluyendo la semana fallera y eventos del resto del año.

El sistema debe permitir:

- gestión multi-entidad desde un único superadministrador
- inscripción de socios y sus familiares a eventos
- selección de menús por persona con precios diferenciados (interno/externo)
- control de inscripciones, aforos y listas de espera
- control y registro de pagos (manual en fase 1, pasarela en fase 3)
- listados operativos para cocina y organización
- credencial visual de acceso para eventos con control de entrada
- uso desde móvil como app instalable (PWA)

---

## 2. Roles del sistema

### 2.1 Superadministrador (`ROLE_SUPERADMIN`)

Rol global con acceso completo al sistema. Solo existe uno o muy pocos usuarios con este rol.

Puede:

- crear, editar y desactivar entidades en el sistema
- subir el Excel del censo de cada entidad (nombre, apellidos, email, DNI, parentesco, tipo)
- generar y regenerar el código de registro único de cada entidad
- asignar uno o varios admins a cada entidad
- ver estadísticas globales por entidad
- acceder a cualquier entidad en modo lectura para soporte

No gestiona eventos, menús ni inscripciones de ninguna entidad directamente.

### 2.2 Administrador de entidad (`ROLE_ADMIN_ENTIDAD`)

Rol limitado a una única entidad. Es el responsable operativo del día a día.

Puede:

- crear y editar eventos de su entidad
- abrir y cerrar inscripciones por evento
- crear menús y definir precios, franja de comida y compatibilidad por tipo de persona
- ver todas las inscripciones de su entidad
- validar o rechazar usuarios y familiares pendientes
- dar de alta y de baja a usuarios en el censo interno
- registrar pagos manualmente
- exportar listados (Excel, PDF)
- subir o actualizar el censo si el superadmin le delega ese permiso (delegable por entidad)
- activar el modo de verificación de acceso para eventos que lo requieran

### 2.3 Usuario (`ROLE_USER`)

Socio o miembro de una falla. Accede solo cuando ha sido validado.

Puede:

- iniciar sesión una vez validado
- editar solo email, teléfono, relaciones y forma de pago preferida
- gestionar su unidad familiar (alta, edición, baja lógica)
- inscribirse a eventos y apuntar a sus familiares validados
- seleccionar menú por persona en cada inscripción
- consultar sus inscripciones y pagos
- ver la credencial visual de acceso cuando corresponda

---

## 3. Entidades del sistema

### 3.1 Entidad

Representa cada organización registrada en el sistema: falla, comparsa de moros y cristianos, peña festera, hermandad, club, asociación u cualquier otra.

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| nombre | string | |
| slug | string | único global, para URLs |
| descripcion | text | nullable |
| logo | string | ruta al archivo, nullable |
| tipoEntidad | enum TipoEntidadEnum | FALLA / COMPARSA / PENYA / HERMANDAD / ASOCIACION / CLUB / OTRO |
| terminologiaSocio | string | ej. "fallero", "festero", "socio", "hermano" — para adaptar la UI |
| terminologiaEvento | string | ej. "comida", "acto", "festejo" — para adaptar la UI |
| emailContacto | string | |
| telefono | string | nullable |
| direccion | string | nullable |
| codigoRegistro | string | único global, generado por superadmin |
| temporadaActual | string | ej. "2025" |
| activa | boolean | |
| censado | boolean | activa/desactiva gestión censal de la entidad |
| createdAt | datetime | |
| updatedAt | datetime | |

Relaciones:

- 1:N con Usuario
- 1:N con Evento
- N:M con Usuario (admins de la entidad)

| Campo | Tipo | Notas |
|---|---|---|
| id | int | |
| entidad | FK Entidad | |
| nombre | string | |
| apellidos | string | |
| email | string | nullable, usado para matching |
| dni | string | nullable, matching alternativo |
| parentesco | string | titular / pareja / hijo / otro |
| tipoPersona | enum TipoPersonaEnum | adulto / infantil |
| tipoRelacionEconomica | enum TipoRelacionEconomicaEnum | interno / externo / invitado |
| temporada | string | ej. "2025" |
| usuarioVinculado | FK Usuario | nullable, se rellena al validar |
| procesado | boolean | true cuando se vincula a un usuario |
| createdAt | datetime | |

Reglas:

- el matching se hace primero por email, luego por DNI si no hay email
- si hay más de una coincidencia en el censo, no se valida automáticamente
- una entrada procesada no puede usarse para validar otro usuario

### 3.3 Usuario

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| entidad | FK Entidad | obligatorio |
| nombre | string | |
| apellidos | string | |
| email | string | único global |
| telefono | string | nullable |
| password | string | hasheado |
| roles | json | array de roles |
| activo | boolean | |
| tipoUsuarioEconomico | enum | INTERNO / EXTERNO / INVITADO |
| estadoValidacion | enum | PENDIENTE_VALIDACION / VALIDADO / RECHAZADO / BLOQUEADO |
| puedeAcceder | boolean | computed: validado y no bloqueado |
| esCensadoInterno | boolean | |
| codigoRegistroUsado | string | nullable, trazabilidad administrativa |
| censadoVia | enum CensadoViaEnum | EXCEL / MANUAL / INVITACION |
| formaPagoPreferida | enum MetodoPagoEnum | nullable |
| antiguedad | int | nullable |
| fechaSolicitudAlta | datetime | nullable |
| fechaAltaCenso | datetime | nullable |
| fechaBajaCenso | datetime | nullable |
| motivoBajaCenso | string | nullable |
| validadoPor | FK Usuario | nullable |
| fechaValidacion | datetime | nullable |
| createdAt | datetime | |
| updatedAt | datetime | |

### 3.4 PersonaFamiliar

Cada asistente posible dentro de la unidad familiar de un usuario.

| Campo | Tipo | Notas |
|---|---|---|
| id | int | |
| usuarioPrincipal | FK Usuario | |
| nombre | string | |
| apellidos | string | |
| parentesco | string | |
| tipoPersona | enum TipoPersonaEnum | ADULTO / INFANTIL |
| fechaNacimiento | date | nullable |
| observaciones | text | nullable |
| activa | boolean | |
| tipoRelacionEconomica | enum | INTERNO / EXTERNO / INVITADO |
| estadoValidacion | enum EstadoValidacionEnum | |
| validadoPor | FK Usuario | nullable |
| fechaValidacion | datetime | nullable |
| createdAt | datetime | |
| updatedAt | datetime | |

### 3.5 Evento

| Campo | Tipo | Notas |
|---|---|---|
| id | int | |
| entidad | FK Entidad | |
| titulo | string | |
| slug | string | único por entidad |
| descripcion | text | nullable |
| tipoEvento | enum TipoEventoEnum | |
| fechaEvento | date | |
| horaInicio | time | nullable |
| horaFin | time | nullable |
| lugar | string | nullable |
| aforo | int | nullable |
| fechaInicioInscripcion | datetime | |
| fechaFinInscripcion | datetime | |
| visible | boolean | |
| publicado | boolean | |
| admitePago | boolean | |
| estado | enum EstadoEventoEnum | |
| requiereVerificacionAcceso | boolean | |
| ventanaInicioVerificacion | datetime | nullable |
| ventanaFinVerificacion | datetime | nullable |
| imagenVerificacion | string | nullable |
| codigoVisual | string | nullable |
| createdAt | datetime | |
| updatedAt | datetime | |

### 3.6 ActividadEvento

| Campo | Tipo | Notas |
|---|---|---|
| id | int | |
| evento | FK Evento | |
| nombre | string | |
| descripcion | text | nullable |
| tipoActividad | enum TipoActividadEnum | ADULTO / INFANTIL / ESPECIAL / LIBRE |
| franjaComida | enum FranjaComidaEnum | ALMUERZO / COMIDA / MERIENDA / CENA |
| compatibilidadPersona | enum CompatibilidadPersonaActividadEnum | ADULTO / INFANTIL / AMBOS |
| esDePago | boolean | |
| precioBase | decimal(8,2) | fallback si no hay precio específico |
| precioAdultoInterno | decimal(8,2) | nullable |
| precioAdultoExterno | decimal(8,2) | nullable |
| precioInfantil | decimal(8,2) | nullable |
| unidadesMaximas | int | nullable |
| ordenVisualizacion | int | |
| activo | boolean | |
| confirmacionAutomatica | boolean | si es gratuito y no requiere revisión |
| observacionesInternas | text | nullable |
| createdAt | datetime | |
| updatedAt | datetime | |

### 3.7 Inscripcion

| Campo | Tipo | Notas |
|---|---|---|
| id | int | |
| codigo | string | único, generado automáticamente |
| entidad | FK Entidad | desnormalizado para queries rápidas |
| evento | FK Evento | |
| usuario | FK Usuario | |
| estadoInscripcion | enum EstadoInscripcionEnum | |
| estadoPago | enum EstadoPagoEnum | |
| importeTotal | decimal(8,2) | calculado en backend |
| importePagado | decimal(8,2) | |
| moneda | string | EUR por defecto |
| metodoPago | enum MetodoPagoEnum | nullable |
| referenciaPago | string | nullable |
| fechaPago | datetime | nullable |
| observaciones | text | nullable |
| createdAt | datetime | |
| updatedAt | datetime | |

### 3.8 InscripcionLinea

| Campo                         | Tipo | Notas |
|-------------------------------|---|---|
| id                            | int | |
| inscripcion                   | FK Inscripcion | |
| persona                       | FK PersonaFamiliar | |
| actividad                     | FK ActividadEvento | |
| nombrePersonaSnapshot         | string | histórico |
| tipoPersonaSnapshot           | string | histórico |
| tipoRelacionEconomicaSnapshot | string | histórico |
| estadoValidacionSnapshot      | string | histórico |
| nombreActividadSnapshot       | string | histórico |
| franjaComidaSnapshot          | string | histórico |
| esDePagoSnapshot              | boolean | histórico |
| precioUnitario                | decimal(8,2) | precio real aplicado, histórico |
| estadoLinea                   | enum EstadoLineaInscripcionEnum | |
| pagada                        | boolean | `true` si la línea quedó cubierta por un pago confirmado |
| observaciones                 | text | nullable |
| createdAt                     | datetime | |

### 3.9 Pago

| Campo | Tipo | Notas |
|---|---|---|
| id | int | |
| inscripcion | FK Inscripcion | |
| fecha | datetime | |
| importe | decimal(8,2) | |
| metodoPago | enum MetodoPagoEnum | |
| referencia | string | nullable |
| estado | string | confirmado / anulado |
| observaciones | text | nullable |
| registradoPor | FK Usuario | admin que lo registró |
| createdAt | datetime | |

---

## 4. Enumerados

```
TipoEntidadEnum:        FALLA, COMPARSA, PENYA, HERMANDAD, ASOCIACION, CLUB, OTRO
TipoEventoEnum:         ALMUERZO, COMIDA, MERIENDA, CENA, OTRO
TipoPersonaEnum:        ADULTO, INFANTIL
FranjaComidaEnum:       ALMUERZO, COMIDA, MERIENDA, CENA
CompatibilidadPersonaActividadEnum: ADULTO, INFANTIL, AMBOS
TipoRelacionEconomicaEnum: INTERNO, EXTERNO, INVITADO
EstadoValidacionEnum:   PENDIENTE_VALIDACION, VALIDADO, RECHAZADO, BLOQUEADO
TipoActividadEnum:      ADULTO, INFANTIL, ESPECIAL, LIBRE
EstadoEventoEnum:       BORRADOR, PUBLICADO, CERRADO, FINALIZADO, CANCELADO
EstadoInscripcionEnum:  PENDIENTE, CONFIRMADA, CANCELADA, LISTA_ESPERA
EstadoPagoEnum:         NO_REQUIERE_PAGO, PENDIENTE, PARCIAL, PAGADO, DEVUELTO, CANCELADO
MetodoPagoEnum:         EFECTIVO, TRANSFERENCIA, BIZUM, TPV, ONLINE, MANUAL
EstadoLineaInscripcionEnum: PENDIENTE, CONFIRMADA, CANCELADA
CensadoViaEnum:         EXCEL, MANUAL, INVITACION
```

---

## 5. Reglas de negocio

### 5.1 Registro y acceso

- no existe autorregistro de usuarios
- el alta de usuarios la realiza exclusivamente un administrador de entidad, manualmente o por importación Excel
- los usuarios pendientes de validación, rechazados, bloqueados o dados de baja (`activo = false`) no pueden iniciar sesión
- si la entidad del usuario está inactiva (`entidad.activa = false`) tampoco puede iniciar sesión
- el código de registro puede regenerarse; el código antiguo queda inválido inmediatamente

### 5.2 Precios

- el backend decide siempre el precio; nunca se confía en el frontend
- si `esDePago = false`, el precio aplicado es 0 independientemente del resto
- si `esDePago = true`, el precio depende del tipo de menú y la condición económica validada de la persona
- si la persona es INTERNO: se aplica `precioAdultoInterno` o `precioInfantil` según el menú elegido
- si la persona es EXTERNO o INVITADO: se aplica `precioAdultoExterno` o `precioInfantil`
- si el menú elegido es de tipo INFANTIL: se aplica siempre `precioInfantil`, sea adulto o infantil quien lo elija
- si el menú elegido es de tipo ADULTO: se aplica precio adulto aunque quien se inscriba sea infantil
- si no existe precio específico, se cae a `precioBase`
- una persona con `estadoValidacion` distinto de VALIDADO no puede beneficiarse de precios internos
- si el usuario está dado de baja del censo interno, en nuevas inscripciones se le aplica precio externo

### 5.3 Inscripciones

- no se puede inscribir fuera del rango de fechas de inscripción
- si el evento está cerrado no admite nuevas inscripciones
- el menú elegido debe pertenecer al evento y estar activo
- cada menú pertenece a una franja de comida (almuerzo/comida/merienda/cena)
- una persona puede tener varias líneas en el mismo evento, pero solo una por franja
- no se puede seleccionar un menú incompatible con el tipo de persona (adulto/infantil)
- el importe total se calcula en el backend sumando solo las líneas de pago
- si el importe total es 0, el estado de pago es `NO_REQUIERE_PAGO` y la inscripción puede confirmarse automáticamente
- se guardan snapshots en cada línea en el momento de la inscripción
- el administrador puede forzar altas manuales y cancelar inscripciones
- una línea con `pagada = true` no se puede modificar ni cancelar
- las líneas nuevas añadidas después de un pago se crean con `pagada = false`
- desde la pantalla de detalle del usuario no se elimina la inscripción; solo se permite gestionar líneas no pagadas

### 5.7 Reglas de pago y diferencia pendiente

- no se admite pago parcial manual: cada registro de pago liquida el pendiente completo
- el importe a pagar en la pantalla de pago es siempre `importeTotal - importePagado`
- si tras pagar se añaden líneas nuevas, el siguiente pago cubre solo la diferencia pendiente
- al confirmar un pago se marca como `pagada = true` toda línea activa incluida en ese saldo pendiente

### 5.4 Credencial visual

- la credencial solo se muestra si la inscripción está confirmada y el evento la requiere
- la ventana de visualización se controla en el backend; no se confía en la hora del dispositivo
- si el acceso depende del pago, no se muestra la credencial mientras el pago esté pendiente
- la credencial debe incluir un token o marca temporal para evitar reutilización de capturas de pantalla

### 5.5 Censo y bajas

- un usuario puede ser dado de baja del censo interno sin ser eliminado
- la baja solo afecta a nuevas inscripciones y precios futuros
- el histórico de inscripciones no cambia
- se registran `fechaAltaCenso` y `fechaBajaCenso` para trazabilidad

---

## 6. API endpoints

### Autenticación y registro

```
POST   /api/login                             Obtiene JWT
POST   /api/password/reset-request           Solicita recuperación de contraseña
POST   /api/password/reset                    Restablece contraseña con token
GET    /api/me                                Perfil del usuario autenticado
PATCH  /api/me                                Edita solo email, teléfono y formaPagoPreferida
```

### Familia

```
GET    /api/persona_familiares                Lista familiares del usuario
POST   /api/persona_familiares                Alta de familiar
PATCH  /api/persona_familiares/{id}           Edita familiar
DELETE /api/persona_familiares/{id}           Baja lógica
```

### Eventos (usuario)

```
GET    /api/eventos                           Lista eventos visibles/publicados de la entidad
GET    /api/eventos/{id}                      Detalle de evento
GET    /api/actividad_eventos?evento={id}     Menús de un evento
GET    /api/eventos/{id}/mi-credencial        Credencial visual (si procede)
```

### Inscripciones (usuario)

```
GET    /api/inscripciones/mias               Mis inscripciones
POST   /api/eventos/{id}/inscribirme          Crear inscripción con líneas
GET    /api/inscripciones/{id}                Detalle de inscripción
POST   /api/inscripciones/{id}/cancelar       Cancelar inscripción
```

### Admin de entidad — usuarios

```
GET    /api/admin/usuarios                    Lista usuarios de la entidad
GET    /api/admin/usuarios-pendientes         Usuarios pendientes de validación
POST   /api/admin/usuarios/{id}/validar       Valida usuario manualmente
POST   /api/admin/usuarios/{id}/rechazar      Rechaza usuario
POST   /api/admin/usuarios/{id}/alta-censo    Alta en censo interno
POST   /api/admin/usuarios/{id}/baja-censo    Baja en censo interno
GET    /api/admin/persona_familiares-pendientes  Familiares pendientes
POST   /api/admin/persona_familiares/{id}/validar
POST   /api/admin/persona_familiares/{id}/rechazar
```

### Admin de entidad — eventos y menús

```
GET    /api/admin/eventos                     Todos los eventos de la entidad
POST   /api/eventos                           Crear evento
PATCH  /api/eventos/{id}                      Editar evento
POST   /api/eventos/{id}/publicar             Publica evento
POST   /api/eventos/{id}/cerrar               Cierra inscripciones
POST   /api/actividad_eventos                 Crear menú
PATCH  /api/actividad_eventos/{id}            Editar menú
```

### Admin de entidad — inscripciones y pagos

```
GET    /api/admin/inscripciones               Lista inscripciones del evento o entidad
GET    /api/admin/inscripciones/{id}          Detalle con líneas
POST   /api/inscripciones/{id}/registrar_pago Registrar pago manual
GET    /api/admin/pagos                        Lista de pagos
```

Notas de comportamiento para `registrar_pago`:

- liquida el pendiente completo de la inscripción (no parcial)
- recalcula `estadoPago` en base a `importeTotal` e `importePagado`
- deja trazabilidad por línea mediante el flag `pagada`

### Admin de entidad — reportes

```
GET    /api/eventos/{id}/reporte-resumen      Resumen por menú
GET    /api/eventos/{id}/reporte-personas     Listado nominal
GET    /api/eventos/{id}/reporte-actividad    Agrupado por menú para cocina
GET    /api/eventos/{id}/reporte-pagos        Estado de pagos
```

### Superadmin — fallas

```
GET    /api/superadmin/entidades                 Lista todas las entidades
POST   /api/superadmin/entidades                 Crear entidad
PATCH  /api/superadmin/entidades/{id}            Editar entidad
POST   /api/superadmin/entidades/{id}/codigo-registro/regenerar
POST   /api/superadmin/entidades/{id}/admins     Asigna admin a entidad
```

---

## 7. Payload de inscripción

### Request

```json
POST /api/eventos/42/inscribirme

{
  "personas": [
    {
      "persona": "/api/persona_familiares/1",
      "actividad": "/api/actividad_eventos/10",
      "observaciones": "Sin cebolla"
    },
    {
      "persona": "/api/persona_familiares/2",
      "actividad": "/api/actividad_eventos/11",
      "observaciones": null
    }
  ]
}
```

### Response

```json
{
  "id": 123,
  "codigo": "FAL-2025-00123",
  "estado": "CONFIRMADA",
  "estadoPago": "PENDIENTE",
  "importeTotal": 27.00,
  "importePagado": 0.00,
  "lineas": [
    {
      "persona": "Ana García",
      "actividad": "Actividad adulto",
      "precioUnitario": 15.00,
      "esDePago": true
    },
    {
      "persona": "Pablo García",
      "actividad": "Actividad infantil",
      "precioUnitario": 12.00,
      "esDePago": true
    }
  ]
}
```

---

## 8. Pantallas principales

### 8.1 Usuario

| Pantalla | Descripción |
|---|---|
| Splash / acceso | Logo de la entidad, login o registro |
| Registro paso 1 | Introducir código de entidad |
| Registro paso 2 | Datos personales, contraseña |
| Pendiente de validación | Pantalla informativa si no hay coincidencia en censo |
| Login | Email y contraseña |
| Inicio | Calendario mensual con eventos marcados |
| Listado de eventos | Próximos, con inscripción abierta, mis reservas |
| Detalle de evento | Info, menús, plazo, botón inscribirse |
| Selección de asistentes | Selector por persona y por franja (almuerzo/comida/merienda/cena), con bloqueo de menús incompatibles y bloqueo por línea pagada |
| Resumen de inscripción | Asistentes, menús, subtotal, total, estado pago |
| Mis inscripciones | Lista con estado |
| Mis pagos | Historial |
| Mi familia | Gestión de PersonaFamiliar |
| Perfil | Datos personales, cambiar contraseña |
| Credencial de acceso | Pase visual con token temporal |

### 8.2 Admin de entidad

| Pantalla | Descripción |
|---|---|
| Dashboard | KPIs rápidos de la entidad |
| Calendario de eventos | Vista mes/semana/día con filtros |
| Crear / editar evento | Formulario completo |
| Gestionar menús | CRUD de menús de un evento |
| Listado de inscripciones | Filtros por evento, estado, pago |
| Detalle de inscripción | Líneas, pagos, historial |
| Listado de pagos | Con exportación |
| Usuarios pendientes | Validar / rechazar |
| Gestión de censo | Entradas vinculadas y sin vincular |
| Exportes | Selección de evento y tipo de reporte |
| Verificación de acceso | Pantalla para el control en puerta |

### 8.3 Superadmin

| Pantalla | Descripción |
|---|---|
| Lista de entidades | Estado, estadísticas básicas |
| Crear / editar entidad | Formulario con logo y configuración |
| Importar censo | Upload de Excel con previsualización |
| Historial de importaciones | Fecha, usuario, entradas procesadas |
| Código de registro | Ver, copiar, regenerar |
| Asignación de admins | Vincular usuarios como admin de entidad |

---

## 9. Casos de cálculo

### Todo gratuito

```
Ana → menú infantil gratuito = 0 €
Pablo → menú infantil gratuito = 0 €
→ importeTotal = 0 | estadoPago = NO_REQUIERE_PAGO | confirmación automática
```

### Todo de pago

```
Juan → menú adulto interno = 15 €
María → menú adulto interno = 15 €
→ importeTotal = 30 € | estadoPago = PENDIENTE
```

### Mezcla gratuito + pago

```
Juan → menú adulto = 15 €
Ana → menú infantil gratuito = 0 €
→ importeTotal = 15 € | estadoPago = PENDIENTE
```

### Externo con sobreprecio

```
precioAdultoInterno = 12 €
precioAdultoExterno = 25 €
→ INTERNO paga 12 € | EXTERNO paga 25 €
```

### Adulto elige menú infantil

```
persona: adulto | menú: infantil | precioInfantil = 8 €
→ paga 8 €
```

### Infantil elige menú adulto

```
persona: infantil | menú: adulto | precioAdultoInterno = 12 €
→ paga 12 € (interno) o 25 € (externo)
```

---

## 10. Fases de implementación

### Fase 1 — MVP

- autenticación JWT con flujo de validación por código de entidad
- gestión de entidades (superadmin)
- importación de censo desde Excel
- gestión de usuarios y familiares con estados y validación administrativa
- eventos y menús
- inscripciones con cálculo de precios en backend
- listados básicos

### Fase 2 — Operativa completa

- registro de pagos manuales
- exportes Excel y PDF
- cierres automáticos de inscripción por fecha
- credencial visual de acceso
- pantalla de verificación en puerta
- reportes por evento para cocina y organización

### Fase 3 — Escala y automatización

- pasarela de pago (TPV / Stripe / Bizum)
- notificaciones push y email
- lista de espera automática
- control de aforo
- aplicación móvil nativa (Capacitor sobre la PWA)

---

## 11. Consideraciones técnicas

- toda la lógica de precios reside en el backend; el frontend solo muestra
- los snapshots de `InscripcionLinea` son inmutables una vez creados
- el bloqueo de edición/eliminación de líneas se decide por `InscripcionLinea.pagada` (granular por línea)
- la ventana de credencial se valida en el servidor con hora UTC
- el código de registro de cada entidad es un string aleatorio seguro de al menos 12 caracteres
- el matching del censo es case-insensitive y normaliza tildes
- los endpoints de admin están protegidos por voter de Symfony que verifica que el recurso pertenezca a la entidad del admin
- los endpoints de superadmin requieren `ROLE_SUPERADMIN` explícito
- el rol de admin de entidad es `ROLE_ADMIN_ENTIDAD`; el voter comprueba además que la entidad coincide
- `terminologiaSocio` y `terminologiaEvento` se devuelven en el endpoint de validación de código y en `/api/me` para que el frontend adapte sus textos
- la API es stateless; toda la sesión viaja en el JWT
- diseño mobile-first; la PWA debe funcionar con conexión intermitente
