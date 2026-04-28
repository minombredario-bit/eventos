import { Injectable, inject } from '@angular/core';
import { CanActivate, Router, UrlTree } from '@angular/router';
import { AuthStore } from '../core/auth/auth-store';

@Injectable({ providedIn: 'root' })
export class LopdGuard implements CanActivate {
  private readonly authStore = inject(AuthStore);
  private readonly router = inject(Router);

  canActivate(): boolean | UrlTree {
    const user = this.authStore.user();

    // Sin usuario autenticado, el authGuard ya habrá redirigido al login
    if (!user) {
      return true;
    }

    // FIX: lógica corregida — si ya aceptó la LOPD, permite el acceso
    // Si no la aceptó, redirige a la pantalla de LOPD
    if (user.aceptoLopd) {
      return true;
    }

    return this.router.createUrlTree(['/lopd']);
  }
}
