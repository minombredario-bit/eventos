import { Routes } from '@angular/router';
import { adminGuard, authGuard, fullAdminGuard } from './core/auth/auth-guard';

export const routes: Routes = [
  {
    path: 'auth/login',
    loadComponent: () =>
      import('./auth/login/login').then((m) => m.Login),
  },
  {
    path: 'lopd',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./lopd/lopd.component').then((m) => m.LopdComponent),
  },
  {
    path: 'auth/cambiar-password',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./auth/password-change/password-change').then((m) => m.PasswordChange),
  },
  {
    path: 'login',
    pathMatch: 'full',
    redirectTo: 'auth/login',
  },

  /* =====================================
     EVENTOS
  ===================================== */
  {
    path: 'eventos',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./features/eventos/eventos').then((m) => m.Eventos),
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
        path: ':id/actividades',
        loadComponent: () =>
          import('./features/eventos/ui/actividades/actividades').then((m) => m.Actividades),
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

  /* =====================================
     ADMIN
  ===================================== */
  {
    path: 'admin',
    canActivate: [authGuard, adminGuard],
    loadComponent: () =>
      import('./features/admin/admin-shell').then((m) => m.AdminShell),
    children: [
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'eventos',
      },
      {
        path: 'dashboard',
        canActivate: [fullAdminGuard],
        loadComponent: () =>
          import('./features/admin/ui/dashboard/dashboard').then((m) => m.AdminDashboard),
      },
      {
        path: 'eventos',
        loadComponent: () =>
          import('./features/admin/ui/eventos/eventos').then((m) => m.AdminEventos),
      },
      {
        path: 'eventos/crear',
        loadComponent: () =>
          import('./features/admin/ui/evento-form/evento-form').then((m) => m.AdminEventoForm),
      },
      {
        path: 'eventos/:id',
        loadComponent: () =>
          import('./features/admin/ui/evento-form/evento-form').then((m) => m.AdminEventoForm),
      },
      {
        path: 'entidad',
        canActivate: [fullAdminGuard],
        loadComponent: () =>
          import('./features/admin/ui/entidad/entidad-form').then((m) => m.AdminEntidadForm),
      },
      {
        path: 'censo-usuarios',
        canActivate: [fullAdminGuard],
        loadComponent: () =>
          import('./features/admin/ui/censo-usuarios/censo-usuarios').then((m) => m.AdminCensoUsuarios),
      },
      {
        path: 'usuarios/crear',
        canActivate: [fullAdminGuard],
        loadComponent: () =>
          import('./features/admin/ui/usuario/usuario-form').then((m) => m.AdminUsuarioForm),
      },
      {
        path: 'usuarios/:id',
        canActivate: [fullAdminGuard],
        loadComponent: () =>
          import('./features/admin/ui/usuario/usuario-form').then((m) => m.AdminUsuarioForm),
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
