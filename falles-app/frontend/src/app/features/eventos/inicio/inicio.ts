import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { AuthService } from '../../../core/auth/auth';
import { EventCard } from '../../shared/components/event-card/event-card';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { UPCOMING_EVENTS } from '../data/mock';
import { EventSummary } from '../models/ui';

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
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly weekDays = WEEK_DAYS;
  protected readonly events = UPCOMING_EVENTS;

  private readonly today = new Date();
  protected readonly monthCursor = signal(new Date(this.today.getFullYear(), this.today.getMonth(), 1));
  protected readonly selectedDateKey = signal(this.formatDateKey(this.today));

  private readonly eventsByDate = computed(() => {
    return UPCOMING_EVENTS.reduce(
      (acc, event) => {
        const key = event.date;
        acc[key] = [...(acc[key] ?? []), event];
        return acc;
      },
      {} as Record<string, EventSummary[]>
    );
  });

  protected readonly monthTitle = computed(() => {
    const month = this.monthCursor();
    return month.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });
  });

  protected readonly calendarCells = computed(() => {
    const monthStart = this.monthCursor();
    const year = monthStart.getFullYear();
    const month = monthStart.getMonth();
    const firstDay = new Date(year, month, 1);
    const offset = (firstDay.getDay() + 6) % 7;
    const gridStart = new Date(year, month, 1 - offset);

    return Array.from({ length: 42 }, (_, index) => {
      const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index);

      return {
        date,
        key: this.formatDateKey(date),
        isCurrentMonth: date.getMonth() === month,
      };
    }) satisfies CalendarCell[];
  });

  protected readonly selectedDayEvents = computed(() => {
    return this.eventsByDate()[this.selectedDateKey()] ?? [];
  });

  protected readonly selectedDateLabel = computed(() => {
    const selected = this.calendarCells().find((cell) => cell.key === this.selectedDateKey())?.date;
    const date = selected ?? this.today;
    return date.toLocaleDateString('es-ES', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    });
  });

  protected readonly selectedDayState = computed(() => {
    const statuses = this.selectedDayEvents().map((event) => event.status);
    if (!statuses.length) {
      return 'sin-eventos';
    }

    if (statuses.some((status) => status === 'abierto')) {
      return 'abierto';
    }

    if (statuses.some((status) => status === 'ultimas_plazas')) {
      return 'ultimas_plazas';
    }

    return 'cerrado';
  });

  protected moveMonth(diff: number): void {
    const current = this.monthCursor();
    this.monthCursor.set(new Date(current.getFullYear(), current.getMonth() + diff, 1));
  }

  protected pickDate(cell: CalendarCell): void {
    this.selectedDateKey.set(cell.key);
  }

  protected hasEvents(key: string): boolean {
    return Boolean(this.eventsByDate()[key]?.length);
  }

  protected isSelected(key: string): boolean {
    return this.selectedDateKey() === key;
  }

  protected dayStateLabel(state: string): string {
    if (state === 'abierto') {
      return 'Con inscripción abierta';
    }

    if (state === 'ultimas_plazas') {
      return 'Con últimas plazas';
    }

    if (state === 'cerrado') {
      return 'Solo eventos cerrados';
    }

    return 'Sin eventos programados';
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  private formatDateKey(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
  }
}
