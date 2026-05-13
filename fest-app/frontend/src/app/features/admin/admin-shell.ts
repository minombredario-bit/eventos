import { ChangeDetectionStrategy, Component, computed, inject, DestroyRef } from '@angular/core';
import { RouterOutlet, Router, NavigationEnd } from '@angular/router';
import { BottomNav } from '../shared/components/bottom-nav/bottom-nav';
import { NavItem } from '../eventos/domain/eventos.models';
import { AuthStore } from '../../core/auth/auth-store';

@Component({
  selector: 'app-admin-shell',
  standalone: true,
  imports: [RouterOutlet, BottomNav],
  templateUrl: './admin-shell.html',
  styleUrl: './admin-shell.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminShell {
  private readonly authStore = inject(AuthStore);

  private readonly navDefs: NavItem[] = [
    {
      key: 'dashboard',
      label: 'Dashboard',
      icon: '📊',
      route: '/admin/dashboard',
      visibleFor: ['ROLE_ADMIN_ENTIDAD', 'ROLE_SUPERADMIN'],
    },
    {
      key: 'eventos',
      label: 'Eventos',
      icon: '🎉',
      route: '/admin/eventos',
      // sin visibleFor → visible para cualquier rol admin
    },
    {
      key: 'censo',
      label: 'Censo',
      icon: '👥',
      route: '/admin/censo-usuarios',
      visibleFor: ['ROLE_ADMIN_ENTIDAD', 'ROLE_SUPERADMIN'],
    },
    {
      key: 'entidad',
      label: 'Entidad',
      icon: '🏛️',
      route: '/admin/entidad',
      visibleFor: ['ROLE_ADMIN_ENTIDAD', 'ROLE_SUPERADMIN'],
    },
  ];

  protected readonly navItems = computed<NavItem[]>(() => {
    const roles: string[] = this.authStore.user()?.roles ?? [];
    return this.navDefs.filter(({ visibleFor }) =>
      !visibleFor?.length || visibleFor.some(r => roles.includes(r))
    );
  });

  constructor() {
    const router = inject(Router);

    // Inicializar según la ruta actual
    try {
      if (router.url.startsWith('/admin')) {
        sessionStorage.setItem('clientPanel', 'panel');
      } else {
        sessionStorage.removeItem('clientPanel');
      }
    } catch {
      // noop
    }

    // Escuchar navegación y ajustar la flag por pestaña
    const sub = router.events.subscribe((event) => {
      if (event instanceof NavigationEnd) {
        try {
          if ((event as NavigationEnd).urlAfterRedirects.startsWith('/admin')) {
            sessionStorage.setItem('clientPanel', 'panel');
          } else {
            sessionStorage.removeItem('clientPanel');
          }
        } catch {
          // noop
        }
      }
    });

    // Limpiar la suscripción y la flag al destruir
    const destroyRef = inject(DestroyRef);
    destroyRef.onDestroy(() => {
      try {
        sub.unsubscribe();
      } catch {
        // noop
      }

      try {
        sessionStorage.removeItem('clientPanel');
      } catch {
        // noop
      }
    });
  }
}

