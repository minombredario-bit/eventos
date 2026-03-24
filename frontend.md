# Skill: Frontend — Angular 18 + PWA

Lee este archivo completo antes de generar cualquier código de frontend.

---

## Stack y versiones

| Paquete | Versión |
|---|---|
| Angular | 18.x |
| Angular Material | 18.x |
| @angular/pwa | 18.x |
| RxJS | 7.x |
| TypeScript | 5.x |
| @angular/service-worker | 18.x |

---

## Estructura de directorios

```
frontend/src/app/
├── core/
│   ├── auth/                   # AuthService, guards, interceptors
│   ├── api/                    # Servicios HTTP base
│   ├── models/                 # Interfaces TypeScript
│   └── utils/                  # Helpers (normalización de texto, etc.)
│
├── shared/
│   ├── components/             # Componentes reutilizables
│   │   ├── evento-card/
│   │   ├── persona-chip/
│   │   └── estado-badge/
│   └── pipes/                  # Pipes personalizados
│
├── auth/                       # Login, registro, recuperación
│   ├── login/
│   ├── registro/
│   │   ├── paso-codigo/        # Paso 1: introducir código de entidad
│   │   └── paso-datos/         # Paso 2: datos personales
│   └── pendiente-validacion/
│
├── inicio/                     # Home con calendario
│
├── eventos/
│   ├── listado/
│   ├── detalle/
│   ├── inscripcion/
│   │   ├── selector-personas/
│   │   └── resumen/
│   └── credencial/             # Pase visual de acceso
│
├── familia/                    # Gestión de PersonaFamiliar
│
├── inscripciones/              # Mis inscripciones
│
├── perfil/                     # Datos personales y contraseña
│
└── admin/
    ├── dashboard/
    ├── calendario-eventos/
    ├── eventos/
    │   ├── form/
    │   └── menus/
    ├── inscripciones/
    ├── pagos/
    ├── usuarios/
    │   └── pendientes/
    ├── censo/
    ├── reportes/
    └── verificacion-acceso/

```

---

## Modelos TypeScript

Crear interfaces en `src/app/core/models/`. Un archivo por entidad principal.

```typescript
// src/app/core/models/usuario.model.ts

export type EstadoValidacion = 'pendiente_validacion' | 'validado' | 'rechazado' | 'bloqueado';
export type TipoUsuarioEconomico = 'interno' | 'externo' | 'invitado';
export type CensadoVia = 'excel' | 'manual' | 'invitacion';

export interface Usuario {
  id: string;
  nombre: string;
  apellidos: string;
  email: string;
  telefono?: string;
  roles: string[];
  activo: boolean;
  tipoUsuarioEconomico: TipoUsuarioEconomico;
  estadoValidacion: EstadoValidacion;
  puedeAcceder: boolean;
  esCensadoInterno: boolean;
  censadoVia?: CensadoVia;
  entidad: string; // IRI
  fechaSolicitudAlta?: string;
  fechaAltaCenso?: string;
  fechaBajaCenso?: string;
}
```

```typescript
// src/app/core/models/evento.model.ts

export type TipoEvento = 'almuerzo' | 'comida' | 'merienda' | 'cena' | 'otro';
export type EstadoEvento = 'borrador' | 'publicado' | 'cerrado' | 'finalizado' | 'cancelado';

export interface Evento {
  id: number;
  titulo: string;
  slug: string;
  descripcion?: string;
  tipoEvento: TipoEvento;
  fechaEvento: string;       // ISO date
  horaInicio?: string;       // HH:mm
  horaFin?: string;
  lugar?: string;
  aforo?: number;
  fechaInicioInscripcion: string;
  fechaFinInscripcion: string;
  visible: boolean;
  publicado: boolean;
  admitePago: boolean;
  estado: EstadoEvento;
  requiereVerificacionAcceso: boolean;
  menus?: MenuEvento[];
}

export interface MenuEvento {
  id: number;
  nombre: string;
  descripcion?: string;
  tipoMenu: 'adulto' | 'infantil' | 'especial' | 'libre';
  esDePago: boolean;
  precioBase: number;
  precioAdultoInterno?: number;
  precioAdultoExterno?: number;
  precioInfantil?: number;
  unidadesMaximas?: number;
  ordenVisualizacion: number;
  activo: boolean;
}
```

