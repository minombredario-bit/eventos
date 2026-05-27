# Análisis: Refactor Componente Perfil

## 🚨 Problema Identificado

El componente `Perfil` tiene **1300 líneas** de código (TS + HTML + SCSS) y está mezclando **5 responsabilidades** distintas, violando el principio de **Responsabilidad Única**.

### Tamaño Actual
- `perfil.ts` - 553 líneas
- `perfil.html` - 328 líneas  
- `perfil.scss` - 396 líneas
- **TOTAL: 1277 líneas** 🔴

---

## 📊 Responsabilidades Actuales

El componente maneja:

### 1. **Perfil Personal** (30%)
- Edición de nombre, apellidos, dirección, teléfono, fecha nacimiento
- Método de pago preferido
- Form: `profileForm`

### 2. **Cambio de Contraseña** (15%)
- Form: `passwordForm`
- Validación de confirmación
- Método: `savePassword()`

### 3. **Relaciones** (25%)
- Amigos/familia
- Edición de hijos/dependientes
- Método: `loadRelaciones()`, `getRelacionadoNombre()`, etc.
- Signals: `relaciones`, `loadingRelaciones`, `editingChildId`, etc.

### 4. **Biometría/Passkeys** (20%)
- Gestión de credenciales
- Registro/eliminación de dispositivos
- Signals: `biometricCredentials`, `registeringBiometric`, etc.

### 5. **Dar de Baja** (10%)
- Solicitar baja de la asociación
- Selección múltiple de miembros
- Form: `unsubscribeForm`

---

## ✅ Solución Propuesta: Arquitectura de Sub-componentes

Dividir en componentes más pequeños y reutilizables:

```
src/app/features/eventos/ui/perfil/
├── perfil.component.ts          ← SHELL (contenedor)
├── perfil.component.html        ← Estructura y enrutamiento de tabs
├── perfil.component.scss        ← Estilos generales
│
├── sections/
│   ├── personal-data/           ← Sección 1: Perfil personal
│   │   ├── personal-data.component.ts      (150 líneas)
│   │   ├── personal-data.component.html
│   │   └── personal-data.component.scss
│   │
│   ├── password-change/         ← Sección 2: Cambio de contraseña
│   │   ├── password-change.component.ts    (100 líneas)
│   │   ├── password-change.component.html
│   │   └── password-change.component.scss
│   │
│   ├── relationships/           ← Sección 3: Relaciones
│   │   ├── relationships.component.ts      (200 líneas)
│   │   ├── relationships.component.html
│   │   ├── relationships.component.scss
│   │   └── relationship-item/   ← Componente de item individual
│   │       ├── relationship-item.component.ts
│   │       ├── relationship-item.component.html
│   │       └── relationship-item.component.scss
│   │
│   ├── biometric-settings/      ← Sección 4: Biometría
│   │   ├── biometric-settings.component.ts (100 líneas)
│   │   ├── biometric-settings.component.html
│   │   └── biometric-settings.component.scss
│   │
│   └── unsubscribe/             ← Sección 5: Dar de baja
│       ├── unsubscribe.component.ts        (120 líneas)
│       ├── unsubscribe.component.html
│       └── unsubscribe.component.scss
│
└── shared/                      ← (Opcional) Componentes compartidos
    └── feedback-message/            (si se necesita compartir)
```

---

## 🏗️ Nueva Arquitectura

### Contenedor Principal: `perfil.component.ts`

El shell solo se encargará de:
- Layout general (header, footers, tabs)
- Navegación entre secciones
- Inyectar sub-componentes dinámicamente
- Manejo global de auth/logout

**Líneas esperadas**: ~100 (muy limpio)

```typescript
@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [
    MobileHeader,
    PersonalDataComponent,
    PasswordChangeComponent,
    RelationshipsComponent,
    BiometricSettingsComponent,
    UnsubscribeComponent,
  ],
  template: `
    <app-mobile-header ... />
    <main class="perfil-shell">
      @switch (activeTab()) {
        @case ('personal') {
          <app-personal-data />
        }
        @case ('password') {
          <app-password-change />
        }
        @case ('relationships') {
          <app-relationships />
        }
        @case ('biometric') {
          <app-biometric-settings />
        }
        @case ('unsubscribe') {
          <app-unsubscribe />
        }
      }
    </main>
  `,
  styleUrl: './perfil.component.scss',
})
export class Perfil {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  
  protected readonly activeTab = signal<'personal' | 'password' | 'relationships' | 'biometric' | 'unsubscribe'>('personal');
  
  protected logout() { /* ... */ }
  protected goBack() { /* ... */ }
  protected setActiveTab(tab: string) { /* ... */ }
}
```

