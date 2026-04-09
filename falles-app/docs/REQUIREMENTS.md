# Requisitos funcionales y técnicos — Festapp

> Versión 1.3 — Franjas de comida por evento y compatibilidad de actividad por tipo de persona

---

## 1. Objetivo del sistema

Aplicación móvil multiplataforma (Android + iPhone vía PWA instalable) para gestionar comidas, almuerzos, meriendas y cenas de fallas, comparsas y colectivos festivos similares, incluyendo la semana fallera y eventos del resto del año.

El sistema debe permitir:

- gestión multi-colectivo desde un único superadministrador
- inscripción de socios y sus familiares a eventos
- selección de actividades por persona con precios diferenciados (interno/externo)
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

- crear, editar y desactivar colectivos en el sistema
- subir el Excel del censo de cada colectivo (nombre, apellidos, email, DNI, parentesco, tipo)
- generar y regenerar el código de registro único de cada colectivo
- asignar uno o varios admins a cada colectivo
- ver estadísticas globales por colectivo
- acceder a cualquier colectivo en modo lectura para soporte

No gestiona eventos, actividades ni inscripciones de ningún colectivo directamente.

### 2.2 Administrador de colectivo (`ROLE_ADMIN_ENTIDAD`)

Rol limitado a un único colectivo. Es el responsable operativo del día a día.

Puede:

- crear y editar eventos de su colectivo
- abrir y cerrar inscripciones por evento
- crear actividades y definir precios, franja de comida y compatibilidad por tipo de persona
- ver todas las inscripciones de su colectivo
- validar o rechazar usuarios y familiares pendientes
- dar de alta y de baja a usuarios en el censo interno
- registrar pagos manualmente
- exportar listados (Excel, PDF)
- subir o actualizar el censo si el superadmin le delega ese permiso (delegable por colectivo)
- activar el modo de verificación de acceso para eventos que lo requieran

### 2.3 Usuario (`ROLE_USER`)

Socio o miembro de una falla. Accede solo cuando ha sido validado.

Puede:

- iniciar sesión una vez validado
- editar solo email, teléfono, relaciones y forma de pago preferida
- cambiar su contraseña desde su perfil
- gestionar su unidad familiar (alta, edición, baja lógica)
- inscribirse a eventos y apuntar a sus familiares validados
- seleccionar actividad por persona en cada inscripción
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
| censado | boolean | activa/desactiva gestión censal del colectivo |
| createdAt | datetime | |
| updatedAt | datetime | |

Relaciones:

- 1:N con Usuario
- 1:N con Evento
- N:M con Usuario (admins del colectivo)

### 3.2 Cargo

Tabla de cargos internos de un colectivo (por ejemplo: presidente, tesorero, vocal).

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| entidad | FK Entidad | obligatorio |
| nombre | string | obligatorio |
| descripcion | text | nullable |
| multiplicador | decimal(8,2) | factor de ponderación, default 1.00 |

Relaciones:

- N:M con Usuario mediante tabla pivote `usuario_cargo`
- un usuario puede tener más de un cargo

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
| antiguedadReal | int | nullable |
| debeCambiarPassword | boolean | true cuando debe renovar contraseña temporal |
| passwordActualizadaAt | datetime | nullable |
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
| id | uuid | |
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
| id | uuid | |
| entidad | FK Entidad | |
| titulo | string | |
| slug | string | único por colectivo |
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

### 3.6 MenuEvento

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| evento | FK Evento | |
| nombre | string | |
| descripcion | text | nullable |
| tipoMenu | enum TipoMenuEnum | ADULTO / INFANTIL / ESPECIAL / LIBRE |
| franjaComida | enum FranjaComidaEnum | ALMUERZO / COMIDA / MERIENDA / CENA |
| compatibilidadPersona | enum CompatibilidadPersonaMenuEnum | ADULTO / INFANTIL / AMBOS |
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
| id | uuid | |
| codigo | string | único, generado automáticamente |
| **usuario_id + evento_id** | | **constraint única: un usuario solo puede tener una inscripción por evento** |
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

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| inscripcion | FK Inscripcion | |
| persona | FK PersonaFamiliar | |
| actividad | FK MenuEvento | canónico; alias legacy: `menu` |
| nombrePersonaSnapshot | string | histórico |
| tipoPersonaSnapshot | string | histórico |
| tipoRelacionEconomicaSnapshot | string | histórico |
| estadoValidacionSnapshot | string | histórico |
| nombreActividadSnapshot | string | histórico |
| franjaComidaSnapshot | string | histórico |
| esDePagoSnapshot | boolean | histórico |
| precioUnitario | decimal(8,2) | precio real aplicado, histórico |
| estadoLinea | enum EstadoLineaInscripcionEnum | |
| pagada | boolean | `true` si la línea quedó cubierta por un pago confirmado |
| observaciones | text | nullable |
| createdAt | datetime | |

