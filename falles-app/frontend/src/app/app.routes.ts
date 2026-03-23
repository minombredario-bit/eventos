import { Routes } from '@angular/router';
import { authGuard } from './core/auth/auth-guard';

export const routes: Routes = [
  {
    path: 'auth/login',
    loadComponent: () => import('./auth/login/login').then((m) => m.Login),
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
        loadComponent: () => import('./features/eventos/inicio/inicio').then((m) => m.Inicio),
      },
      {
        path: ':id/detalle',
        loadComponent: () => import('./features/eventos/detalle/detalle').then((m) => m.Detalle),
      },
      {
        path: ':id/menus',
        loadComponent: () => import('./features/eventos/menus/menus').then((m) => m.Menus),
      },
      {
        path: ':id/credencial',
        loadComponent: () => import('./features/eventos/credencial/credencial').then((m) => m.Credencial),
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
