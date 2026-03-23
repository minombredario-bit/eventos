import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthStore } from './auth-store';

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  if (!isApiRequest(request.url) || isPublicEndpoint(request.url)) {
    return next(request);
  }

  const authStore = inject(AuthStore);
  const token = authStore.getToken();

  if (!token) {
    return next(request);
  }

  return next(
    request.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`,
      },
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