### 3.9 Pago

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| inscripcion | FK Inscripcion | |
| fecha | datetime | |
| importe | decimal(8,2) | |
| metodoPago | enum MetodoPagoEnum | |
| referencia | string | nullable |
| estado | string | confirmado / anulado |
| observaciones | text | nullable |
| registradoPor | FK Usuario | admin que lo registró |
| createdAt | datetime | |

### 3.10 ColaCorreo

Cola persistente de correos transaccionales del sistema.

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| entidad | FK Entidad | nullable |
| usuario | FK Usuario | nullable |
| destinatario | string | email destino |
| asunto | string | |
| plantilla | string | plantilla Twig utilizada |
| contexto | json | variables para render Twig |
| estado | string | pendiente / enviado / error |
| intentos | int | reintentos |
| ultimoError | text | nullable |
| createdAt | datetime | |
| updatedAt | datetime | |
| enviadoAt | datetime | nullable |

---

## 4. Enumerados

```
TipoEntidadEnum:        FALLA, COMPARSA, PENYA, HERMANDAD, ASOCIACION, CLUB, OTRO
TipoEventoEnum:         ALMUERZO, COMIDA, MERIENDA, CENA, OTRO
TipoPersonaEnum:        ADULTO, INFANTIL
FranjaComidaEnum:       ALMUERZO, COMIDA, MERIENDA, CENA
CompatibilidadPersonaMenuEnum: ADULTO, INFANTIL, AMBOS
```
TipoRelacionEconomicaEnum: INTERNO, EXTERNO, INVITADO
EstadoValidacionEnum:   PENDIENTE_VALIDACION, VALIDADO, RECHAZADO, BLOQUEADO
TipoMenuEnum:           ADULTO, INFANTIL, ESPECIAL, LIBRE
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
- el alta de usuarios la realiza exclusivamente un administrador de colectivo, manualmente o por importación Excel
- los usuarios pendientes de validación, rechazados, bloqueados o dados de baja (`activo = false`) no pueden iniciar sesión
- si el colectivo del usuario está inactivo tampoco puede iniciar sesión
- cuando `debeCambiarPassword = true`, el usuario debe cambiar su contraseña en el primer login antes de usar el resto de endpoints
- el código de registro puede regenerarse; el código antiguo queda inválido inmediatamente

### 5.1.1 Alta de usuarios y contraseña inicial

- al crear un usuario manualmente o por Excel se genera/asigna contraseña temporal
- en ambos casos se marca `debeCambiarPassword = true`
- se encola correo de bienvenida con usuario, contraseña temporal y URI de acceso a la app

### 5.1.2 Cambio de contraseña

- el usuario puede cambiar contraseña desde su perfil
- el backend valida contraseña actual salvo en flujo forzado de primer acceso
- al cambiarla correctamente se actualiza `passwordActualizadaAt` y `debeCambiarPassword = false`

### 5.2 Precios

- el backend decide siempre el precio; nunca se confía en el frontend
- si `esDePago = false`, el precio aplicado es 0 independientemente del resto
- si `esDePago = true`, el precio depende del tipo de actividad y la condición económica validada de la persona
- si la persona es INTERNO: se aplica `precioAdultoInterno` o `precioInfantil` según la actividad elegida
- si la persona es EXTERNO o INVITADO: se aplica `precioAdultoExterno` o `precioInfantil`
- si la actividad elegida es de tipo INFANTIL: se aplica siempre `precioInfantil`, sea adulto o infantil quien lo elija
- si la actividad elegida es de tipo ADULTO: se aplica precio adulto aunque quien se inscriba sea infantil
- si no existe precio específico, se cae a `precioBase`
- una persona con `estadoValidacion` distinto de VALIDADO no puede beneficiarse de precios internos
- si el usuario está dado de baja del censo interno, en nuevas inscripciones se le aplica precio externo

### 5.3 Inscripciones

