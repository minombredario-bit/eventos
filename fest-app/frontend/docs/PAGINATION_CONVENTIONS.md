# Convenciones de Paginado en Componentes de Lista

## Descripción General

Todos los componentes que muestran listas paginadas deben seguir estas convenciones para asegurar consistencia, mantenibilidad y UX uniforme en toda la aplicación.

## Estructura Base

### 1. Constante PAGE_SIZE

```typescript
private static readonly PAGE_SIZE = 10;
```

**Normas:**
- Tamaño recomendado: **10 elementos** por página (optimizado para dispositivos móviles)
- Debe ser `private static readonly`
- No debe variar sin motivo explícito

### 2. Signals de Estado

#### loading y transitioning

```typescript
protected readonly loading       = signal(true);
protected readonly transitioning = signal(false);
```

**Diferencia:**
- **`loading`**: Indica que la página está CARGANDO INICIALMENTE (spinner en la UI)
- **`transitioning`**: Indica cambio entre páginas o filtros (feedback visual más sutil)

**Uso en template:**
```html
<div *ngIf="loading()">Cargando...</div>
<div *ngIf="transitioning()" class="spinner--subtle">Actualizando...</div>
```

### 3. Signal de Página

```typescript
protected readonly [nombreEntidad]Page = signal<[ModeloPage]>({
  items: [],
  totalPages: 0,
  totalItems: 0,
  page: 1,
  itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
  hasNext: false,
  hasPrevious: false,
});
```

**Ejemplo en censo-usuarios.ts:**
```typescript
protected readonly usuariosPage = signal<UsuariosPage>({...});
```

**Ejemplo en inscripciones.ts:**
```typescript
protected readonly inscripcionesPage = signal<InscripcionesPage>({...});
```

### 4. Computados para Acceso Rápido

```typescript
protected readonly [items]        = computed<[Modelo][]>(() => this.[nombreEntidad]Page().items);
protected readonly totalItems      = computed<number>(() => this.[nombreEntidad]Page().totalItems);
protected readonly currentPage     = computed<number>(() => this.[nombreEntidad]Page().page);
protected readonly totalPages      = computed<number>(() => this.[nombreEntidad]Page().totalPages);
protected readonly hasNextPage     = computed<boolean>(() => this.[nombreEntidad]Page().hasNext);
protected readonly hasPreviousPage = computed<boolean>(() => this.[nombreEntidad]Page().hasPrevious);
```

**Ejemplo (censo-usuarios.ts):**
```typescript
protected readonly usuarios        = computed<Usuario[]>(() => this.usuariosPage().items);
protected readonly totalItems      = computed<number>(() => this.usuariosPage().totalItems);
protected readonly currentPage     = computed<number>(() => this.usuariosPage().page);
protected readonly hasNextPage     = computed<boolean>(() => this.usuariosPage().hasNext);
protected readonly hasPreviousPage = computed<boolean>(() => this.usuariosPage().hasPrevious);
```

## Constructor

```typescript
constructor() {
  // ... suscripciones a filtros ...
  
  this.load[Entidad](1, true);  // ← Importante: isInitial = true
}
```

**Ejemplo (inscripciones.ts):**
```typescript
constructor() {
  this.searchSubject.pipe(
    debounceTime(400),
    distinctUntilChanged(),
    takeUntilDestroyed(this.destroyRef),
  ).subscribe((value) => {
    this.searchTerm.set(value);
    this.load[Entidad](1);  // ← Sin isInitial, usa default = false
  });

  this.load[Entidad](1, true);  // ← Primera carga con isInitial = true
}
```

## Método Privado de Carga: load[Entidad]

### Firma

```typescript
private load[Entidad](page = 1, isInitial = false): void {
  isInitial ? this.loading.set(true) : this.transitioning.set(true);
  this.errorMessage.set(null);

  this.[api]
    .get[Entidad]Collection({
      // ... filtros ...
      page,
      itemsPerPage: [Clase].PAGE_SIZE,
    })
    .pipe(
      finalize(() => {
        this.loading.set(false);
        this.transitioning.set(false);
      }),
      takeUntilDestroyed(this.destroyRef),
    )
    .subscribe({
      next: (page) => this.[nombreEntidad]Page.set(page),
      error: (error: { error?: { error?: string } }) => {
        this.[nombreEntidad]Page.set({
          items: [],
          totalItems: 0,
          totalPages: 0,
          page: 1,
          itemsPerPage: [Clase].PAGE_SIZE,
          hasNext: false,
          hasPrevious: false,
        });
        this.errorMessage.set(error?.error?.error ?? 'Mensaje de error genérico.');
      },
    });
}
```

### Detalles Clave

1. **Parámetro `isInitial`**: Diferencia entre primera carga (loading) y cambios posteriores (transitioning)
2. **`finalize` siempre resetea ambos signals**: Así el UI nunca queda en estado inconsistente
3. **Error: Reset a estado vacío**: Siempre devuelve el estado inicial con estructura valid

### Ejemplo (inscripciones.ts)

