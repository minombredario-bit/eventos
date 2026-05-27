# REDISEÑO DEL PERFIL - Estructura Fluida y Coherente

## 🔴 PROBLEMA ACTUAL

El perfil actual es una lista larga de "panels/artículos" que se siente:
- Fragmentado (cada sección parece desconectada)
- Sin jerarquía clara (todo tiene el mismo peso visual)
- Sin flujo (saltos abruptos entre secciones)
- Demasiado "formularios apilados"

```
┌────────���────────────┐
│  Método de Pago     │  ← Panel aislado
└─────────────────────┘
┌─────────────────────┐
│  Datos Personales   │  ← Panel aislado
└─────────────────────┘
┌─────────────────────┐
│  Relaciones         │  ← Panel aislado
└���────────────────────┘
┌─────────────────────┐
│  Contraseña         │  ← Panel aislado
└─────────────────────┘
┌─────────────────────┐
│  Biometría          │  ← Panel aislado
└─────────────────────┘
┌─────────────────────┐
│  Dar de Baja        │  ← Panel peligroso al final
└─────────────────────┘
```

---

## ✅ SOLUCIÓN: ESTRUCTURA FLUIDA POR BLOQUES

Reorganizar en **3 bloques cohesivos** que fluyen naturalmente:

### **BLOQUE 1: Identidad & Acceso** (Lo importante)
```
┌──────────────────────────────────────┐
│ IDENTIDAD & ACCESO                   │
├──────────────────────────────────────┤
│ • Nombre, Apellidos                  │
│ • Email (no editable)                │
│ • Teléfono, Dirección                │
│ • Fecha de Nacimiento                │
│                      [Guardar cambios]│
├──────────────────────────────────────┤
│ • Cambiar Contraseña                 │
│ • Biometría (Huella / Face ID)       │
│ • Dispositivos registrados           │
└──────────────────────────────────────┘
```

### **BLOQUE 2: Familia & Relaciones** (Secundario)
```
┌──────────────────────────────────────┐
│ FAMILIA & RELACIONES                 │
├──────────────────────────────────────┤
│ Tu Perfil:                           │
│ • Nombre Apellidos                   │
│ • DNI / NIE                          │
│                                      │
│ Relaciones (Amigos/Familia):         │
│ □ Pepe García         [Editar][❌]   │
│ □ María López         [Editar][❌]   │
│                                      │
│ Dependientes (Hijos):                │
│ □ Niño García                        │
│   Edad: 8 años                       │
│   Relación: Hijo      [Editar][❌]   │
│                                      │
│                    [+ Agregar nuevo] │
└──────────────────────────────────────┘
```

### **BLOQUE 3: Forma de Pago & Cuenta** (Acciones)
```
┌──────────────────────────────────────┐
│ FORMA DE PAGO                        │
├──────────────────────────────────────┤
│ Método preferido                     │
│ □ Efectivo                           │
│ □ Tarjeta                            │
│ □ Transferencia                      │
│                                      │
│                      [Guardar cambios]│
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│ MI CUENTA                            │
├──────────────────────────────────────┤
│ [⚠️ Dar de baja de la asociación]   │
│ Solicitar la baja de ti y/o tus     │
│ dependientes                         │
└──────────────���───────────────────────┘
```

---

## 🎨 MEJORAS VISUALES

### 1. **Mejor Tipografía & Jerarquía**
```
IDENTIDAD & ACCESO          ← Sección título grande
────────────────────────────

Información Personal        ← Sub-sección
Nombre, apellidos, etc.

Seguridad & Acceso          ← Sub-sección
Contraseña, biometría, etc.
```

### 2. **Uso de Dividers Sutiles**
```
┌─ BLOQUE 1 ───────────────────────────┐
│ Identidad & Acceso                   │
│ (datos personales + seguridad)       │
└───────────────────────────────────────┘

                ↓ Separación visual (gap grande)

┌─ BLOQUE 2 ───────────────────────────┐
│ Familia & Relaciones                 │
└───────────────────────────────────────┘

                ↓ Separación visual

┌─ BLOQUE 3 ───────────────────────────┐
│ Forma de Pago & Mi Cuenta            │
└───────────────────────────────────────┘
```

### 3. **Color y Énfasis**
- **Bloque 1**: Normal (fondo estándar)
- **Bloque 2**: Normal (contenido familiar/social)
- **Bloque 3**: El "Dar de Baja" en **rojo/peligroso** para destacar

### 4. **Menos Paneles, Más Flujo**
```
ANTES: 8 artículos/panels separados
DESPUÉS: 3 bloques cohesivos con sub-secciones internas
```

---

## 📱 HTML PROPUESTO (Estructura Fluida)

