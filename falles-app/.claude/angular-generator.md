# Skill: Angular 18 Generator — FestApp

Lee este archivo completo antes de generar cualquier pieza de código Angular.

---

## ⚠️ Restricciones globales

- Genera **solo** lo que se pide. No añadas archivos extra "por si acaso".
- **No modifiques** archivos existentes salvo que la tarea lo requiera explícitamente.
- **No cambies** nombres de clases, métodos ni propiedades ya definidas.
- Si necesitas tocar un archivo no mencionado, **para y pregunta** primero.
- Declara siempre qué archivos vas a crear/modificar antes de escribir código.

---

## Stack y versiones

| Paquete | Versión |
|---|---|
| Angular | 18.x |
| Angular Material | 18.x |
| Tailwind CSS | 3.x |
| ngx-translate | última compatible con Angular 18 |
| RxJS | 7.x |
| TypeScript | 5.x |

---

## Reglas base — aplicar siempre

- **Standalone components** — siempre `standalone: true`, nunca NgModules nuevos
- **`inject()`** — nunca constructor injection en código nuevo
- **Estado**: usa signals (`signal`, `computed`, `effect`) para estado local simple; usa `BehaviorSubject` / `Observable` cuando el estado se comparte entre componentes o viene de HTTP
- **Typed forms** — siempre `FormGroup<{...}>` y `FormControl<Tipo>` con tipo explícito
- **Functional guards e interceptors** — nunca clases que implementen interfaces de guard
- **Imports explícitos** — declara en el array `imports` del componente solo lo que usa ese componente
- **API Platform responses** — las colecciones devuelven `hydra:member`, no `data` ni `items`
- **Precios** — nunca calcular precios en el frontend; solo mostrar lo que devuelve el backend
- **Hora del servidor** — para credenciales y ventanas temporales, usar siempre la hora que devuelve el backend

---

## 1. COMPONENTES

### Plantilla base — componente de presentación

```typescript
// src/app/{feature}/{nombre}/{nombre}.component.ts

import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-{nombre}',
  standalone: true,
  imports: [CommonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: ``,
})
export class {Nombre}Component {
  // Inputs con signal-based API (Angular 18)
  dato = input.required<TipoDato>();
  opcional = input<string>('valor por defecto');

  // Outputs
  accion = output<TipoEvento>();
}
```

### Plantilla base — componente de contenedor (con lógica y HTTP)

```typescript
// src/app/{feature}/{nombre}/{nombre}.component.ts

import { ChangeDetectionStrategy, Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { {Entidad}Service } from '../../core/api/{entidad}.service';
import { {Entidad} } from '../../core/models/{entidad}.model';

@Component({
  selector: 'app-{nombre}',
  standalone: true,
  imports: [CommonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: ``,
})
export class {Nombre}Component implements OnInit {
  private {entidad}Service = inject({Entidad}Service);

  // Estado con signals
  items = signal<{Entidad}[]>([]);
  cargando = signal(false);
  error = signal<string | null>(null);

  // Derivado
  hayItems = computed(() => this.items().length > 0);

  ngOnInit() {
    this.cargar();
  }

  private cargar() {
    this.cargando.set(true);
    this.error.set(null);
    this.{entidad}Service.get{Entidades}().subscribe({
      next: (data) => {
        this.items.set(data);
        this.cargando.set(false);
      },
      error: (err) => {
        this.error.set('Error al cargar los datos');
        this.cargando.set(false);
      },
    });
  }
}
```

### Plantilla base — componente con formulario reactivo

```typescript
// src/app/{feature}/{nombre}/{nombre}.component.ts

import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormGroup, FormControl, Validators } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';

interface {Nombre}Form {
  campo: FormControl<string>;
}

@Component({
  selector: 'app-{nombre}',
  standalone: true,
  imports: [ReactiveFormsModule, MatFormFieldModule, MatInputModule, MatButtonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <form [formGroup]="form" (ngSubmit)="onSubmit()">
      <mat-form-field appearance="outline">
        <mat-label>Campo</mat-label>
        <input matInput formControlName="campo" />
        @if (form.controls.campo.hasError('required')) {
          <mat-error>Campo obligatorio</mat-error>
        }
      </mat-form-field>

      <button mat-raised-button color="primary" type="submit" [disabled]="form.invalid || enviando()">
        {{ enviando() ? 'Enviando…' : 'Guardar' }}
      </button>
    </form>
  `,
})
export class {Nombre}Component {
  enviando = signal(false);

