import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { catchError, map, Observable, of } from 'rxjs';
import { EventosMapper } from './eventos-mapper';

interface HydraCollection<T> {
  'hydra:member': T[];
}

export interface PersonaFamiliarApi {
  id: string;
  nombre: string;
  apellidos: string;
  nombreCompleto: string;
  parentesco: string;
  tipoPersona: 'adulto' | 'infantil';
  observaciones?: string | null;
  inscripcion?: {
    evento: {
      id: string;
      titulo: string;
      fechaEvento: string;
      horaInicio?: string | null;
    };
    estadoPago: string;
  } | null;
}

export interface NoFalleroApi {
  id: string;
  nombre: string;
  apellidos?: string;
  nombreCompleto?: string;
  parentesco?: string;
  tipoPersona: 'adulto' | 'infantil';
  observaciones?: string | null;
  origen?: 'no_fallero' | 'familiar';
  esNoFallero?: boolean;
  inscripcion?: {
    evento: {
      id: string;
      titulo: string;
      fechaEvento: string;
      horaInicio?: string | null;
    };
    estadoPago: string;
  } | null;
}

export interface AltaNoFalleroPayload {
  nombre: string;
  apellidos: string;
  tipoPersona: 'adulto' | 'infantil';
  parentesco?: string;
  observaciones?: string;
}

export interface MenuEventoApi {
  id: string;
  nombre: string;
  descripcion?: string | null;
  franjaComida: 'almuerzo' | 'comida' | 'merienda' | 'cena';
  compatibilidadPersona: 'adulto' | 'infantil' | 'ambos';
  esDePago: boolean;
  precioBase: number;
  activo?: boolean;
}

export interface EventoDetalleApi {
  id: string;
  titulo: string;
  descripcion?: string | null;
  fechaEvento: string;
  horaInicio?: string | null;
  lugar?: string | null;
  estado?: string;
  inscripcionAbierta?: boolean;
  menus: MenuEventoApi[];
}

export interface EventoResumenApi {
  id: string;
  titulo: string;
  descripcion?: string | null;
  fechaEvento: string;
  horaInicio?: string | null;
  lugar?: string | null;
  estado: string;
  inscripcionAbierta?: boolean;
}

export interface InscripcionApi {
  id: string;
  codigo: string;
  evento: {
    id: string;
    titulo: string;
    fechaEvento: string;
    horaInicio?: string | null;
    lugar?: string | null;
    inscripcionAbierta?: boolean;
  };
  estadoInscripcion: string;
  estadoPago: string;
  importeTotal: number;
  importePagado: number;
  lineas: {
    id: string;
    nombrePersonaSnapshot: string;
    nombreMenuSnapshot: string;
    precioUnitario: number;
    estadoLinea: string;
  }[];
}

interface InscripcionResumenApi {
  id: string;
  codigo: string;
  evento: {
    id: string;
  };
}

interface CrearInscripcionResponse {
  id: string;
}

interface NoFalleroStorageEntry extends NoFalleroApi {
  eventId: string;
}

@Injectable({ providedIn: 'root' })
export class EventosApi {
  private readonly http = inject(HttpClient);
  private readonly mapper = inject(EventosMapper);
  private readonly apiBaseUrl = 'http://localhost:8080';
  private readonly noFallerosStorageKey = 'falles:no-falleros';

  getEventos(): Observable<EventoResumenApi[]> {
    return this.http
      .get<HydraCollection<EventoResumenApi>>(`${this.apiBaseUrl}/api/eventos`)
      .pipe(map((response) => response['hydra:member'] ?? []));
  }

  getEvento(id: string): Observable<EventoDetalleApi> {
    return this.http.get<EventoDetalleApi>(`${this.eventoBasePath(id)}`);
  }

  getPersonasMias(): Observable<PersonaFamiliarApi[]> {
    return this.http
      .get<HydraCollection<PersonaFamiliarApi>>(`${this.apiBaseUrl}/api/persona_familiares/mias`)
      .pipe(map((response) => response['hydra:member'] ?? []));
  }

  crearInscripcion(eventoId: string, personas: Array<{ persona: string; menu: string; observaciones?: string }>): Observable<CrearInscripcionResponse> {
    return this.http.post<CrearInscripcionResponse>(`${this.eventoBasePath(eventoId)}/inscribirme`, {
      personas,
    });
  }

  getInscripcionesMias(): Observable<InscripcionResumenApi[]> {
    return this.http
      .get<HydraCollection<InscripcionResumenApi>>(`${this.apiBaseUrl}/api/inscripciones/mias`)
      .pipe(map((response) => response['hydra:member'] ?? []));
  }

  getInscripcion(id: string): Observable<InscripcionApi> {
    return this.http.get<InscripcionApi>(`${this.apiBaseUrl}/api/inscripciones/${id}`);
  }

  cancelarLineaInscripcion(inscripcionId: string, lineaId: string): Observable<unknown> {
    return this.http.post(`${this.apiBaseUrl}/api/inscripciones/${inscripcionId}/lineas/${lineaId}/cancelar`, {});
  }

