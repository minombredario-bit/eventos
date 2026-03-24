import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { BottomNav } from '../shared/components/bottom-nav/bottom-nav';
import { NavItem } from './models/ui';

@Component({
  selector: 'app-eventos',
  standalone: true,
  imports: [RouterOutlet, BottomNav],
  templateUrl: './eventos.html',
  styleUrl: './eventos.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Eventos {
  private readonly router = inject(Router);

  private readonly currentUrl = toSignal(
    this.router.events.pipe(
      filter((event): event is NavigationEnd => event instanceof NavigationEnd),
      map(() => this.router.url),
      startWith(this.router.url),
    ),
    { initialValue: this.router.url },
  );

  private readonly currentEventId = computed(() => {
    const pathOnly = this.currentUrl().split('?')[0] ?? this.currentUrl();
    const match = pathOnly.match(/^\/eventos\/([^/]+)\/(detalle|menus|credencial)$/);
    return match?.[1] ?? null;
  });

  protected readonly navItems = computed<NavItem[]>(() => {
    const eventId = this.currentEventId();

    return [
      { key: 'inicio', label: 'Inicio', icon: '🏠', route: '/eventos/inicio' },
      { key: 'detalle', label: 'Detalle', icon: '📅', route: eventId ? `/eventos/${eventId}/detalle` : '/eventos/inicio' },
      { key: 'menus', label: 'Menús', icon: '🍽️', route: eventId ? `/eventos/${eventId}/menus` : '/eventos/inicio' },
      {
        key: 'credencial',
        label: 'Credencial',
        icon: '🎟️',
        route: eventId ? `/eventos/${eventId}/credencial` : '/eventos/inicio',
      },
    ];
  });
}