  form = new FormGroup<{Nombre}Form>({
    campo: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
  });

  onSubmit() {
    if (this.form.invalid) return;
    this.enviando.set(true);
    // llamada al servicio
  }
}
```

### Reglas de template

- Usa `@if`, `@for`, `@switch` (sintaxis Angular 17+ con bloques de control), nunca `*ngIf` ni `*ngFor`
- `@for` siempre con `track`: `@for (item of items(); track item.id)`
- Clases de color de evento: `tipo-almuerzo`, `tipo-comida`, `tipo-merienda`, `tipo-cena`, `tipo-otro`
- Skeleton loaders mientras `cargando()` es true; nunca spinners globales bloqueantes
- Touch targets mínimo 44×44px en elementos interactivos
- Mobile-first: diseñar para 360px de ancho, escalar hacia arriba

### Convención de archivos de componente

```
{nombre}/
├── {nombre}.component.ts        ← lógica + template inline (componentes simples)
├── {nombre}.component.html      ← template separado (componentes con template > 30 líneas)
├── {nombre}.component.scss      ← estilos del componente
└── {nombre}.component.spec.ts   ← tests unitarios
```

---

## 2. SERVICIOS HTTP

```typescript
// src/app/core/api/{entidad}.service.ts

import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { {Entidad} } from '../models/{entidad}.model';
import { environment } from '../../../environments/environment';

// Wrapper genérico para respuestas de colección de API Platform (JSON-LD)
interface HydraCollection<T> {
  'hydra:member': T[];
  'hydra:totalItems': number;
}

@Injectable({ providedIn: 'root' })
export class {Entidad}Service {
  private http = inject(HttpClient);
  private base = `${environment.apiUrl}/api/{entidades}`;

  // Colección — extrae hydra:member
  getAll(params?: Record<string, string>): Observable<{Entidad}[]> {
    let httpParams = new HttpParams();
    if (params) {
      Object.entries(params).forEach(([k, v]) => httpParams = httpParams.set(k, v));
    }
    return this.http
      .get<HydraCollection<{Entidad}>>(this.base, { params: httpParams })
      .pipe(map(r => r['hydra:member']));
  }

  // Elemento único
  getById(id: number | string): Observable<{Entidad}> {
    return this.http.get<{Entidad}>(`${this.base}/${id}`);
  }

  // Crear
  create(data: Partial<{Entidad}>): Observable<{Entidad}> {
    return this.http.post<{Entidad}>(this.base, data);
  }

  // Actualizar parcialmente (PATCH)
  update(id: number | string, data: Partial<{Entidad}>): Observable<{Entidad}> {
    return this.http.patch<{Entidad}>(`${this.base}/${id}`, data, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
  }

  // Eliminar
  delete(id: number | string): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}
```

> **Importante con PATCH en API Platform**: usar siempre `Content-Type: application/merge-patch+json`.

### Servicio de estado compartido (BehaviorSubject)

Usar cuando el estado se comparte entre varios componentes (ej: usuario autenticado, falla activa).

```typescript
// src/app/core/auth/auth.service.ts

import { inject, Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, tap } from 'rxjs';
import { Router } from '@angular/router';
import { Usuario } from '../models/usuario.model';
import { environment } from '../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http   = inject(HttpClient);
  private router = inject(Router);

  private readonly TOKEN_KEY = 'jwt_token';

  // Signal para consumo en templates
  usuarioActual = signal<Usuario | null>(null);

  // Observable para componentes que necesitan reaccionar a cambios
  private usuarioSubject = new BehaviorSubject<Usuario | null>(null);
  usuario$ = this.usuarioSubject.asObservable();

  login(email: string, password: string): Observable<{ token: string }> {
    return this.http.post<{ token: string }>(`${environment.apiUrl}/api/login_check`, { email, password }).pipe(
      tap(({ token }) => {
        localStorage.setItem(this.TOKEN_KEY, token);
        this.cargarPerfil();
      })
    );
  }

