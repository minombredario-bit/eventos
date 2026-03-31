import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, catchError, map, of, tap } from 'rxjs';
import {
  AltaInvitadoPayload,
  EventoResumenApi,
  EventosApi,
  NoFalleroApi,
  ParticipanteSeleccionApi,
} from '../data/eventos.api';
import { EventosMapper } from '../data/eventos.mapper';
import { EventSummary, FamilyMember } from '../domain/eventos.models';
import { formatDateKey, hasValidTime, normalizeDateKey } from '../../../core/utils/date.utils';

@Injectable({ providedIn: 'root' })
export class EventosStore {
  private readonly api = inject(EventosApi);
  private readonly mapper = inject(EventosMapper);

  // ── Estado ────────────────────────────────────────────────────────────
  readonly events         = signal<EventSummary[]>([]);
  readonly upcomingEvents = signal<EventSummary[]>([]);
  readonly loadingEventos = signal(false);
  readonly errorEventos   = signal<string | null>(null);

  readonly members        = signal<FamilyMember[]>([]);
  readonly loadingMembers = signal(false);
  readonly errorMembers   = signal<string | null>(null);

  // ── Derivados ─────────────────────────────────────────────────────────
  readonly eventsByDate = computed(() =>
    this.events().reduce((acc, event) => {
      const key = normalizeDateKey(event.date);
      acc[key] = [...(acc[key] ?? []), event];
      return acc;
    }, {} as Record<string, EventSummary[]>),
  );

  // ── Acciones: Eventos ─────────────────────────────────────────────────

  loadEventos(): Observable<void> {
    this.loadingEventos.set(true);
    this.errorEventos.set(null);

    return this.api.getEventos().pipe(
      tap((eventos) => {
        const now = new Date();
        const sorted = [...eventos].sort((a, b) => this.compareByDateTime(a, b));

        this.events.set(sorted.map((e) => this.mapper.toEventSummary(e)));
        this.upcomingEvents.set(
          sorted
            .filter((e) => !this.isPast(e, now) && this.isWithinNextMonth(e, now))
            .map((e) => this.mapper.toEventSummary(e)),
        );
        this.loadingEventos.set(false);
      }),
      map(() => void 0),
      catchError(() => {
        this.errorEventos.set('No pudimos cargar los eventos. Reintentá en unos segundos.');
        this.events.set([]);
        this.upcomingEvents.set([]);
        this.loadingEventos.set(false);
        return of(void 0);
      }),
    );
  }

  // ── Acciones: Personas ────────────────────────────────────────────────

  loadPersonasMias(): Observable<FamilyMember[]> {
    this.loadingMembers.set(true);
    this.errorMembers.set(null);

    return this.api.getPersonasMias().pipe(
      tap((personas) => {
        this.members.set(personas.map((p) => this.mapper.toFamilyMember(p)));
        this.loadingMembers.set(false);
      }),
      map((personas) => personas.map((p) => this.mapper.toFamilyMember(p))),
      catchError(() => {
        this.errorMembers.set('No pudimos cargar tus familiares.');
        this.members.set([]);
        this.loadingMembers.set(false);
        return of([]);
      }),
    );
  }

  // ── Acciones: No Falleros ─────────────────────────────────────────────

  getNoFallerosByEvento(eventoId: string): Observable<NoFalleroApi[]> {
    return this.api.getNoFallerosByEvento(eventoId);
  }

  getSeleccionParticipantes(eventoId: string): Observable<ParticipanteSeleccionApi[]> {
    return this.api.getSeleccionParticipantes(eventoId);
  }

  altaNoFalleroEnEvento(eventoId: string, payload: AltaInvitadoPayload): Observable<NoFalleroApi> {
    return this.api.altaNoFalleroEnEvento(eventoId, payload);
  }

  bajaNoFalleroEnEvento(eventoId: string, noFalleroId: string): Observable<void> {
    return this.api.bajaNoFalleroEnEvento(eventoId, noFalleroId);
  }

  // ── Privados: ordenación y filtrado ───────────────────────────────────

  private isPast(evento: EventoResumenApi, now: Date): boolean {
    if (hasValidTime(evento.horaInicio)) {
      return this.toDateTime(evento).getTime() < now.getTime();
    }
    return normalizeDateKey(evento.fechaEvento) < formatDateKey(now);
  }

  private isWithinNextMonth(evento: EventoResumenApi, now: Date): boolean {
    const max = new Date(now);
    max.setMonth(max.getMonth() + 1);
    if (hasValidTime(evento.horaInicio)) {
      return this.toDateTime(evento).getTime() <= max.getTime();
    }
    return normalizeDateKey(evento.fechaEvento) <= formatDateKey(max);
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
