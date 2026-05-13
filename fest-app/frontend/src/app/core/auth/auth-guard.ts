import { inject } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivateFn, Router, RouterStateSnapshot, UrlTree } from '@angular/router';
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

/**
 * Permite el acceso al área admin a ROLE_ADMIN_ENTIDAD, ROLE_SUPERADMIN y ROLE_EVENTO.
 * ROLE_EVENTO solo verá las rutas permitidas (el resto usan fullAdminGuard).
 */
export const adminGuard: CanActivateFn = () => {
  const authStore = inject(AuthStore);
  const router = inject(Router);

  const user = authStore.user();
  const roles = Array.isArray(user?.roles) ? user.roles : [];
  const canEnterAdmin =
    roles.includes('ROLE_ADMIN_ENTIDAD') ||
    roles.includes('ROLE_SUPERADMIN') ||
    roles.includes('ROLE_EVENTO');

  if (canEnterAdmin) {
    try {
      sessionStorage.setItem('clientPanel', 'panel');
    } catch {
      // noop
    }
    return true;
  }

  try {
    sessionStorage.removeItem('clientPanel');
  } catch {
    // noop
  }

  return router.createUrlTree(['/eventos/inicio']);
};

/**
 * Restringe el acceso a rutas exclusivas de administrador completo.
 * - ROLE_ADMIN_ENTIDAD / ROLE_SUPERADMIN: acceso completo.
 * - ROLE_EVENTO: solo puede acceder a rutas que empiecen por /admin/eventos.
 * - Cualquier otro: redirigido a /eventos/inicio.
 *
 * Diseñado para usarse tanto en `canActivate` de rutas individuales
 * como en `canActivateChild` del padre admin (protección global).
 */
export const fullAdminGuard: CanActivateFn = (
  _route: ActivatedRouteSnapshot,
  state: RouterStateSnapshot,
) => {
  const authStore = inject(AuthStore);
  const router = inject(Router);

  const user = authStore.user();
  const roles = Array.isArray(user?.roles) ? user.roles : [];

  if (roles.includes('ROLE_ADMIN_ENTIDAD') || roles.includes('ROLE_SUPERADMIN')) {
    return true;
  }

  if (roles.includes('ROLE_EVENTO')) {
    // ROLE_EVENTO solo puede acceder a las rutas de gestión de eventos
    if (state.url.startsWith('/admin/eventos')) {
      return true;
    }
    return router.createUrlTree(['/admin/eventos']);
  }

  return router.createUrlTree(['/eventos/inicio']);
};



