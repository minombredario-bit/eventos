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
