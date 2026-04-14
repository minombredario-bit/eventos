import { Injectable, inject, effect } from '@angular/core';
import { AuthStore } from '../auth/auth-store';
import { TipoEntidad } from '../models/auth.models';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly authStore = inject(AuthStore);

  constructor() {
    // Reactivo: recalcula el tema cuando cambia el usuario autenticado
    effect(() => {
      const user = this.authStore.user();
      const tipo = this.resolveTipoEntidad(user?.tipoEntidad);
      this.applyTheme(tipo);
    });
  }

  private resolveTipoEntidad(value: unknown): TipoEntidad {
    if (typeof value === 'string' && value.toLowerCase() === 'comparsa') return 'Comparsa';
    return 'Falla'; // por defecto
  }

  private applyTheme(tipo: TipoEntidad): void {
    if (typeof document === 'undefined') return;
    document.documentElement.setAttribute('data-theme', tipo);
  }
}
