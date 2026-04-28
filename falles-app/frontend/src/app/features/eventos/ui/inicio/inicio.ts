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
    typeof window !== 'undefined' && 'Notification' in window;
  protected readonly notificationPermission = signal<NotificationPermission>(
    this.notificationsSupported ? Notification.permission : 'denied',
  );
  protected readonly notificationMessage = signal('');

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

  protected enableNotifications(): void {
    if (!this.notificationsSupported) {
      this.notificationMessage.set('Tu navegador no soporta notificaciones push.');
      return;
    }

    if (this.notificationPermission() === 'granted') {
      this.showActivationNotification();
      this.notificationMessage.set('Notificaciones ya activas en este dispositivo.');
      return;
    }

    void Notification.requestPermission().then((permission) => {
      this.notificationPermission.set(permission);

      if (permission === 'granted') {
        this.showActivationNotification();
        this.notificationMessage.set('Notificaciones activadas correctamente.');
        return;
      }

      if (permission === 'denied') {
        this.notificationMessage.set(
          'Notificaciones bloqueadas. Puedes activarlas desde los ajustes del navegador.',
        );
        return;
      }

      this.notificationMessage.set('Solicitud de notificaciones pospuesta.');
    });
  }

  private showActivationNotification(): void {
    try {
      new Notification('Entidades Festivas', {
        body: 'Ya tienes activadas las notificaciones push.',
      });
    } catch {
      this.notificationMessage.set(
        'Permiso concedido, pero no se pudo mostrar la notificación de prueba.',
      );
    }
  }
}
