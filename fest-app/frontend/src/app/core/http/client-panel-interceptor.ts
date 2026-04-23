import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthStore } from '../auth/auth-store';

/**
 * Interceptor global que añade cabeceras comunes y X-Client-Panel cuando el
 * usuario es admin. Se aplica a todas las peticiones API.
 */
export const clientPanelInterceptor: HttpInterceptorFn = (request, next) => {
  // Si la petición no es API devolvemos tal cual
  try {
    const url = new URL(request.url, 'http://localhost');
    const pathname = url.pathname;
    if (!pathname.startsWith('/api/')) {
      return next(request);
    }
  } catch {
    return next(request);
  }

  const authStore = inject(AuthStore);
  const user = authStore.user();

  const idioma = localStorage.getItem('idioma') ?? 'es-ES';
  const accept = request.headers.get('Accept') ?? 'application/json';
  const contentType = request.headers.get('Content-Type');

  const roles = Array.isArray(user?.roles) ? user.roles : [];
  const isAdmin = roles.includes('ROLE_ADMIN_ENTIDAD') || roles.includes('ROLE_ADMIN') || roles.includes('ROLE_SUPERADMIN');

  // Detectar si la app actual es el panel de administración mediante una
  // flag en sessionStorage por pestaña. Se debe establecer al entrar al panel admin:
  // sessionStorage.setItem('clientPanel', 'panel')
  const isAdminApp = (typeof window !== 'undefined') && (sessionStorage.getItem('clientPanel') === 'panel');
  const setHeaders: Record<string, string> = {
    Accept: accept,
    'Accept-Language': request.headers.get('Accept-Language') ?? idioma,
  };

  if (contentType) {
    setHeaders['Content-Type'] = contentType;
  }

  // Solo añadir X-Client-Panel cuando estemos en la UI de administración y el
  // usuario tiene rol de admin. Evita que un admin usando la vista de usuario
  // haga que el backend trate la llamada como desde el panel admin.
  if (isAdminApp && isAdmin) {
    // Añadir la palabra 'panel' en el valor para que el backend la detecte.
    setHeaders['X-Client-Panel'] = 'admin-panel';
  }

  // Preserve authorization header if present (avoid removing it)
  const existingAuth = request.headers.get('Authorization');
  if (existingAuth) {
    setHeaders['Authorization'] = existingAuth;
  } else {
    // fallback: try to read token from auth store
    try {
      const token = authStore.getToken?.() ?? null;
      if (token) setHeaders['Authorization'] = `Bearer ${token}`;
    } catch {
      // noop
    }
  }

  const cloned = request.clone({ setHeaders });
  return next(cloned);
};