```html
<main class="perfil-container">
  <app-mobile-header ... />

  <section class="perfil-wrapper">
    
    <!-- BLOQUE 1: IDENTIDAD & ACCESO -->
    <section class="perfil-block perfil-block--primary">
      <h2 class="block-title">Identidad & Acceso</h2>
      
      <!-- Sub-sección: Información Personal -->
      <article class="block-section">
        <h3 class="section-title">Información Personal</h3>
        <form [formGroup]="profileForm" (ngSubmit)="saveProfile()">
          <!-- campos: nombre, apellidos, teléfono, etc. -->
          <button type="submit">Guardar cambios</button>
        </form>
      </article>

      <!-- Divider sutil -->
      <div class="block-divider"></div>

      <!-- Sub-sección: Seguridad -->
      <article class="block-section">
        <h3 class="section-title">Seguridad & Autenticación</h3>
        <!-- Cambiar contraseña -->
        <!-- Biometría -->
        <!-- Dispositivos registrados -->
      </article>
    </section>

    <!-- BLOQUE 2: FAMILIA & RELACIONES -->
    <section class="perfil-block perfil-block--secondary">
      <h2 class="block-title">Familia & Relaciones</h2>
      
      <article class="block-section">
        <h3 class="section-title">Mi Perfil</h3>
        <!-- Info personal resumida -->
      </article>

      <div class="block-divider"></div>

      <article class="block-section">
        <h3 class="section-title">Mis Relaciones</h3>
        <!-- Amigos/Familia -->
        <!-- Dependientes/Hijos -->
      </article>
    </section>

    <!-- BLOQUE 3: FORMA DE PAGO & CUENTA -->
    <section class="perfil-block perfil-block--tertiary">
      <h2 class="block-title">Forma de Pago</h2>
      <article class="block-section">
        <!-- Seleccionar método -->
      </article>

      <div class="block-divider"></div>

      <article class="block-section perfil-section--danger">
        <h2 class="block-title block-title--danger">⚠️ Dar de Baja</h2>
        <!-- Solicitud de baja -->
      </article>
    </section>

  </section>
</main>
```

---

## 🎯 SCSS PROPUESTO (Flujo Visual)

```scss
.perfil-container {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

.perfil-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2.5rem;  // Separación grande entre bloques
  padding: 1.5rem 1rem;
  max-width: 640px;
  margin: 0 auto;
}

// ── Bloques principales ────────────────────────
.perfil-block {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--border-soft);
  padding: 1.5rem;
  box-shadow: var(--shadow-elevated);
}

.perfil-block--primary {
  background: linear-gradient(135deg, 
    color-mix(in srgb, var(--brand-primary) 8%, var(--surface)) 0%,
    var(--surface) 100%
  );
}

.perfil-block--secondary {
  background: color-mix(in srgb, var(--surface-soft) 60%, var(--surface));
}

// ── Títulos de bloque ──────────────────────────
.block-title {
  margin: 0 0 1rem;
  font-size: 1.3rem;
  font-weight: 900;
  color: var(--brand-ink);
  letter-spacing: -0.02em;
  text-transform: uppercase;
  font-size: clamp(1.2rem, 4vw, 1.4rem);
}

.block-title--danger {
  color: var(--color-error, #c62828);
}

// ── Sub-secciones dentro de bloques ─────���──────
.block-section {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
}

.section-title {
  margin: 0 0 0.5rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

// ── Divider sutil entre sub-secciones ──────────
.block-divider {
  height: 1px;
  background: color-mix(in srgb, var(--border-soft) 60%, transparent);
  margin: 1rem 0;
}

// ── Responsive ─────────────────────────────────
@media (max-width: 480px) {
  .perfil-wrapper {
    gap: 1.5rem;
    padding: 1rem 0.75rem;
  }

  .perfil-block {
    padding: 1.2rem;
    border-radius: 12px;
  }

  .block-title {
    font-size: 1.1rem;
  }
}
```

---

## 🏆 BENEFICIOS

| Aspecto | Antes | Después |
|---------|-------|---------|
| **Flujo visual** | Fragmentado | Continuo |
| **Jerarquía** | Plana | Clara |
| **Enfoque** | Múltiples focos | Bloques coherentes |
| **UX** | "Scroll infinito de forms" | "3 bloques lógicos" |
| **Método de pago** | Perdido en el medio | En su propio bloque |
| **Dar de baja** | Peligrosamente escondida | Destacada al final |

---

## 📐 ESTRUCTURA FINAL

```
┌─────────────────────────────────────┐
│        MI PERFIL                    │ ← Header
├─────────────────────────────────────┤
│                                     │
│ ╔═══════════════════════════════╗   │
│ ║ IDENTIDAD & ACCESO           ║   │ ← BLOQUE 1 (Primary)
│ ║ • Información Personal        ║   │
│ ║ • Seguridad & Autenticación  ║   │
│ ╚═══════════════════════════════╝   │
│                                     │
│ ╔═══════════════════════════════╗   │
│ ║ FAMILIA & RELACIONES         ║   │ ← BLOQUE 2 (Secondary)
│ ║ • Mi Perfil                   ║   │
│ ║ • Mis Relaciones              ║   │
│ ╚═══════════════════════════════╝   │
│                                     │
│ ╔═══════════════════════════════╗   │
│ ║ FORMA DE PAGO                ║   │ ← BLOQUE 3
│ ║ • Método preferido            ║   │
│ ├───────────────────────────────┤   │
│ ║ ⚠️  DAR DE BAJA              ║   │ ← Peligroso
│ ╚═══════════════════════════════╝   │
│                                     │
└─────────────────────────────────────┘
```

---

## 🚀 IMPLEMENTACIÓN

Este diseño **NO requiere refactor de código**:
1. Solo cambiar la estructura HTML
2. Agrupar los `<article>` en bloques
3. Ajustar el SCSS para flujo visual
4. Listo

O si prefieres, mantener los componentes pequeños (PersonalDataComponent, etc.) pero ahora **dentro de estos bloques lógicos**.

---

**¿Te gusta más esta estructura?** Puedo implementarla ahora mismo.

