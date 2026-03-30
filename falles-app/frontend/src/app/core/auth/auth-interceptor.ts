import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthStore } from './auth-store';

let redirectingToLogin = false;

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  if (!isApiRequest(request.url) || isPublicEndpoint(request.url)) {
    return next(request);
  }

  const authStore = inject(AuthStore);
  const router = inject(Router);
  const token = authStore.getToken();

  if (!token) {
    return next(request);
  }

  if (isTokenExpired(token)) {
    forceLogoutAndRedirect(authStore, router);
    return throwError(() => new Error('Token expired'));
  }

  return next(
    request.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`,
      },
    }),
  ).pipe(
    catchError((error) => {
      if (error?.status === 401) {
        forceLogoutAndRedirect(authStore, router);
      }

      return throwError(() => error);
    }),
  );
};

function isApiRequest(url: string): boolean {
  const pathname = getPathname(url);
  return pathname.startsWith('/api/');
}

function isPublicEndpoint(url: string): boolean {
  const pathname = getPathname(url);
  return pathname === '/api/login' || pathname.startsWith('/api/registro');
}

function getPathname(url: string): string {
  try {
    return new URL(url, 'http://localhost').pathname;
  } catch {
    return url;
  }
}

function forceLogoutAndRedirect(authStore: AuthStore, router: Router): void {
  if (redirectingToLogin) return;

  redirectingToLogin = true;
  authStore.logout();

  void router.navigateByUrl('/auth/login').finally(() => {
    redirectingToLogin = false;
  });
}

function isTokenExpired(token: string): boolean {
  try {
    const payload = token.split('.')[1];
    if (!payload) return true;

    const decoded = JSON.parse(atob(payload)) as { exp?: number };
    if (typeof decoded.exp !== 'number') return false;

    const nowInSeconds = Math.floor(Date.now() / 1000);
    return decoded.exp <= nowInSeconds;
  } catch {
    return true;
  }
}