  logout() {
    localStorage.removeItem(this.TOKEN_KEY);
    this.usuarioActual.set(null);
    this.usuarioSubject.next(null);
    this.router.navigate(['/auth/login']);
  }

  getToken(): string | null {
    return localStorage.getItem(this.TOKEN_KEY);
  }

  isAuthenticated(): boolean {
    return !!this.getToken();
  }

  hasRole(role: string): boolean {
    return this.usuarioActual()?.roles.includes(role) ?? false;
  }

  private cargarPerfil() {
    this.http.get<Usuario>(`${environment.apiUrl}/api/me`).subscribe(usuario => {
      this.usuarioActual.set(usuario);
      this.usuarioSubject.next(usuario);
    });
  }
}
```

---

## 3. INTERFACES Y MODELOS

Un archivo por entidad en `src/app/core/models/`. Convenciones:

- Tipos union string para enums: `type EstadoEvento = 'borrador' | 'publicado' | ...`
- IRIs de API Platform como `string`: `falla: string // IRI: /api/fallas/{id}`
- Fechas como `string` en ISO 8601: `fechaEvento: string`
- Campos opcionales con `?`
- Separar interfaces de request/response cuando difieren

```typescript
// src/app/core/models/{entidad}.model.ts

// --- Tipos enumerados ---
export type EstadoXxx = 'valor_a' | 'valor_b' | 'valor_c';

// --- Entidad principal (respuesta del backend) ---
export interface {Entidad} {
  id: number | string;
  // campos...
  relacion: string;          // IRI cuando es referencia a otra entidad
  relaciones: string[];      // array de IRIs
  createdAt?: string;        // ISO 8601
  updatedAt?: string;
}

// --- DTO para crear (POST) ---
export interface Create{Entidad}Dto {
  // solo los campos que se envían al crear
}

// --- DTO para actualizar (PATCH) ---
export type Update{Entidad}Dto = Partial<Create{Entidad}Dto>;
```

### Helper: extraer ID desde IRI

```typescript
// src/app/core/utils/iri.utils.ts

/**
 * Extrae el ID numérico o string del final de un IRI de API Platform.
 * Ejemplo: '/api/eventos/42' → '42'
 */
export function idDesdeIri(iri: string): string {
  return iri.split('/').pop() ?? '';
}

/**
 * Construye un IRI a partir de la colección y el ID.
 * Ejemplo: iriDesde('eventos', 42) → '/api/eventos/42'
 */
export function iriDesde(coleccion: string, id: number | string): string {
  return `/api/${coleccion}/${id}`;
}
```

---

## 4. GUARDS

```typescript
// src/app/core/auth/{nombre}.guard.ts

import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

// Guard de autenticación
export const authGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);
  return auth.isAuthenticated() ? true : router.createUrlTree(['/auth/login']);
};

// Guard de rol
export const rolGuard = (rol: string): CanActivateFn => () => {
  const auth   = inject(AuthService);
  const router = inject(Router);
  return auth.hasRole(rol) ? true : router.createUrlTree(['/inicio']);
};

// Guard de usuario validado
export const validadoGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);
  const usuario = auth.usuarioActual();
  if (!usuario) return router.createUrlTree(['/auth/login']);
  if (usuario.estadoValidacion !== 'validado') return router.createUrlTree(['/auth/pendiente-validacion']);
  return true;
};
```

---

## 5. INTERCEPTORS

```typescript
// src/app/core/auth/auth.interceptor.ts — adjunta JWT

import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from './auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = inject(AuthService).getToken();
  if (token) {
    req = req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
  }
  return next(req);
};
```

```typescript
// src/app/core/http/error.interceptor.ts — gestión global de errores HTTP

import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../auth/auth.service';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const auth   = inject(AuthService);

  return next(req).pipe(
    catchError(err => {
      if (err.status === 401) {
        auth.logout();
      }
      if (err.status === 403) {
        router.navigate(['/inicio']);
      }
      return throwError(() => err);
    })
  );
};
```

---

## 6. PIPES

```typescript
// src/app/shared/pipes/{nombre}.pipe.ts

import { Pipe, PipeTransform } from '@angular/core';

@Pipe({ name: '{nombre}', standalone: true, pure: true })
export class {Nombre}Pipe implements PipeTransform {
  transform(value: TipoEntrada, ...args: any[]): TipoSalida {
    // transformación
    return resultado;
  }
}
```

