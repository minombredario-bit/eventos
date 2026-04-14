import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, catchError, finalize, map, of, tap } from 'rxjs';
import {
  AltaInvitadoPayload,
  EventoResumenApi,
  EventosApi,
  InvitadoApi,
  ParticipanteSeleccionApi,
} from '../data/eventos.api';
import { EventosMapper } from '../data/eventos.mapper';
import { EventSummary, FamilyMember } from '../domain/eventos.models';
import { formatDateKey, hasValidTime, normalizeDateKey } from '../../../core/utils/date.utils';
import { AuthService } from '../../../core/auth/auth';

@Injectable({ providedIn: 'root' })
export class EventosStore {
  private readonly api = inject(EventosApi);
  private readonly mapper = inject(EventosMapper);
  private readonly authService = inject(AuthService);
  private readonly loadedMonths = new Set<string>();
  private readonly inFlightMonths = new Set<string>();
  private readonly rawEvents = signal<EventoResumenApi[]>([]);

  // ── Estado ────────────────────────────────────────────────────────────
  readonly events         = signal<EventSummary[]>([]);
  readonly upcomingEvents = signal<EventSummary[]>([]);
  readonly loadingEventos = signal(false);
  readonly errorEventos   = signal<string | null>(null);

  readonly members        = signal<FamilyMember[]>([]);
  readonly loadingMembers = signal(false);
  readonly errorMembers   = signal<string | null>(null);

  // ── Derivados ─────────────────────────────────────────────────────────
  readonly eventsByDate = computed(() => {
    const raw = this.rawEvents();

    return raw.reduce((acc, evento) => {
      const key = normalizeDateKey(evento.fechaEvento);
      const mapped = this.mapper.toEventSummary(evento);
      acc[key] = [...(acc[key] ?? []), mapped];
      return acc;
    }, {} as Record<string, EventSummary[]>);
  });

  // ── Acciones: Eventos ─────────────────────────────────────────────────
  loadInitialCalendarWindow(referenceDate: Date): Observable<void> {
    const previousMonth = this.addMonths(referenceDate, -1);
    const nextMonth = this.addMonths(referenceDate, 2);

    const { startDate } = this.toMonthRange(previousMonth);
    const { endDate } = this.toMonthRange(nextMonth);

    const monthsToMark = [
      this.toMonthKey(previousMonth),
      this.toMonthKey(referenceDate),
      this.toMonthKey(nextMonth),
    ];

    this.errorEventos.set(null);
    // FIX: loading siempre activo al iniciar, sin condición
    this.loadingEventos.set(true);

    return this.api.getEventosByDateRange(startDate, endDate).pipe(
      map((eventos) => [...eventos].sort((a, b) => this.compareByDateTime(a, b))),
      tap((sorted) => {
        monthsToMark.forEach((key) => this.loadedMonths.add(key));
        this.rawEvents.set(sorted);
        this.events.set(sorted.map((e) => this.mapper.toEventSummary(e)));
      }),
      map(() => void 0),
      catchError((err) => {
        this.errorEventos.set('No pudimos cargar el calendario. Reintentá en unos segundos.');
        this.rawEvents.set([]);
        this.events.set([]);
        return of(void 0);
      }),
      finalize(() => {
        // FIX: siempre desactivar loading, sin condición sobre upcomingEvents
        this.loadingEventos.set(false);
      }),
    );
  }

  loadUpcomingCurrentAndNextMonth(): Observable<void> {
    const referenceDate = new Date();

    const startDate = formatDateKey(referenceDate);
    const endOfSecondNextMonth = new Date(
      referenceDate.getFullYear(),
      referenceDate.getMonth() + 3,
      0,
    );
    const endDate = formatDateKey(endOfSecondNextMonth);

    this.errorEventos.set(null);

    return this.api.getEventosByDateRange(startDate, endDate).pipe(
      map((eventos) => [...eventos].sort((a, b) => this.compareByDateTime(a, b))),
      tap((sorted) => {
        const now = new Date();
        const upcoming = sorted
          .filter((e) => !this.isPast(e, now))
          .map((e) => this.mapper.toEventSummary(e));

        this.upcomingEvents.set(upcoming);
      }),
      map(() => void 0),
      catchError(() => {
        this.errorEventos.set('No pudimos cargar los eventos próximos. Reintentá en unos segundos.');
        this.upcomingEvents.set([]);
        return of(void 0);
      }),
    );
  }

  loadAdjacentMonth(viewedMonth: Date, direction: -1 | 1): Observable<void> {
    return this.loadMonths([this.addMonths(viewedMonth, direction)]);
  }

  // ── Acciones: Personas ────────────────────────────────────────────────

  loadPersonasMias(): Observable<FamilyMember[]> {
    const currentUserId = this.authService.currentUserId?.trim();
    if (!currentUserId) {
      this.members.set([]);
      return of([]);
    }

    this.loadingMembers.set(true);
    this.errorMembers.set(null);

    return this.api.getRelacionesByUsuario(currentUserId).pipe(
      map((relaciones) => relaciones
        .map((relacion) => this.mapper.toFamilyMemberFromRelacion(relacion, currentUserId))
        .filter((member): member is FamilyMember => member !== null)),
      tap((members) => {
        this.members.set(members);
        this.loadingMembers.set(false);
      }),
      catchError(() => {
        this.errorMembers.set('No pudimos cargar tus familiares.');
        this.members.set([]);
        this.loadingMembers.set(false);
        return of([]);
      }),
    );
  }

