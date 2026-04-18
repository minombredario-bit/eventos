import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { forkJoin } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { EventCard } from '../../../shared/components/event-card/event-card';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventSummary } from '../../domain/eventos.models';
import { EventosStore } from '../../store/eventos.store';
import { formatDateKey } from '../../../../core/utils/date.utils';
import {CalendarCell} from '../../domain/calendar.models';

const WEEK_DAYS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

@Component({
  selector: 'app-inicio',
  standalone: true,
  imports: [MobileHeader, EventCard, DatePipe],
  templateUrl: './inicio.html',
  styleUrl: './inicio.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Inicio implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  readonly store = inject(EventosStore);

  protected readonly weekDays = WEEK_DAYS;

  protected readonly loading = this.store.loadingEventos;
  protected readonly errorMessage = this.store.errorEventos;
  protected readonly upcomingEvents = this.store.upcomingEvents;

  private readonly today = new Date();

  protected readonly monthCursor = signal(
    new Date(this.today.getFullYear(), this.today.getMonth(), 1),
  );

  protected readonly selectedDateKey = signal(formatDateKey(this.today));

  ngOnInit(): void {
    forkJoin([
      this.store.loadInitialCalendarWindow(this.monthCursor()),
      this.store.loadUpcomingCurrentAndNextMonth(),
    ])
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe();
  }

  protected readonly monthTitle = computed(() =>
    this.monthCursor().toLocaleDateString('es-ES', {
      month: 'long',
      year: 'numeric',
    }),
  );

  protected readonly calendarCells = computed<CalendarCell[]>(() => {
    const monthStart = this.monthCursor();
    const eventsByDate = this.store.eventsByDate();

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

      const key = formatDateKey(date);

      return {
        date,
        key,
        isCurrentMonth: date.getMonth() === month,
        hasEvents: Boolean(eventsByDate[key]?.length),
      };
    });
  });

  protected readonly selectedDayEvents = computed<EventSummary[]>(() =>
    this.store.eventsByDate()[this.selectedDateKey()] ?? [],
  );

  protected readonly selectedDateLabel = computed(() => {
    const cell = this.calendarCells().find(
      (c) => c.key === this.selectedDateKey(),
    );

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
    const current = this.monthCursor();
    const next = new Date(current.getFullYear(), current.getMonth() + diff, 1);

    this.monthCursor.set(next);

    const direction: -1 | 1 = diff < 0 ? -1 : 1;

    this.store
      .loadAdjacentMonth(next, direction)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe();
  }

  protected pickDate(cell: CalendarCell): void {
    this.selectedDateKey.set(cell.key);
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
}
