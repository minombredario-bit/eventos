import { inject } from '@angular/core';
import {CanActivateFn, Router, UrlTree} from '@angular/router';
import { AuthStore } from './auth-store';
import {isTokenExpired} from '../../auth/utils/auth.utils';

export const authGuard: CanActivateFn = (): boolean | UrlTree => {
  const authStore = inject(AuthStore);
  const router = inject(Router);

  const token = authStore.getToken();

  if (!token) {
    return router.createUrlTree(['/auth/login']);
  }

  if (isTokenExpired(token)) {
    authStore.logout();
    return router.createUrlTree(['/auth/login']);
  }

  return true;
};

export const adminGuard: CanActivateFn = () => {
  const authStore = inject(AuthStore);
  const router = inject(Router);

  const user = authStore.user();
  const roles = Array.isArray(user?.roles) ? user.roles : [];
  const isAdmin = roles.includes('ROLE_ADMIN_ENTIDAD') || roles.includes('ROLE_SUPERADMIN');

  if (isAdmin) {
    // Marcar en sessionStorage que esta pestaña es panel admin antes de cargar
    try {
      sessionStorage.setItem('clientPanel', 'panel');
    } catch {
      // noop
    }
    return true;
  }

  // Asegurar que no quede la marca si no es admin
  try {
    sessionStorage.removeItem('clientPanel');
  } catch {
    // noop
  }

  return router.createUrlTree(['/eventos/inicio']);
};