---

## 📋 Beneficios del Refactor

| Aspecto | Antes | Después |
|--------|-------|---------|
| **Líneas por componente** | 1300 | 100-200 |
| **Responsabilidades** | 5 | 1 |
| **Testabilidad** | 🔴 Difícil | 🟢 Fácil |
| **Reusabilidad** | 🔴 No | 🟢 Sí (PersonalData) |
| **Mantenibilidad** | 🔴 Difícil | 🟢 Fácil |
| **Debugging** | 🔴 Confuso | 🟢 Claro |
| **Testing total** | 1 suite | 5+ suites |

---

## 🔁 Implementación Secuencial

### **Fase 1: Preparar estructura** (SIN romper nada)
1. Crear carpeta `sections/`
2. Crear `personal-data.component.ts` (copia el código existente)
3. Crear `password-change.component.ts` (copia el código)
4. Mover archivos HTML/SCSS a sus carpetas

### **Fase 2: Extraer componentes**
5. **PersonalDataComponent**: Mover lógica de `profileForm` y `saveProfile()`
6. **PasswordChangeComponent**: Mover `passwordForm` y `savePassword()`
7. **RelationshipsComponent**: Mover relaciones y edición de hijos
8. **BiometricSettingsComponent**: Mover biometría (o importar el existente)
9. **UnsubscribeComponent**: Mover dar de baja

### **Fase 3: Limpiar shell**
10. Dejar `perfil.component.ts` como contenedor
11. Sistema de tabs/secciones
12. Eliminar código duplicado

---

## 🔧 Servicios Compartidos Necesarios

Algunos servicios ya inyectados deberían ser compartidos entre sub-componentes:

```typescript
// En sub-componentes
private readonly authService = inject(AuthService);
private readonly eventosApi = inject(EventosApi);
private readonly destroyRef = inject(DestroyRef);
private readonly router = inject(Router);
```

Estos son servicios root, así que está bien que se inyecten múltiples veces.

---

## 📝 Ejemplo: PersonalDataComponent

```typescript
// personal-data.component.ts (150 líneas)
@Component({
  selector: 'app-personal-data',
  standalone: true,
  imports: [ReactiveFormsModule, CtaButton, CommonModule],
  templateUrl: './personal-data.component.html',
  styleUrl: './personal-data.component.scss',
})
export class PersonalDataComponent {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly profileForm = this.fb.nonNullable.group({
    nombre: [''],
    apellidos: [''],
    // ... resto de campos
  });

  protected readonly savingProfile = signal(false);
  protected readonly profileMessage = signal<Feedback | null>(null);
  
  // ... solo métodos relacionados a perfil personal

  protected saveProfile() { /* ... */ }
}
```

---

## ❌ Antipatrones Actuales

```typescript
// ❌ Así está AHORA en perfil.ts:
export class Perfil {
  // Señales de perfil
  protected readonly savingProfile = signal(false);
  
  // Señales de contraseña
  protected readonly savingPassword = signal(false);
  
  // Señales de relaciones
  protected readonly loadingRelaciones = signal(false);
  
  // Señales de biometría
  protected readonly registeringBiometric = signal(false);
  
  // Señales de baja
  protected readonly unsubmitting = signal(false);
  
  // ... 553 líneas de código mezclado
}
```

```typescript
// ✅ Así debería ser:
export class Perfil {
  // Solo responsabilidades de layout/navegación
  protected readonly activeTab = signal('personal');
  
  protected goBack() { /* ... */ }
  protected logout() { /* ... */ }
  protected setActiveTab(tab: string) { /* ... */ }
  // Total: ~50 líneas
}
```

---

## 🎯 Próximos Pasos

1. ✅ Crear documento (HECHO - estás leyendo)
2. Crear estructura de carpetas
3. Extraer cada componente, uno por uno
4. Verificar que no se rompa nada
5. Limpiar perfil.component.ts
6. Actualizar tests (si existen)

---

## 📌 Nota Importante

Este refactor:
- ✅ NO rompe la funcionalidad actual
- ✅ Se puede hacer de forma incremental
- ✅ Mejora drasticamente el código
- ✅ Facilita agregar nuevas secciones
- ⏱️ Tiempo estimado: ~4 horas de refactor

Recomendación: **IMPLEMENTAR DESPUÉS de que todo funcione perfecto**, o en una rama separada si urgencia.