  // ── Acciones: Invitados ───────────────────────────────────────────────

  getInvitadosByEvento(eventoId: string): Observable<InvitadoApi[]> {
    return this.api.getInvitadosByEvento(eventoId);
  }

  getSeleccionParticipantes(eventoId: string): Observable<ParticipanteSeleccionApi[]> {
    return this.api.getSeleccionParticipantes(eventoId);
  }

  altaInvitadoEnEvento(eventoId: string, payload: AltaInvitadoPayload): Observable<InvitadoApi> {
    return this.api.altaInvitadoEnEvento(eventoId, payload);
  }

  bajaInvitadoEnEvento(eventoId: string, invitadoId: string): Observable<void> {
    return this.api.bajaInvitadoEnEvento(eventoId, invitadoId);
  }

  // ── Privados ──────────────────────────────────────────────────────────

  private isPast(evento: EventoResumenApi, now: Date): boolean {
    if (hasValidTime(evento.horaInicio)) {
      return this.toDateTime(evento).getTime() < now.getTime();
    }
    return normalizeDateKey(evento.fechaEvento) < formatDateKey(now);
  }

  private loadMonths(months: Date[]): Observable<void> {
    const targets = months
      .map((monthDate) => ({
        monthDate,
        key: this.toMonthKey(monthDate),
        ...this.toMonthRange(monthDate),
      }))
      .filter((target, index, arr) =>
        arr.findIndex((item) => item.key === target.key) === index
        && !this.loadedMonths.has(target.key)
        && !this.inFlightMonths.has(target.key));

    if (targets.length === 0) {
      return of(void 0);
    }

    this.errorEventos.set(null);
    targets.forEach((target) => this.inFlightMonths.add(target.key));

    const target = targets[0];

    return this.api.getEventosByDateRange(target.startDate, target.endDate).pipe(
      map((eventos) => ({ key: target.key, eventos, success: true as const })),
      catchError(() => of({ key: target.key, eventos: [] as EventoResumenApi[], success: false as const })),
      tap((result) => {
        if (!result.success) {
          this.errorEventos.set('No pudimos cargar algunos meses del calendario. Reintentá en unos segundos.');
          return;
        }

        this.loadedMonths.add(result.key);

        const merged = new Map(this.rawEvents().map((evento) => [evento.id, evento]));
        result.eventos.forEach((evento) => merged.set(evento.id, evento));

        const sorted = [...merged.values()].sort((a, b) => this.compareByDateTime(a, b));
        this.rawEvents.set(sorted);
        this.events.set(sorted.map((e) => this.mapper.toEventSummary(e)));
      }),
      map(() => void 0),
      finalize(() => {
        targets.forEach((t) => this.inFlightMonths.delete(t.key));
        this.loadingEventos.set(false);
      }),
    );
  }

  private isWithinNextMonth(evento: EventoResumenApi, now: Date): boolean {
    const max = new Date(now);
    max.setMonth(max.getMonth() + 1);
    if (hasValidTime(evento.horaInicio)) {
      return this.toDateTime(evento).getTime() <= max.getTime();
    }
    return normalizeDateKey(evento.fechaEvento) <= formatDateKey(max);
  }

  private toMonthKey(date: Date): string {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    return `${year}-${month}`;
  }

  private toMonthRange(date: Date): { startDate: string; endDate: string } {
    const year = date.getFullYear();
    const month = date.getMonth();
    return {
      startDate: formatDateKey(new Date(year, month, 1)),
      endDate: formatDateKey(new Date(year, month + 1, 0)),
    };
  }

  private addMonths(baseDate: Date, diff: number): Date {
    return new Date(baseDate.getFullYear(), baseDate.getMonth() + diff, 1);
  }

  private compareByDateTime(a: EventoResumenApi, b: EventoResumenApi): number {
    const dateA = normalizeDateKey(a.fechaEvento);
    const dateB = normalizeDateKey(b.fechaEvento);
    if (dateA !== dateB) return dateA.localeCompare(dateB);

    const aHasTime = hasValidTime(a.horaInicio);
    const bHasTime = hasValidTime(b.horaInicio);
    if (aHasTime && bHasTime) return (a.horaInicio ?? '').localeCompare(b.horaInicio ?? '');
    if (aHasTime !== bHasTime) return aHasTime ? -1 : 1;
    return a.titulo.localeCompare(b.titulo);
  }

  private toDateTime(evento: EventoResumenApi): Date {
    const [y, m, d] = normalizeDateKey(evento.fechaEvento).split('-').map(Number);
    if (!hasValidTime(evento.horaInicio)) return new Date(y, m - 1, d);
    const [h, min] = (evento.horaInicio ?? '').split(':').map(Number);
    return new Date(y, m - 1, d, h, min);
  }
}
