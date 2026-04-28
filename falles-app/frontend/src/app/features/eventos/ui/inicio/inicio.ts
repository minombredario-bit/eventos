import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AuthService } from '../../../../core/auth/auth';
import { EventCard } from '../../../shared/components/event-card/event-card';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventSummary } from '../../domain/eventos.models';
import { EventosStore } from '../../store/eventos.store';
import { formatDateKey, normalizeDateKey } from '../../../../core/utils/date.utils';

interface CalendarCell {
  date: Date;
  key: string;
  isCurrentMonth: boolean;
}

const WEEK_DAYS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

@Component({
  selector: 'app-inicio',
  standalone: true,
  imports: [MobileHeader, EventCard, DatePipe],
  templateUrl: './inicio.html',
  styleUrl: './inicio.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Inicio {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  readonly store = inject(EventosStore);

  protected readonly weekDays = WEEK_DAYS;
  protected readonly notificationsSupported =
    typeof window !== 'undefined' &&
    'Notification' in window &&
    'serviceWorker' in navigator &&
    'PushManager' in window;
  protected readonly notificationPermission = signal<NotificationPermission>(
    this.notificationsSupported ? Notification.permission : 'denied',
  );
  protected readonly notificationMessage = signal('');
  protected readonly notificationsBusy = signal(false);

  // Signals exposed for template
  protected readonly loading = this.store.loadingEventos;
  protected readonly errorMessage = this.store.errorEventos;
  protected readonly upcomingEvents = this.store.upcomingEvents;

  private readonly today = new Date();
  protected readonly monthCursor = signal(
    new Date(this.today.getFullYear(), this.today.getMonth(), 1),
  );
  protected readonly selectedDateKey = signal(formatDateKey(this.today));

  constructor() {
    this.store.loadEventos().pipe(takeUntilDestroyed()).subscribe();
  }

  protected readonly monthTitle = computed(() =>
    this.monthCursor().toLocaleDateString('es-ES', { month: 'long', year: 'numeric' }),
  );

  protected readonly calendarCells = computed<CalendarCell[]>(() => {
    const monthStart = this.monthCursor();
    const year = monthStart.getFullYear();
    const month = monthStart.getMonth();
    const offset = (new Date(year, month, 1).getDay() + 6) % 7;
    const gridStart = new Date(year, month, 1 - offset);

    return Array.from({ length: 42 }, (_, i) => {
      const date = new Date(
        gridStart.getFullYear(),
        gridStart.getMonth(),
        gridStart.getDate() + i,
      );
      return { date, key: formatDateKey(date), isCurrentMonth: date.getMonth() === month };
    });
  });

  protected readonly selectedDayEvents = computed<EventSummary[]>(() =>
    this.store.eventsByDate()[this.selectedDateKey()] ?? [],
  );

  protected readonly selectedDateLabel = computed(() => {
    const cell = this.calendarCells().find((c) => c.key === this.selectedDateKey());
    return (cell?.date ?? this.today).toLocaleDateString('es-ES', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    });
  });

  protected readonly selectedDayState = computed(() => {
    const statuses = this.selectedDayEvents().map((e) => e.status);
    if (!statuses.length) return 'sin-eventos';
    if (statuses.some((s) => s === 'abierto')) return 'abierto';
    if (statuses.some((s) => s === 'ultimas_plazas')) return 'ultimas_plazas';
    return 'cerrado';
  });

  protected moveMonth(diff: number): void {
    const c = this.monthCursor();
    this.monthCursor.set(new Date(c.getFullYear(), c.getMonth() + diff, 1));
  }

  protected pickDate(cell: CalendarCell): void {
    this.selectedDateKey.set(cell.key);
  }

  protected hasEvents(key: string): boolean {
    return Boolean(this.store.eventsByDate()[key]?.length);
  }

  protected isSelected(key: string): boolean {
    return this.selectedDateKey() === key;
  }

  protected dayStateLabel(state: string): string {
    const labels: Record<string, string> = {
      abierto: 'Con inscripción abierta',
      ultimas_plazas: 'Con últimas plazas',
      cerrado: 'Solo eventos cerrados',
    };
    return labels[state] ?? 'Sin eventos programados';
  }

  protected logout(): void {
    this.auth.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected async enableNotifications(): Promise<void> {
    if (!this.notificationsSupported) {
      this.notificationMessage.set('Tu navegador no soporta notificaciones push para esta app.');
      return;
    }

    if (this.notificationsBusy()) {
      return;
    }

    this.notificationsBusy.set(true);

    try {
      let permission = this.notificationPermission();

      if (permission !== 'granted') {
        permission = await Notification.requestPermission();
        this.notificationPermission.set(permission);
      }

      if (permission !== 'granted') {
        this.notificationMessage.set(
          permission === 'denied'
            ? 'Permiso denegado. Actívalo desde ajustes del navegador.'
            : 'Solicitud de permiso pospuesta.',
        );
        return;
      }

      const registration = await navigator.serviceWorker.ready;
      const existingSubscription = await registration.pushManager.getSubscription();
      const subscription =
        existingSubscription ??
        (await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: this.getApplicationServerKey(),
        }));

      await this.persistSubscription(subscription);
      this.notificationMessage.set('Notificaciones push activadas correctamente.');
    } catch {
      this.notificationMessage.set(
        'No se pudieron activar las notificaciones push. Inténtalo de nuevo.',
      );
    } finally {
      this.notificationsBusy.set(false);
    }
  }

  private async persistSubscription(subscription: PushSubscription): Promise<void> {
    await fetch('/api/push_subscriptions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(subscription),
    });
  }

  private getApplicationServerKey(): Uint8Array {
    const publicKey =
      'BElw6xA6o2P7vWQ2UjPjvNiIHTsX4z6s1_bN0L7On2rV7Y5bW6Yb0S9m7mC2fQ2Y0vWkP4vG8oH0aF8q7sM6jCA';
    const normalized = publicKey.replace(/-/g, '+').replace(/_/g, '/');
    const padding = '='.repeat((4 - (normalized.length % 4)) % 4);
    const decoded = atob(`${normalized}${padding}`);

    return Uint8Array.from(decoded, (char) => char.charCodeAt(0));
  }
}
