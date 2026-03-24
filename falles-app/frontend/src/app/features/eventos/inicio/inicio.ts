import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { EventCard } from '../../shared/components/event-card/event-card';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { EventSummary } from '../models/ui';
import { EventoResumenApi, EventosApi } from '../services/eventos-api';

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
  private readonly eventosApi = inject(EventosApi);

  protected readonly weekDays = WEEK_DAYS;
  protected readonly events = signal<EventSummary[]>([]);          // todos — calendario
  protected readonly upcomingEvents = signal<EventSummary[]>([]);  // filtrados — próximos
  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);

  private readonly today = new Date();
  protected readonly monthCursor = signal(new Date(this.today.getFullYear(), this.today.getMonth(), 1));
  protected readonly selectedDateKey = signal(this.formatDateKey(this.today));

  constructor() {
    void this.loadEventos();
  }

  private readonly eventsByDate = computed(() => {
    const map = this.events().reduce(
      (acc, event) => {
        const key = this.normalizeApiDateKey(event.date);
        console.log('evento:', event.title, '| date raw:', event.date, '| key:', key);
        acc[key] = [...(acc[key] ?? []), event];
        return acc;
      },
      {} as Record<string, EventSummary[]>
    );
    console.log('eventsByDate keys:', Object.keys(map));
    return map;
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

  private async loadEventos(): Promise<void> {
    this.loading.set(true);
    this.errorMessage.set(null);

    try {
      const eventos = await firstValueFrom(this.eventosApi.getEventos());
      const now = new Date();

      const allSummaries = eventos
        .map((evento) => this.toEventSummary(evento));

      const upcomingSummaries = eventos  // ← sobre el array original, no sobre allSummaries
        .filter((evento) => !this.isPastEvent(evento, now) && this.isWithinNextMonth(evento, now))
        .map((evento) => this.toEventSummary(evento));

      this.events.set(allSummaries);
      this.upcomingEvents.set(upcomingSummaries);

    } catch {
      this.errorMessage.set('No pudimos cargar los eventos. Reintentá en unos segundos.');
      this.events.set([]);
      this.upcomingEvents.set([]);
    } finally {
      this.loading.set(false);
    }
  }

  private toEventSummary(evento: EventoResumenApi): EventSummary {
    return {
      id: evento.id,
      title: evento.titulo,
      date: evento.fechaEvento,
      time: evento.horaInicio ?? 'Sin hora',
      location: evento.lugar ?? 'Lugar por confirmar',
      status: this.toUiStatus(evento),
      description: evento.descripcion ?? 'Sin descripción disponible.',
    };
  }

  private toUiStatus(evento: EventoResumenApi): EventSummary['status'] {
    if (evento.inscripcionAbierta) {
      return 'abierto';
    }

    if (evento.estado === 'publicado') {
      return 'ultimas_plazas';
    }

    return 'cerrado';
  }

  private formatDateKey(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  private normalizeApiDateKey(rawDate: string): string {
    return rawDate.includes('T') ? rawDate.slice(0, 10) : rawDate;
  }

  private isPastEvent(evento: EventoResumenApi, now: Date): boolean {
    if (this.hasValidTime(evento.horaInicio)) {
      return this.getEventStartDateTime(evento).getTime() < now.getTime();
    }

    const todayKey = this.formatDateKey(now);
    return this.normalizeApiDateKey(evento.fechaEvento) < todayKey;
  }

  private isWithinNextMonth(evento: EventoResumenApi, now: Date): boolean {
    const maxDate = new Date(now);
    maxDate.setMonth(maxDate.getMonth() + 1);

    if (this.hasValidTime(evento.horaInicio)) {
      return this.getEventStartDateTime(evento).getTime() <= maxDate.getTime();
    }

    const maxDateKey = this.formatDateKey(maxDate);
    return this.normalizeApiDateKey(evento.fechaEvento) <= maxDateKey;
  }

  private compareEventsByDateTime(a: EventoResumenApi, b: EventoResumenApi): number {
    const dateA = this.normalizeApiDateKey(a.fechaEvento);
    const dateB = this.normalizeApiDateKey(b.fechaEvento);

    if (dateA !== dateB) {
      return dateA.localeCompare(dateB);
    }

    const aHasTime = this.hasValidTime(a.horaInicio);
    const bHasTime = this.hasValidTime(b.horaInicio);

    if (aHasTime && bHasTime) {
      return (a.horaInicio ?? '').localeCompare(b.horaInicio ?? '');
    }

    if (aHasTime !== bHasTime) {
      return aHasTime ? -1 : 1;
    }

    return a.titulo.localeCompare(b.titulo);
  }

  private getEventStartDateTime(evento: EventoResumenApi): Date {
    const normalizedDate = this.normalizeApiDateKey(evento.fechaEvento);
    const [yearRaw, monthRaw, dayRaw] = normalizedDate.split('-');

    const year = Number(yearRaw);
    const month = Number(monthRaw);
    const day = Number(dayRaw);

    if (!this.hasValidTime(evento.horaInicio)) {
      return new Date(year, month - 1, day, 0, 0, 0, 0);
    }

    const [hoursRaw, minutesRaw] = (evento.horaInicio ?? '').split(':');
    const hours = Number(hoursRaw);
    const minutes = Number(minutesRaw);

    return new Date(year, month - 1, day, hours, minutes, 0, 0);
  }

  private hasValidTime(time?: string | null): boolean {
    return typeof time === 'string' && /^\d{2}:\d{2}/.test(time);
  }
}
