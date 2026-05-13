import { inject } from '@angular/core';
import { CanActivateFn, Router, ActivatedRouteSnapshot } from '@angular/router';
import {AuthStore} from '../../../../core/auth/auth-store';


/**
 * Uso en las rutas:
 *   canActivate: [adminRouteAccessGuard],
 *   data: { requiredRoles: ['ROLE_ADMIN_ENTIDAD', 'ROLE_SUPERADMIN'] }
 */
export const adminRouteAccessGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const authStore = inject(AuthStore);
  const router = inject(Router);

  const roles: string[] = authStore.user()?.roles ?? [];
  const required: string[] = route.data['requiredRoles'] ?? [];

  if (!required.length || required.some(r => roles.includes(r))) {
    return true;
  }

  // ROLE_EVENTO puede acceder a /admin/eventos, para el resto redirige ahí
  if (roles.includes('ROLE_EVENTO')) {
    return router.createUrlTree(['/admin/eventos']);
  }

  // Sin ningún rol admin conocido → login
  return router.createUrlTree(['/auth/login']);
};
