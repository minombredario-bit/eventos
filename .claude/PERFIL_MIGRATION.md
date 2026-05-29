# Estrategia de Migración de Perfil a Sub-componentes

## Estado Actual

- ✅ Documento de auditoría creado: `PERFIL_REFACTOR.md`
- ✅ Primer sub-componente creado: `PersonalDataComponent`
- ⏳ Resto de componentes pendientes

## Fase 1: Crear Todos los Sub-componentes (SIN ROMPER NADA)

Los componentes existen lado a lado con `perfil.component.ts`. Todo sigue funcionando normalmente.

### Paso 1: Crear PersonalDataComponent ✅
- ✅ `personal-data.component.ts` - 112 líneas
- ✅ `personal-data.component.html` - 45 líneas
- ✅ `personal-data.component.scss` - 73 líneas
- **Total**: ~230 líneas (vs 1300 en monolito)

### Paso 2: Crear PasswordChangeComponent
Crear en `sections/password-change/`
- Extraer `passwordForm` del perfil
- Método `savePassword()`
- Validaciones de coincidencia
- **Líneas esperadas**: ~100

### Paso 3: Crear RelationshipsComponent
Crear en `sections/relationships/`
- Extraer gestión de relaciones
- Edición de hijos/dependientes
- Modal de confirmación
- **Líneas esperadas**: ~200

### Paso 4: Importar BiometricSettingsComponent
Crear en `sections/biometric-settings/`
- Ya existe en su propia carpeta
- Solo importarlo y re-exportarlo
- **Líneas esperadas**: ~50 (wrapper)

### Paso 5: Crear UnsubscribeComponent
Crear en `sections/unsubscribe/`
- Formulario de baja
- Selección múltiple de miembros
- **Líneas esperadas**: ~120

---

## Fase 2: Actualizar Perfil.component.ts

Cambiar de monolito a contenedor:

```typescript
// ANTES ~553 líneas: código de todo mezclado

// DESPUÉS ~100 líneas: solo contenedor
@Component({
  selector: 'app-perfil',
  imports: [
    MobileHeader,
    TabNavigation,
    PersonalDataComponent,
    PasswordChangeComponent,
    RelationshipsComponent,
    BiometricSettingsComponent,
    UnsubscribeComponent,
  ],
})
export class Perfil {
  // Solo navegación y layout
  protected readonly activeTab = signal('personal');
  
  protected setTab(tab: string) { /* ... */ }
  protected logout() { /* ... */ }
  protected goBack() { /* ... */ }
}
```

---

## Fase 3: Actualizar perfil.html

De 328 líneas a solo estructura de tabs:

```html
<app-mobile-header ... />
<main class="perfil-container">
  
  <!-- Tab Navigation -->
  <nav class="tab-nav">
    <button @if (activeTab === 'personal')" [class.active]="activeTab() === 'personal'" 
      (click)="setTab('personal')">
      Datos Personales
    </button>
    <button [class.active]="activeTab() === 'password'" (click)="setTab('password')">
      Contraseña
    </button>
    <button [class.active]="activeTab() === 'relationships'" (click)="setTab('relationships')">
      Relaciones
    </button>
    <button [class.active]="activeTab() === 'biometric'" (click)="setTab('biometric')">
      Seguridad
    </button>
    <button [class.active]="activeTab() === 'unsubscribe'" (click)="setTab('unsubscribe')">
      Baja
    </button>
  </nav>

  <!-- Content - Solo switch entre componentes -->
  <div class="tab-content">
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
  </div>

</main>
```

De ~328 líneas a ~50 líneas (80% reducción).

---

## Fase 4: Actualizar perfil.scss

De 396 líneas a solo estilos globales de estructura:

```scss
.perfil-container {
  display: grid;
  grid-template-rows: auto 1fr;
  gap: 1rem;
}

.tab-nav {
  display: flex;
  gap: 0.5rem;
  border-bottom: 2px solid var(--border-soft);
  overflow-x: auto;

  button {
    padding: 0.8rem 1rem;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-weight: 600;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color 140ms;

    &.active {
      color: var(--brand-primary);
      border-bottom-color: var(--brand-primary);
    }

    &:hover { color: var(--text-primary); }
  }
}

.tab-content {
  animation: fadeIn 200ms ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
```