- no se puede inscribir fuera del rango de fechas de inscripción
- si el evento está cerrado no admite nuevas inscripciones
- **un usuario no puede inscribirse dos veces al mismo evento** (validación a nivel de API y base de datos con constraint única en `usuario_id` + `evento_id`)
- la actividad elegida debe pertenecer al evento y estar activa
- cada actividad pertenece a una franja de comida (almuerzo/comida/merienda/cena)
- una persona puede tener varias líneas en el mismo evento, pero **solo una por franja**
- no se puede seleccionar una actividad incompatible con el tipo de persona (adulto/infantil)
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

### 5.6 Notificaciones por correo (encoladas)

- todos los correos se registran en `ColaCorreo` antes de su procesamiento/envío
- se usa plantillado Twig para construir el contenido
- se encola correo cuando:
  - se da de alta un usuario (manual o Excel)
  - se crea o actualiza un evento
  - un usuario se apunta a un evento
  - un usuario borra/cancela su inscripción o selección
  - un usuario actualiza su inscripción/selección
  - se registra un pago de inscripción

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
POST   /api/me/cambiar-password               Cambia contraseña del usuario autenticado
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
GET    /api/eventos                           Lista eventos visibles/publicados del colectivo
GET    /api/eventos/{id}                      Detalle de evento
GET    /api/actividad_eventos?evento={id}     Actividades de un evento
GET    /api/menu_eventos?evento={id}          Alias legacy compatible
GET    /api/eventos/{id}/mi-credencial        Credencial visual (si procede)
```

### Inscripciones (usuario)

```
GET    /api/inscripciones/mias               Mis inscripciones
POST   /api/eventos/{id}/inscribirme          Crear inscripción con líneas
GET    /api/inscripciones/{id}                Detalle de inscripción
POST   /api/inscripciones/{id}/cancelar       Cancelar inscripción
```

### Admin de colectivo — usuarios

```
GET    /api/admin/usuarios                    Lista usuarios del colectivo
POST   /api/admin/usuarios                    Alta manual de usuario
POST   /api/admin/usuarios/importar-excel     Alta/actualización de usuarios por Excel
GET    /api/admin/usuarios-pendientes         Usuarios pendientes de validación
POST   /api/admin/usuarios/{id}/validar       Valida usuario manualmente
POST   /api/admin/usuarios/{id}/rechazar      Rechaza usuario
POST   /api/admin/usuarios/{id}/alta-censo    Alta en censo interno
POST   /api/admin/usuarios/{id}/baja-censo    Baja en censo interno
GET    /api/admin/persona_familiares-pendientes  Familiares pendientes
POST   /api/admin/persona_familiares/{id}/validar
POST   /api/admin/persona_familiares/{id}/rechazar
```

### Admin de colectivo — eventos y actividades

```
GET    /api/admin/eventos                     Todos los eventos del colectivo
POST   /api/eventos                           Crear evento
PATCH  /api/eventos/{id}                      Editar evento
POST   /api/eventos/{id}/publicar             Publica evento
POST   /api/eventos/{id}/cerrar               Cierra inscripciones
POST   /api/actividad_eventos                 Crear actividad
PATCH  /api/actividad_eventos/{id}            Editar actividad
POST   /api/menu_eventos                      Alias legacy compatible
PATCH  /api/menu_eventos/{id}                 Alias legacy compatible
```

### Admin de colectivo — inscripciones y pagos

```
GET    /api/admin/inscripciones               Lista inscripciones del evento o colectivo
GET    /api/admin/inscripciones/{id}          Detalle con líneas
POST   /api/inscripciones/{id}/registrar_pago Registrar pago manual
GET    /api/admin/pagos                        Lista de pagos
```

Notas de comportamiento para `registrar_pago`:

- liquida el pendiente completo de la inscripción (no parcial)
- recalcula `estadoPago` en base a `importeTotal` e `importePagado`
- deja trazabilidad por línea mediante el flag `pagada`

### Admin de colectivo — reportes

```
GET    /api/eventos/{id}/reporte-resumen      Resumen por actividad
GET    /api/eventos/{id}/reporte-personas     Listado nominal
GET    /api/eventos/{id}/reporte-menu         Agrupado por actividad para cocina
GET    /api/eventos/{id}/reporte-pagos        Estado de pagos
```

### Superadmin — fallas

```
GET    /api/superadmin/entidades                 Lista todos los colectivos
POST   /api/superadmin/entidades                 Crear colectivo
PATCH  /api/superadmin/entidades/{id}            Editar colectivo
POST   /api/superadmin/entidades/{id}/codigo-registro/regenerar
POST   /api/superadmin/entidades/{id}/admins     Asigna admin a colectivo
```

### Sistema — cola de correo

```
CLI    php bin/console app:mail-queue:process [--limit=50]  Procesa correos pendientes en cola
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
      "actividad": "/api/menu_eventos/10",
      "observaciones": "Sin cebolla"
    },
    {
      "persona": "/api/persona_familiares/1",
      "actividad": "/api/menu_eventos/12",
      "observaciones": "Solo para cena"
    },
    {
      "persona": "/api/persona_familiares/2",
      "actividad": "/api/menu_eventos/11",
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
      "franja": "comida",
      "precioUnitario": 15.00,
      "esDePago": true
    },
    {
      "persona": "Pablo García",
      "actividad": "Actividad infantil",
      "franja": "comida",
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
| Splash / acceso | Logo del colectivo, login o registro |
| Registro paso 1 | Introducir código de colectivo |
| Registro paso 2 | Datos personales, contraseña |
| Pendiente de validación | Pantalla informativa si no hay coincidencia en censo |
| Login | Email y contraseña |
| Inicio | Calendario mensual con eventos marcados |
| Listado de eventos | Próximos, con inscripción abierta, mis reservas |
| Detalle de evento | Info, actividades, plazo, botón inscribirse |
| Selección de asistentes | Selector por persona y por franja (almuerzo/comida/merienda/cena), con bloqueo de actividades incompatibles y bloqueo por línea pagada |
| Resumen de inscripción | Asistentes, actividades, subtotal, total, estado pago |
| Mis inscripciones | Lista con estado |
| Mis pagos | Historial |
| Mi familia | Gestión de PersonaFamiliar |
| Perfil | Datos personales, cambiar contraseña |
| Credencial de acceso | Pase visual con token temporal |

### 8.2 Admin de colectivo

| Pantalla | Descripción |
|---|---|
| Dashboard | KPIs rápidos del colectivo |
| Calendario de eventos | Vista mes/semana/día con filtros |
| Crear / editar evento | Formulario completo |
| Gestionar actividades | CRUD de actividades de un evento |
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
| Lista de colectivos | Estado, estadísticas básicas |
| Crear / editar colectivo | Formulario con logo y configuración |
| Importar censo | Upload de Excel con previsualización |
| Historial de importaciones | Fecha, usuario, entradas procesadas |
| Código de registro | Ver, copiar, regenerar |
| Asignación de admins | Vincular usuarios como admin de colectivo |

---

## 9. Casos de cálculo

### Todo gratuito

```
Ana → actividad infantil gratuita = 0 €
Pablo → actividad infantil gratuita = 0 €
→ importeTotal = 0 | estadoPago = NO_REQUIERE_PAGO | confirmación automática
```

### Todo de pago

```
Juan → actividad adulto interno = 15 €
María → actividad adulto interno = 15 €
→ importeTotal = 30 € | estadoPago = PENDIENTE
```

### Mezcla gratuito + pago

```
Juan → actividad adulto = 15 €
Ana → actividad infantil gratuita = 0 €
→ importeTotal = 15 € | estadoPago = PENDIENTE
```

### Externo con sobreprecio

```
precioAdultoInterno = 12 €
precioAdultoExterno = 25 €
→ INTERNO paga 12 € | EXTERNO paga 25 €
```

### Adulto elige actividad infantil

```
persona: adulto | actividad: infantil | precioInfantil = 8 €
→ paga 8 €
```

### Infantil elige actividad adulto

```
persona: infantil | actividad: adulto | precioAdultoInterno = 12 €
→ paga 12 € (interno) o 25 € (externo)
```

---

## 10. Fases de implementación

### Fase 1 — MVP

- autenticación JWT con flujo de validación por código de colectivo
- gestión de colectivos (superadmin)
- importación de censo desde Excel
- gestión de usuarios y familiares con estados y validación administrativa
- eventos y actividades
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
- el código de registro de cada colectivo es un string aleatorio seguro de al menos 12 caracteres
- el matching del censo es case-insensitive y normaliza tildes
- los endpoints de admin están protegidos por voter de Symfony que verifica que el recurso pertenezca al colectivo del admin
- los endpoints de superadmin requieren `ROLE_SUPERADMIN` explícito
- el rol de admin de colectivo es `ROLE_ADMIN_ENTIDAD`; el voter comprueba además que el colectivo coincide
- `terminologiaSocio` y `terminologiaEvento` se devuelven en el endpoint de validación de código y en `/api/me` para que el frontend adapte sus textos
- la API es stateless; toda la sesión viaja en el JWT
- diseño mobile-first; la PWA debe funcionar con conexión intermitente