### Pipes incluidos en FestApp

```typescript
// estado-pago.pipe.ts
@Pipe({ name: 'estadoPago', standalone: true, pure: true })
export class EstadoPagoPipe implements PipeTransform {
  transform(estado: EstadoPago): { label: string; color: string } {
    const map: Record<EstadoPago, { label: string; color: string }> = {
      no_requiere_pago: { label: 'Sin coste',         color: 'success' },
      pendiente:        { label: 'Pendiente de pago', color: 'warn'    },
      parcial:          { label: 'Pago parcial',      color: 'accent'  },
      pagado:           { label: 'Pagado',             color: 'success' },
      devuelto:         { label: 'Devuelto',           color: 'default' },
      cancelado:        { label: 'Cancelado',          color: 'default' },
    };
    return map[estado] ?? { label: estado, color: 'default' };
  }
}

// estado-inscripcion.pipe.ts
@Pipe({ name: 'estadoInscripcion', standalone: true, pure: true })
export class EstadoInscripcionPipe implements PipeTransform {
  transform(estado: EstadoInscripcion): { label: string; icon: string; color: string } {
    const map: Record<EstadoInscripcion, { label: string; icon: string; color: string }> = {
      pendiente:    { label: 'Pendiente',       icon: 'schedule',      color: 'warn'    },
      confirmada:   { label: 'Confirmada',      icon: 'check_circle',  color: 'success' },
      cancelada:    { label: 'Cancelada',       icon: 'cancel',        color: 'default' },
      lista_espera: { label: 'Lista de espera', icon: 'hourglass_top', color: 'accent'  },
    };
    return map[estado] ?? { label: estado, icon: 'help', color: 'default' };
  }
}

// tipo-evento-label.pipe.ts
@Pipe({ name: 'tipoEventoLabel', standalone: true, pure: true })
export class TipoEventoLabelPipe implements PipeTransform {
  transform(tipo: TipoEvento): string {
    const map: Record<TipoEvento, string> = {
      almuerzo: 'Almuerzo',
      comida:   'Comida',
      merienda: 'Merienda',
      cena:     'Cena',
      otro:     'Otro',
    };
    return map[tipo] ?? tipo;
  }
}
```

---

## 7. HELPERS Y UTILS

Crear en `src/app/core/utils/`. Un archivo por dominio de funciones.

```typescript
// src/app/core/utils/fecha.utils.ts

/**
 * Formatea una fecha ISO a 'DD/MM/YYYY'
 */
export function formatearFecha(iso: string): string {
  return new Date(iso).toLocaleDateString('es-ES', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  });
}

/**
 * Formatea una fecha ISO a 'DD/MM/YYYY HH:mm'
 */
export function formatearFechaHora(iso: string): string {
  return new Date(iso).toLocaleString('es-ES', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

/**
 * Comprueba si una fecha ISO está en el pasado
 */
export function esPasado(iso: string): boolean {
  return new Date(iso) < new Date();
}

/**
 * Comprueba si ahora está dentro del rango [inicio, fin]
 */
export function dentroDeRango(inicio: string, fin: string): boolean {
  const ahora = Date.now();
  return ahora >= new Date(inicio).getTime() && ahora <= new Date(fin).getTime();
}
```

```typescript
// src/app/core/utils/texto.utils.ts

/**
 * Elimina tildes y convierte a minúsculas para búsquedas y comparaciones
 */
export function normalizarTexto(texto: string): string {
  return texto
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

/**
 * Filtra un array por un término de búsqueda sobre campos string
 */
export function filtrarPorTexto<T>(items: T[], termino: string, campos: (keyof T)[]): T[] {
  const t = normalizarTexto(termino);
  if (!t) return items;
  return items.filter(item =>
    campos.some(campo => {
      const valor = item[campo];
      return typeof valor === 'string' && normalizarTexto(valor).includes(t);
    })
  );
}
```

```typescript
// src/app/core/utils/precio.utils.ts

/**
 * Formatea un número como precio en euros
 * Ejemplo: 12.5 → '12,50 €'
 */
export function formatearPrecio(precio: number): string {
  return precio.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
}

/**
 * Comprueba si un evento tiene coste real (algún menú es de pago)
 */
export function eventoTieneCoste(menus: { esDePago: boolean }[]): boolean {
  return menus.some(m => m.esDePago);
}
```