  getNoFallerosByEvento(eventoId: string): Observable<NoFalleroApi[]> {
    return this.http
      .get<HydraCollection<NoFalleroApi>>(`${this.eventoBasePath(eventoId)}/no_falleros`)
      .pipe(
        map((response) => this.mapper.mapNoFallerosList(response['hydra:member'], eventoId)),
        catchError(() => {
          return this.http
            .get<HydraCollection<NoFalleroApi>>(`${this.eventoBasePath(eventoId)}/participantes_externos`)
            .pipe(
              map((response) => this.mapper.mapNoFallerosList(response['hydra:member'], eventoId)),
              catchError(() => of(this.mapper.mapNoFallerosList(this.readNoFallerosFromFallback(eventoId), eventoId))),
            );
        }),
      );
  }

  altaNoFalleroEnEvento(eventoId: string, payload: AltaNoFalleroPayload): Observable<NoFalleroApi> {
    return this.http
      .post<NoFalleroApi>(`${this.eventoBasePath(eventoId)}/no_falleros`, payload)
      .pipe(
        map((response) => this.mapper.mapNoFalleroCreate(response, eventoId, payload.nombre, payload.apellidos)),
        catchError(() => {
          return this.http
            .post<NoFalleroApi>(`${this.eventoBasePath(eventoId)}/participantes_externos`, payload)
            .pipe(
              map((response) => this.mapper.mapNoFalleroCreate(response, eventoId, payload.nombre, payload.apellidos)),
              catchError(() => of(this.createNoFalleroInFallback(eventoId, payload))),
            );
        }),
      );
  }

  bajaNoFalleroEnEvento(eventoId: string, noFalleroId: string): Observable<void> {
    return this.http
      .delete<unknown>(`${this.eventoBasePath(eventoId)}/no_falleros/${noFalleroId}`)
      .pipe(
        map((response) => this.mapper.mapNoFalleroDelete(response, eventoId, noFalleroId)),
        map(() => void 0),
        catchError(() => {
          return this.http
            .post<unknown>(`${this.eventoBasePath(eventoId)}/no_falleros/${noFalleroId}/baja`, {})
            .pipe(
              map((response) => this.mapper.mapNoFalleroDelete(response, eventoId, noFalleroId)),
              map(() => void 0),
              catchError(() => {
                this.deleteNoFalleroInFallback(eventoId, noFalleroId);
                return of(void 0);
              }),
            );
        }),
      );
  }

  private eventoBasePath(eventoId: string): string {
    return `${this.apiBaseUrl}/api/eventos/${this.normalizeEventoId(eventoId)}`;
  }

  private normalizeEventoId(eventoId: string): string {
    const safeDecode = (() => {
      try {
        return decodeURIComponent(eventoId);
      } catch {
        return eventoId;
      }
    })();

    const cleaned = safeDecode.trim();
    if (cleaned.startsWith('/api/eventos/')) {
      return cleaned.slice('/api/eventos/'.length);
    }

    return cleaned;
  }

  private readNoFallerosFromFallback(eventoId: string): NoFalleroApi[] {
    const entries = this.getNoFallerosStorageEntries();
    return entries
      .filter((entry) => entry.eventId === eventoId)
      .map(({ eventId, ...item }) => item);
  }

  private createNoFalleroInFallback(eventoId: string, payload: AltaNoFalleroPayload): NoFalleroApi {
    const created: NoFalleroStorageEntry = {
      id: `nf-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
      eventId: eventoId,
      nombre: payload.nombre.trim(),
      apellidos: payload.apellidos.trim(),
      nombreCompleto: `${payload.nombre} ${payload.apellidos}`.trim(),
      parentesco: payload.parentesco?.trim() || 'Invitado/a',
      tipoPersona: payload.tipoPersona,
      observaciones: payload.observaciones?.trim() || null,
      origen: 'no_fallero',
      esNoFallero: true,
      inscripcion: null,
    };

    const entries = this.getNoFallerosStorageEntries();
    entries.push(created);
    this.setNoFallerosStorageEntries(entries);

    const { eventId, ...result } = created;
    return result;
  }

  private deleteNoFalleroInFallback(eventoId: string, noFalleroId: string): void {
    const entries = this.getNoFallerosStorageEntries();
    const nextEntries = entries.filter((entry) => {
      return !(entry.eventId === eventoId && String(entry.id) === noFalleroId);
    });
    this.setNoFallerosStorageEntries(nextEntries);
  }

  private getNoFallerosStorageEntries(): NoFalleroStorageEntry[] {
    if (typeof window === 'undefined') {
      return [];
    }

    const raw = window.localStorage.getItem(this.noFallerosStorageKey);
    if (!raw) {
      return [];
    }

    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed as NoFalleroStorageEntry[] : [];
    } catch {
      return [];
    }
  }

  private setNoFallerosStorageEntries(entries: NoFalleroStorageEntry[]): void {
    if (typeof window === 'undefined') {
      return;
    }

    // TODO(backend-shape): retirar fallback localStorage cuando backend exponga endpoint definitivo de no_falleros por evento.
    window.localStorage.setItem(this.noFallerosStorageKey, JSON.stringify(entries));
  }
}