```typescript
private loadInscripciones(page = 1, isInitial = false): void {
  isInitial ? this.loading.set(true) : this.transitioning.set(true);
  this.errorMessage.set(null);

  this.eventosApi
    .getMisInscripcionesCollection({
      search: this.searchTerm(),
      page,
      itemsPerPage: Inscripciones.PAGE_SIZE,
    })
    .pipe(
      finalize(() => {
        this.loading.set(false);
        this.transitioning.set(false);
      }),
      takeUntilDestroyed(this.destroyRef),
    )
    .subscribe({
      next: (inscripcionesPage) => this.inscripcionesPage.set(inscripcionesPage),
      error: (error: { error?: { error?: string } }) => {
        this.inscripcionesPage.set({
          items: [],
          totalItems: 0,
          totalPages: 0,
          page: 1,
          itemsPerPage: Inscripciones.PAGE_SIZE,
          hasNext: false,
          hasPrevious: false,
        });
        this.errorMessage.set(error?.error?.error ?? 'No se pudo cargar tus eventos.');
      },
    });
}
```

## Métodos Públicos de Paginación

### loadNextPage()

```typescript
protected loadNextPage(): void {
  if (!this.hasNextPage()) {
    return;
  }

  this.load[Entidad](this.currentPage() + 1);
}
```

### loadPreviousPage()

```typescript
protected loadPreviousPage(): void {
  if (!this.hasPreviousPage()) {
    return;
  }

  this.load[Entidad](this.currentPage() - 1);
}
```

**Nota:** No pasan `isInitial`, usan el default `false`.

## Filtros y Búsqueda

### Patrón: search con debounce

```typescript
private readonly searchSubject = new Subject<string>();

constructor() {
  this.searchSubject.pipe(
    debounceTime(400),
    distinctUntilChanged(),
    takeUntilDestroyed(this.destroyRef),
  ).subscribe((value) => {
    this.searchTerm.set(value);
    this.load[Entidad](1);  // ← Siempre vuelve a página 1
  });
}

protected setSearchTerm(value: string): void {
  this.searchSubject.next(value);
}
```

### Patrón: Filtro sin debounce con validación

```typescript
protected setFiltro(filtro: UsuariosFiltro): void {
  if (this.filtro() === filtro) return;  // ← Previene llamadas innecesarias
  this.filtro.set(filtro);
  this.load[Entidad](1);  // ← Vuelve a página 1
}
```

### Patrón: Filtro numérico/booleano sin debounce

```typescript
protected setTipoPersona(value: TipoPersonaFiltro): void {
  if (this.tipoPersona() === value) return;
  this.tipoPersona.set(value);
  this.load[Entidad](1);
}
```

## Ejemplo Completo: Censo Usuarios (✅ CORRECTO)

```typescript
export class AdminCensoUsuarios {
  private static readonly PAGE_SIZE = 10;

  private readonly router      = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi    = inject(AdminApi);
  private readonly destroyRef  = inject(DestroyRef);

  protected readonly loading       = signal(true);
  protected readonly transitioning = signal(false);
  protected readonly errorMessage  = signal<string | null>(null);

  protected readonly searchTerm = signal('');
  protected readonly filtro    = signal<UsuariosFiltro>('censado');

  protected readonly usuariosPage = signal<UsuariosPage>({
    items: [],
    totalPages: 0,
    totalItems: 0,
    page: 1,
    itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
    hasNext: false,
    hasPrevious: false,
  });

  protected readonly usuarios        = computed<Usuario[]>(() => this.usuariosPage().items);
  protected readonly hasNextPage     = computed<boolean>(() => this.usuariosPage().hasNext);
  protected readonly hasPreviousPage = computed<boolean>(() => this.usuariosPage().hasPrevious);

  constructor() {
    this.loadUsuarios(1, true);  // ← isInitial = true
  }

  protected setSearchTerm(value: string): void {
    this.searchTerm.set(value);
    this.loadUsuarios(1);  // ← isInitial = false (default)
  }

  protected loadNextPage(): void {
    if (!this.hasNextPage()) return;
    this.loadUsuarios(this.currentPage() + 1);
  }

  protected loadPreviousPage(): void {
    if (!this.hasPreviousPage()) return;
    this.loadUsuarios(this.currentPage() - 1);
  }

  private loadUsuarios(page = 1, isInitial = false): void {
    isInitial ? this.loading.set(true) : this.transitioning.set(true);
    this.errorMessage.set(null);

    this.adminApi.getUsuarios({
      search: this.searchTerm() || undefined,
      filtro: this.filtro(),
      page,
      itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
    }).pipe(
      finalize(() => {
        this.loading.set(false);
        this.transitioning.set(false);
      }),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe({
      next: (page) => this.usuariosPage.set(page),
      error: (error) => {
        this.usuariosPage.set({
          items: [],
          totalItems: 0,
          totalPages: 0,
          page: 1,
          itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
          hasNext: false,
          hasPrevious: false,
        });
        this.errorMessage.set(error?.error?.error ?? 'Error cargando usuarios.');
      },
    });
  }
}
```

## Resumen de Cambios Requeridos

Si detectas que un componente NO sigue estas convenciones:

- [ ] PAGE_SIZE = 10
- [ ] Ambos signals: `loading` y `transitioning`
- [ ] Signal `[nombreEntidad]Page` con estructura completa
- [ ] Computados para items, totalItems, currentPage, hasNextPage, hasPreviousPage
- [ ] Método privado `load[Entidad](page = 1, isInitial = false)`
- [ ] Constructor llama a `load[Entidad](1, true)`
- [ ] Métodos públicos: `loadNextPage()` y `loadPreviousPage()`
- [ ] Filtros con validación y reseteo a página 1

## Referencias

- **censo-usuarios.ts**: Ejemplo completo y correcto
- **inscripciones.ts**: Corregido en junio 2026
- **Modelos de página**: Deben incluir `page`, `itemsPerPage`, `totalPages`, `hasNext`, `hasPrevious`