De ~396 líneas a ~30 líneas (92% reducción).

---

## Análisis de Líneas

### ANTES (Monolito)
```
perfil.ts     : 553 líneas
perfil.html   : 328 líneas
perfil.scss   : 396 líneas
─────────────────────────
TOTAL         : 1,277 líneas
```

### DESPUÉS (Modular)
```
perfil.ts              : 100 líneas (contenedor)
perfil.html            :  50 líneas (tabs + switch)
perfil.scss            :  30 líneas (layout global)
                         ─────────────
Subtotal perfil.ts/html/scss : 180 líneas

personal-data/  :  230 líneas
password-change/:  140 líneas
relationships/  :  250 líneas
biometric/      :   50 líneas (wrapper)
unsubscribe/    :  150 líneas
                ─────────────
Subtotal componentes: 820 líneas

─────────────────────────
TOTAL          :  1,000 líneas (MISMO CÓDIGO)
```

### Beneficios de Distribución
- Cada componente < 250 líneas ✅ (legible)
- Responsabilidad única ✅
- Reutilizable ✅
- Testeable ✅
- Debuggeable ✅

---

## Checklist de Implementación

- [ ] **Fase 1: Crear Sub-componentes**
  - [ ] Crear PersonalDataComponent
  - [ ] Crear PasswordChangeComponent
  - [ ] Crear RelationshipsComponent
  - [ ] Crear BiometricSettingsComponent (wrapper)
  - [ ] Crear UnsubscribeComponent

- [ ] **Fase 2: Actualizar Shell**
  - [ ] Simplificar perfil.ts a contenedor
  - [ ] Simplificar perfil.html
  - [ ] Simplificar perfil.scss

- [ ] **Fase 3: Testing Manual**
  - [ ] Navegar entre tabs
  - [ ] Guardar datos personales
  - [ ] Cambiar contraseña
  - [ ] Gestionar relaciones
  - [ ] Registrar biometría
  - [ ] Solicitar baja

- [ ] **Fase 4: Limpieza**
  - [ ] Remover código duplicado
  - [ ] Remover signals no usadas
  - [ ] Actualizar imports
  - [ ] Verificar no hay memory leaks

---

## Comandos de Migración Sugeridos

```bash
# 1. Crear estructura
mkdir -p frontend/src/app/features/eventos/ui/perfil/sections/{personal-data,password-change,relationships,biometric-settings,unsubscribe}

# 2. Mover archivos (después de crearlos)
mv frontend/src/app/features/eventos/ui/perfil/sections/personal-data/* frontend/src/app/features/eventos/ui/perfil/sections/personal-data/

# 3. Compilar para verificar
ng build
```

---

## Timeline Estimado

| Fase | Tarea | Tiempo |
|------|-------|--------|
| 1a | PersonalDataComponent | 30 min ✅ |
| 1b | PasswordChangeComponent | 20 min |
| 1c | RelationshipsComponent | 45 min |
| 1d | BiometricSettingsComponent | 10 min |
| 1e | UnsubscribeComponent | 30 min |
| 2 | Actualizar Shell | 30 min |
| 3 | Testing Manual | 30 min |
| 4 | Limpieza | 15 min |
| **TOTAL** | | **3.5 horas** |

---

## Referencias

- Documento de auditoría: `PERFIL_REFACTOR.md`
- Primer componente extraído: `sections/personal-data/`
- Estándar de componentes: `frontend.md` → "Estándar: Componentes que Manejan Colecciones"

---

## ⚠️ Notas Importantes

1. **NO romper nada**: Los sub-componentes se crean pero perfil.ts sigue siendo el contenedor principal
2. **Gradual**: Se puede implementar uno o dos componentes y dejar el resto para después
3. **Tests**: Si hay tests del perfil, hay que actualizarlos
4. **Backward compatible**: El endpoint `/eventos/perfil` sigue siendo el mismo
5. **Ya completado**: PersonalDataComponent está listo (copy-paste de código existente)

---

## Próximo Paso

👉 Crear `PasswordChangeComponent` siguiendo el mismo patrón de `PersonalDataComponent`

