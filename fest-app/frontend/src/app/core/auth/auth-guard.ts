import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthStore } from './auth-store';

export const authGuard: CanActivateFn = (_, state) => {
  const authStore = inject(AuthStore);
  const router = inject(Router);

  if (authStore.isAuthenticated()) {
    const user = authStore.user();
    if (Boolean(user?.['debeCambiarPassword']) && state.url !== '/auth/cambiar-password') {
      return router.createUrlTree(['/auth/cambiar-password']);
    }

    return true;
  }

  return router.createUrlTree(['/auth/login'], {
    queryParams: {
      returnUrl: state.url,
    },
  });
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

