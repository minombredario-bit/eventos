import { Routes } from '@angular/router';
import { authGuard } from './core/auth/auth-guard';

export const routes: Routes = [
  {
    path: 'auth/login',
    loadComponent: () => import('./auth/login/login').then((m) => m.Login),
  },
  {
    path: 'auth/cambiar-password',
    canActivate: [authGuard],
    loadComponent: () => import('./auth/password-change/password-change').then((m) => m.PasswordChange),
  },
  {
    path: 'login',
    pathMatch: 'full',
    redirectTo: 'auth/login',
  },
  {
    path: 'eventos',
    canActivate: [authGuard],
    loadComponent: () => import('./features/eventos/eventos').then((m) => m.Eventos),
    children: [
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'inicio',
      },
      {
        path: 'inicio',
        loadComponent: () =>
          import('./features/eventos/ui/inicio/inicio').then((m) => m.Inicio),
      },
      {
        path: 'inscripciones',
        loadComponent: () =>
          import('./features/eventos/ui/inscripciones/inscripciones').then((m) => m.Inscripciones),
      },
      {
        path: 'perfil',
        loadComponent: () =>
          import('./features/eventos/ui/perfil/perfil').then((m) => m.Perfil),
      },
      {
        path: ':id/detalle',
        loadComponent: () =>
          import('./features/eventos/ui/detalle/detalle').then((m) => m.Detalle),
      },
      {
        path: ':id/menus',
        loadComponent: () =>
          import('./features/eventos/ui/menus/menus').then((m) => m.Menus),
      },
      {
        path: ':id/apuntados',
        loadComponent: () =>
          import('./features/eventos/ui/apuntados/apuntados').then((m) => m.Apuntados),
      },
      {
        path: ':id/credencial',
        loadComponent: () =>
          import('./features/eventos/ui/credencial/credencial').then((m) => m.Credencial),
      },
      {
        path: ':id/pago',
        loadComponent: () =>
          import('./features/eventos/ui/pago/pago').then((m) => m.Pago),
      },
    ],
  },
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'eventos',
  },
  {
    path: '**',
    redirectTo: 'eventos',
  },
];