```typescript
// src/app/core/utils/iri.utils.ts

/**
 * Extrae el ID del final de un IRI de API Platform
 * '/api/eventos/42' → '42'
 */
export function idDesdeIri(iri: string): string {
  return iri.split('/').pop() ?? '';
}

/**
 * Construye un IRI para API Platform
 * iriDesde('eventos', 42) → '/api/eventos/42'
 */
export function iriDesde(coleccion: string, id: number | string): string {
  return `/api/${coleccion}/${id}`;
}

/**
 * Comprueba si un valor es un IRI (empieza por '/')
 */
export function esIri(valor: unknown): valor is string {
  return typeof valor === 'string' && valor.startsWith('/api/');
}
```

---

## 8. INTEGRACIÓN DE LIBRERÍAS

### Angular Material — convenciones

- Siempre `appearance="outline"` en `mat-form-field`
- Usar `MatSnackBar` para notificaciones no bloqueantes (éxito, error)
- Usar `MatDialog` para confirmaciones destructivas (eliminar, cancelar inscripción)
- Usar `MatStepper` para flujos de varios pasos (inscripción, registro)
- Usar `MatCalendar` para el calendario del inicio (marcar días con eventos)
- No mezclar botones Material con botones HTML nativos en la misma vista

### Tailwind CSS — convenciones

- Usar Tailwind para **layout y espaciado**: `flex`, `grid`, `gap-*`, `p-*`, `m-*`
- Usar Angular Material para **componentes UI**: inputs, botones, cards, dialogs
- No duplicar: si Angular Material ya tiene el componente, no recrearlo con Tailwind
- Mobile-first: clases base para móvil, modificadores `md:` y `lg:` para escritorio

```html
<!-- Ejemplo correcto: layout con Tailwind, componente con Material -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
  <mat-card *ngFor="...">
    ...
  </mat-card>
</div>
```

### ngx-translate — convenciones

```typescript
// Importar en el componente
import { TranslateModule } from '@ngx-translate/core';

// En el array imports del componente standalone
imports: [TranslateModule]

// En el template
{{ 'CLAVE.SUBCLAVE' | translate }}
{{ 'EVENTO.ESTADO' | translate: { estado: evento.estado } }}
```

- Claves en UPPER_SNAKE_CASE agrupadas por dominio: `EVENTO.TITULO`, `INSCRIPCION.CONFIRMAR`
- Los archivos de traducción van en `src/assets/i18n/{lang}.json`

---

## 9. CHECKLIST antes de entregar código

### Componente
- [ ] `standalone: true`
- [ ] `ChangeDetectionStrategy.OnPush`
- [ ] Usa `inject()` (no constructor injection)
- [ ] Inputs con `input()` / `input.required()` (signal-based)
- [ ] Template usa `@if`, `@for` con `track`, `@switch` (no directivas estructurales)
- [ ] No calcula precios (solo muestra los del backend)
- [ ] Skeleton loader cuando `cargando()` es true

### Servicio HTTP
- [ ] Extiende la interfaz `HydraCollection<T>` para colecciones
- [ ] PATCH usa `Content-Type: application/merge-patch+json`
- [ ] Maneja errores con `catchError` o deja que el interceptor los capture

### Formulario
- [ ] `FormGroup<{...}>` con tipos explícitos
- [ ] `FormControl<Tipo>` con `nonNullable: true` cuando el campo no puede ser null
- [ ] Mensajes de error para cada validación
- [ ] Botón de submit deshabilitado mientras `enviando()` o `form.invalid`

### Modelo
- [ ] Tipos union string para enums (no enums TypeScript)
- [ ] IRIs como `string` con comentario indicando la colección
- [ ] DTOs separados para create y update cuando difieren del modelo de lectura

### Guard / Interceptor
- [ ] Funcional (`CanActivateFn`, `HttpInterceptorFn`)
- [ ] No clases que implementen interfaces

### Pipes y utils
- [ ] Pipes con `pure: true` (por defecto, pero explicitarlo)
- [ ] Utils son funciones puras sin efectos secundarios
- [ ] `normalizarTexto` para cualquier comparación de strings con el usuario