```typescript
// src/app/core/models/inscripcion.model.ts

export type EstadoInscripcion = 'pendiente' | 'confirmada' | 'cancelada' | 'lista_espera';
export type EstadoPago = 'no_requiere_pago' | 'pendiente' | 'parcial' | 'pagado' | 'devuelto' | 'cancelado';

export interface InscripcionLinea {
  id: number;
  nombrePersonaSnapshot: string;
  tipoPersonaSnapshot: string;
  nombreMenuSnapshot: string;
  precioUnitario: number;
  esDePagoSnapshot: boolean;
  estadoLinea: string;
  observaciones?: string;
}

export interface Inscripcion {
  id: number;
  codigo: string;
  evento: Evento | string;
  estadoInscripcion: EstadoInscripcion;
  estadoPago: EstadoPago;
  importeTotal: number;
  importePagado: number;
  metodoPago?: string;
  referenciaPago?: string;
  fechaPago?: string;
  lineas: InscripcionLinea[];
  createdAt: string;
}

export interface InscripcionRequest {
  personas: {
    persona: string;    // IRI: /api/persona_familiares/{id}
    menu: string;       // IRI: /api/menu_eventos/{id}
    observaciones?: string;
  }[];
}
```

---

## Servicios HTTP

Crear en `src/app/core/api/`. Usar `HttpClient` con `inject()`.

```typescript
// src/app/core/api/evento.service.ts

import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Evento } from '../models/evento.model';
import { environment } from '../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class EventoService {
  private http = inject(HttpClient);
  private base = environment.apiUrl;

  getEventos(params?: { estado?: string; tipo?: string }): Observable<Evento[]> {
    let httpParams = new HttpParams();
    if (params?.estado) httpParams = httpParams.set('estado', params.estado);
    if (params?.tipo) httpParams = httpParams.set('tipoEvento', params.tipo);
    return this.http.get<{ 'hydra:member': Evento[] }>(`${this.base}/api/eventos`, { params: httpParams })
      .pipe(map(r => r['hydra:member']));
  }

  getEvento(id: number): Observable<Evento> {
    return this.http.get<Evento>(`${this.base}/api/eventos/${id}`);
  }
}
```

### Interceptor JWT

```typescript
// src/app/core/auth/auth.interceptor.ts

import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from './auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const token = auth.getToken();

  if (token) {
    req = req.clone({
      setHeaders: { Authorization: `Bearer ${token}` }
    });
  }

  return next(req);
};
```

---

## Guards

```typescript
// src/app/core/auth/auth.guard.ts

import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

export const authGuard: CanActivateFn = (route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (auth.isAuthenticated()) {
    return true;
  }
  return router.createUrlTree(['/auth/login']);
};

export const adminGuard: CanActivateFn = (route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (auth.hasRole('ROLE_ADMIN_ENTIDAD') || auth.hasRole('ROLE_SUPERADMIN')) {
    return true;
  }
  return router.createUrlTree(['/inicio']);
};
```

---

## Pantalla principal — Calendario de eventos

El componente de inicio debe mostrar un calendario mensual. Usar `@angular/material` `MatCalendar` o una librería como `ngx-mat-calendar`. La lógica clave:

```typescript
// src/app/inicio/inicio.component.ts

export class InicioComponent implements OnInit {
  eventoService = inject(EventoService);
  eventosPorFecha = new Map<string, Evento[]>(); // clave: 'YYYY-MM-DD'
  fechaSeleccionada = new Date();
  eventosDelDia: Evento[] = [];

  ngOnInit() {
    this.eventoService.getEventos().subscribe(eventos => {
      eventos.forEach(e => {
        const key = e.fechaEvento.substring(0, 10);
        if (!this.eventosPorFecha.has(key)) {
          this.eventosPorFecha.set(key, []);
        }
        this.eventosPorFecha.get(key)!.push(e);
      });
      this.actualizarEventosDelDia();
    });
  }

  seleccionarFecha(fecha: Date) {
    this.fechaSeleccionada = fecha;
    this.actualizarEventosDelDia();
  }

  private actualizarEventosDelDia() {
    const key = this.fechaSeleccionada.toISOString().substring(0, 10);
    this.eventosDelDia = this.eventosPorFecha.get(key) ?? [];
  }

  // Para marcar días con eventos en el calendario
  dateClass = (date: Date): string => {
    const key = date.toISOString().substring(0, 10);
    return this.eventosPorFecha.has(key) ? 'tiene-evento' : '';
  };
}
```

---

## Flujo de inscripción

El flujo de inscripción es el más complejo del frontend. Se compone de tres pasos en un stepper:

### Paso 1: Detalle del evento

- mostrar info del evento
- mostrar menús disponibles
- botón "Inscribirse" (solo si plazo abierto y usuario validado)

### Paso 2: Selector de asistentes

```typescript
// src/app/eventos/inscripcion/selector-personas/selector-personas.component.ts

export interface LineaSeleccion {
  persona: PersonaFamiliar;
  asiste: boolean;
  menuId: number | null;
  observaciones: string;
}

export class SelectorPersonasComponent {
  // Estado local por persona
  lineas: LineaSeleccion[] = [];

  // Calcular precio estimado (solo visual, el real viene del backend)
  precioEstimado(linea: LineaSeleccion): number {
    if (!linea.asiste || !linea.menuId) return 0;
    const menu = this.menus.find(m => m.id === linea.menuId);
    if (!menu || !menu.esDePago) return 0;
    // Precio orientativo; el backend recalcula
    return menu.precioBase;
  }
}
```

### Paso 3: Resumen y confirmación

- mostrar asistentes, menú por persona, precio estimado
- botón "Confirmar inscripción" que llama al endpoint
- gestionar estados: loading, error, éxito
- tras éxito, redirigir a la pantalla de la inscripción creada

---

## Credencial visual de acceso

```typescript
// src/app/eventos/credencial/credencial.component.ts

export class CredencialComponent implements OnInit, OnDestroy {
  credencial: CredencialData | null = null;
  enVentana = false;
  private timer?: ReturnType<typeof setInterval>;

  ngOnInit() {
    this.cargarCredencial();
    // Verificar estado cada 60 segundos
    this.timer = setInterval(() => this.cargarCredencial(), 60_000);
  }

  ngOnDestroy() {
    clearInterval(this.timer);
  }

  private cargarCredencial() {
    this.inscripcionService.getCredencial(this.inscripcionId).subscribe({
      next: (data) => {
        this.credencial = data;
        this.enVentana = data.enVentanaActiva;
      },
      error: () => {
        this.enVentana = false;
      }
    });
  }
}
```

La credencial debe mostrar:

- nombre del evento y fecha (grande y legible)
- nombre del titular
- personas incluidas con sus menús
- estado de pago si es relevante
- token visual temporal (cambia cada cierto tiempo para evitar capturas reutilizadas)
- color o imagen identificativa del evento/tipo

---

## Pantalla de registro en dos pasos

### Paso 1: Código de entidad

```typescript
// src/app/auth/registro/paso-codigo/paso-codigo.component.ts

export class PasoCodigoComponent {
  codigo = '';
  cargando = false;
  error = '';
  entidadInfo: { nombre: string; logo?: string; tipoEntidad: string; terminologiaSocio: string } | null = null;

  validarCodigo() {
    this.cargando = true;
    this.authService.validarCodigoEntidad(this.codigo).subscribe({
      next: (entidad) => {
        this.entidadInfo = entidad;
        // Navegar al paso 2 pasando el código
        this.router.navigate(['/auth/registro/datos'], {
          queryParams: { codigo: this.codigo }
        });
      },
      error: () => {
        this.error = 'Código de entidad no válido o inactivo';
        this.cargando = false;
      }
    });
  }
}
```

### Paso 2: Datos personales

- formulario reactivo con validaciones
- llamar a `POST /api/registro/solicitud` con el código incluido
- según la respuesta (`validadoAutomaticamente: true/false`), redirigir a login o a pantalla de "pendiente de validación"

---

## UX móvil — principios

- **mobile-first**: todos los layouts deben funcionar en pantallas de 360px de ancho
- **touch targets**: botones y elementos interactivos mínimo 44x44px
- **bottom navigation**: para usuario autenticado (Inicio, Eventos, Familia, Inscripciones, Perfil)
- **top app bar**: con título y acciones contextuales
- **skeleton loaders**: mostrar esqueletos mientras carga, nunca spinners globales bloqueantes
- **pull to refresh**: en listados de eventos e inscripciones
- **offline**: la PWA debe poder mostrar el calendario y la credencial guardados en cache
- **instalable**: manifest configurado para que el navegador ofrezca instalar la PWA

---

## Configuración PWA

```json
// ngsw-config.json (resumen)
{
  "assetGroups": [
    {
      "name": "app",
      "installMode": "prefetch",
      "resources": {
        "files": ["/favicon.ico", "/index.html", "/*.css", "/*.js"]
      }
    }
  ],
  "dataGroups": [
    {
      "name": "eventos",
      "urls": ["/api/eventos"],
      "cacheConfig": {
        "maxSize": 100,
        "maxAge": "1h",
        "strategy": "freshness"
      }
    },
    {
      "name": "credencial",
      "urls": ["/api/inscripciones/*/mi-credencial"],
      "cacheConfig": {
        "maxSize": 20,
        "maxAge": "5m",
        "strategy": "freshness"
      }
    }
  ]
}
```

---

## Colores por tipo de evento

Definir en el tema global:

```scss
// src/styles/evento-tipos.scss

.tipo-almuerzo   { --evento-color: #F59E0B; } // amber
.tipo-comida     { --evento-color: #EF4444; } // red
.tipo-merienda   { --evento-color: #8B5CF6; } // purple
.tipo-cena       { --evento-color: #3B82F6; } // blue
.tipo-otro       { --evento-color: #6B7280; } // gray
```

Usar en `EventoCardComponent` y en el calendario para marcar días.

---

## Estado de pago — badges

```typescript
// src/app/shared/pipes/estado-pago.pipe.ts

@Pipe({ name: 'estadoPago', standalone: true })
export class EstadoPagoPipe implements PipeTransform {
  transform(estado: EstadoPago): { label: string; color: string } {
    const map: Record<EstadoPago, { label: string; color: string }> = {
      no_requiere_pago: { label: 'Sin coste', color: 'success' },
      pendiente:        { label: 'Pendiente de pago', color: 'warn' },
      parcial:          { label: 'Pago parcial', color: 'accent' },
      pagado:           { label: 'Pagado', color: 'success' },
      devuelto:         { label: 'Devuelto', color: 'default' },
      cancelado:        { label: 'Cancelado', color: 'default' },
    };
    return map[estado] ?? { label: estado, color: 'default' };
  }
}
```

---

## Checklist antes de hacer PR

- [ ] Ninguna lógica de precios en el frontend (solo mostrar lo que devuelve el backend)
- [ ] Todos los formularios usan `ReactiveFormsModule` con validaciones explícitas
- [ ] Los guards protegen correctamente las rutas de admin y superadmin
- [ ] La credencial consulta la hora al servidor, no a `new Date()`
- [ ] El interceptor adjunta el JWT en todas las llamadas autenticadas
- [ ] La PWA tiene las rutas de API en la estrategia de cache correcta
- [ ] Los lazy modules están configurados para reducir el bundle inicial
- [ ] Los componentes standalone usan `inject()` en lugar de constructor injection
