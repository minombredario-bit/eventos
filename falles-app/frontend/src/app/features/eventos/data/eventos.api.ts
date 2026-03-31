import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { catchError, map, Observable, of } from 'rxjs';
import { EventosMapper } from './eventos.mapper';
import { AuthService } from '../../../core/auth/auth';

interface ApiCollection<T> {
  member: T[];
  'hydra:member'?: T[];
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

export interface AltaInvitadoPayload {
  nombre: string;
  apellidos: string;
  tipoPersona: 'adulto' | 'infantil';
  parentesco?: string;
  observaciones?: string;
}

export interface MenuEventoApi {
  id: string;
  evento?: string | { id?: string };
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
    descripcion?: string | null;
    fechaEvento: string;
    horaInicio?: string | null;
    lugar?: string | null;
    inscripcionAbierta?: boolean;
    fechaLimiteInscripcion?: string | null;
    fechaFinInscripcion?: string | null;
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

export interface InscripcionResumenApi {
  id: string;
  codigo: string;
  evento: { id: string };
}

export interface EventoApuntadoApi {
  inscripcionId: string;
  usuarioId: string;
  nombreUsuario: string;
  estadoInscripcion: string;
  estadoPago: string;
  totalLineas: number;
  importeTotal: number;
}

interface InscripcionResumenCollectionItem {
  id?: string | number;
  '@id'?: string;
  codigo?: string;
  evento?: { id?: string | number; '@id'?: string } | string | null;
}

interface InscripcionLineaCollectionItem {
  id?: string | number;
  '@id'?: string;
  nombrePersonaSnapshot?: string;
  nombreMenuSnapshot?: string;
  precioUnitario?: number | string | null;
  estadoLinea?: string;
}

interface InscripcionCollectionItem {
  id?: string | number;
  '@id'?: string;
  codigo?: string;
    evento?: {
      id?: string | number;
      '@id'?: string;
      titulo?: string;
      descripcion?: string | null;
      fechaEvento?: string;
      horaInicio?: string | null;
      lugar?: string | null;
      inscripcionAbierta?: boolean;
      fechaLimiteInscripcion?: string | null;
      fechaFinInscripcion?: string | null;
    } | string | null;
  estadoInscripcion?: string;
  estadoPago?: string;
  importeTotal?: number | string | null;
  importePagado?: number | string | null;
  lineas?: InscripcionLineaCollectionItem[];
}

export interface CrearInscripcionResponse {
  id: string;
}

export interface RelacionUsuarioApi {
  id: string;
  usuarioOrigen: { id: string; nombre: string; apellidos: string };
  usuarioDestino: { id: string; nombre: string; apellidos: string };
  tipoRelacion: string;
  createdAt: string;
}

export interface ParticipanteSeleccionApi {
  id: string;
  origen: 'familiar' | 'no_fallero';
  nombre?: string;
  apellidos?: string;
  inscripcionRelacion?: {
    id: string;
    codigo: string;
    estadoPago: string;
    lineas: Array<{
      id: string;
      nombreMenuSnapshot: string;
      franjaComidaSnapshot: string;
      estadoLinea: string;
      precioUnitario: number;
    }>;
  };
}

interface SeleccionParticipantesResponseApi {
  eventoId: string;
  participantes: ParticipanteSeleccionApi[];
  updatedAt: string | null;
}

interface NoFalleroStorageEntry extends NoFalleroApi {
  eventId: string;
}

@Injectable({ providedIn: 'root' })
export class EventosApi {
  private readonly http = inject(HttpClient);
  private readonly mapper = inject(EventosMapper);
  private readonly authService = inject(AuthService);
  private readonly apiBaseUrl = 'http://localhost:8080';
  private readonly noFallerosStorageKey = 'asociacion:invitados';

  // ── Eventos ───────────────────────────────────────────────────────────

  getEventos(): Observable<EventoResumenApi[]> {
    return this.http
      .get<ApiCollection<EventoResumenApi>>(`${this.apiBaseUrl}/api/eventos`)
      .pipe(map((r) => r.member ?? []));
  }

  getEvento(id: string): Observable<EventoDetalleApi> {
    return this.http.get<EventoDetalleApi>(this.eventoBasePath(id));
  }

  getMenusByEvento(eventoId: string): Observable<MenuEventoApi[]> {
    return this.http
      .get<ApiCollection<MenuEventoApi>>(`${this.apiBaseUrl}/api/menu_eventos?evento=${encodeURIComponent(eventoId)}`)
      .pipe(map((r) => r.member ?? r['hydra:member'] ?? []));
  }

  // ── Personas ──────────────────────────────────────────────────────────

  getPersonasMias(): Observable<PersonaFamiliarApi[]> {
    return this.http
      .get<ApiCollection<PersonaFamiliarApi>>(`${this.apiBaseUrl}/api/persona_familiares/mias`)
      .pipe(map((r) => r.member ?? []));
  }

  // ── Inscripciones ─────────────────────────────────────────────────────

  crearInscripcion(
    eventoId: string,
    personas: Array<{ persona: string; menu: string; observaciones?: string }>,
  ): Observable<CrearInscripcionResponse> {
    return this.http.post<CrearInscripcionResponse>(
      `${this.eventoBasePath(eventoId)}/inscribirme`,
      { personas },
    );
  }

  getInscripcionesMias(): Observable<InscripcionResumenApi[]> {
    const currentUserId = this.authService.currentUserId;
    if (!currentUserId) {
      return of([]);
    }

    const params = new HttpParams().set('usuario.id', currentUserId.trim());

    return this.http
      .get<ApiCollection<InscripcionResumenCollectionItem>>(`${this.apiBaseUrl}/api/inscripcions`, { params })
      .pipe(
        map((r) => r.member ?? r['hydra:member'] ?? []),
        map((items) => items
          .map((item) => this.toInscripcionResumen(item))
          .filter((item): item is InscripcionResumenApi => item !== null)),
      );
  }

  getInscripcionesMiasCollection(): Observable<InscripcionApi[]> {
    const currentUserId = this.authService.currentUserId;
    if (!currentUserId) {
      return of([]);
    }

    const params = new HttpParams().set('usuario.id', currentUserId.trim());

    return this.http
      .get<ApiCollection<InscripcionCollectionItem>>(`${this.apiBaseUrl}/api/inscripcions`, { params })
      .pipe(
        map((r) => r.member ?? r['hydra:member'] ?? []),
        map((items) => items
          .map((item) => this.toInscripcionCollection(item))
          .filter((item): item is InscripcionApi => item !== null)),
      );
  }

  getInscripcion(id: string): Observable<InscripcionApi> {
    return this.http.get<InscripcionApi>(`${this.apiBaseUrl}/api/inscripcions/${id}`);
  }

  getApuntadosByEvento(eventoId: string, search?: string): Observable<EventoApuntadoApi[]> {
    let params = new HttpParams();
    const query = search?.trim();

    if (query) {
      params = params.set('q', query);
    }

    return this.http
      .get<ApiCollection<EventoApuntadoApi>>(`${this.eventoBasePath(eventoId)}/apuntados`, { params })
      .pipe(map((r) => r.member ?? r['hydra:member'] ?? []));
  }

  cancelarLineaInscripcion(inscripcionId: string, lineaId: string): Observable<unknown> {
    return this.http.post(
      `${this.apiBaseUrl}/api/inscripcions/${inscripcionId}/lineas/${lineaId}/cancelar`,
      {},
    );
  }

  // ── No Falleros ───────────────────────────────────────────────────────

  getNoFallerosByEvento(eventoId: string): Observable<NoFalleroApi[]> {
    return this.http
      .get<ApiCollection<NoFalleroApi>>(`${this.eventoBasePath(eventoId)}/no_falleros`)
      .pipe(
        map((r) => this.mapper.mapNoFallerosList(r.member, eventoId)),
        catchError(() =>
          this.http
            .get<ApiCollection<NoFalleroApi>>(`${this.eventoBasePath(eventoId)}/participantes_externos`)
            .pipe(
              map((r) => this.mapper.mapNoFallerosList(r.member, eventoId)),
              catchError(() =>
                of(this.mapper.mapNoFallerosList(this.readNoFallerosFromFallback(eventoId), eventoId)),
              ),
            ),
        ),
      );
  }

  altaNoFalleroEnEvento(eventoId: string, payload: AltaInvitadoPayload): Observable<NoFalleroApi> {
    return this.http
      .post<NoFalleroApi>(`${this.eventoBasePath(eventoId)}/no_falleros`, payload)
      .pipe(
        map((r) => this.mapper.mapNoFalleroCreate(r, eventoId, payload.nombre, payload.apellidos)),
        catchError(() =>
          this.http
            .post<NoFalleroApi>(`${this.eventoBasePath(eventoId)}/participantes_externos`, payload)
            .pipe(
              map((r) => this.mapper.mapNoFalleroCreate(r, eventoId, payload.nombre, payload.apellidos)),
              catchError(() => of(this.createNoFalleroInFallback(eventoId, payload))),
            ),
        ),
      );
  }

  bajaNoFalleroEnEvento(eventoId: string, noFalleroId: string): Observable<void> {
    return this.http
      .delete<unknown>(`${this.eventoBasePath(eventoId)}/no_falleros/${noFalleroId}`)
      .pipe(
        map((r) => this.mapper.mapNoFalleroDelete(r, eventoId, noFalleroId)),
        map(() => void 0),
        catchError(() =>
          this.http
            .post<unknown>(`${this.eventoBasePath(eventoId)}/no_falleros/${noFalleroId}/baja`, {})
            .pipe(
              map((r) => this.mapper.mapNoFalleroDelete(r, eventoId, noFalleroId)),
              map(() => void 0),
              catchError(() => {
                this.deleteNoFalleroInFallback(eventoId, noFalleroId);
                return of(void 0);
              }),
            ),
        ),
      );
  }

  // ── Relaciones ────────────────────────────────────────────────────────

  getRelacionesByUsuario(usuarioId: string): Observable<RelacionUsuarioApi[]> {
    return this.http
      .get<ApiCollection<RelacionUsuarioApi>>(
        `${this.apiBaseUrl}/api/usuarios/${usuarioId}/relaciones`,
      )
      .pipe(map((r) => r.member ?? []));
  }

  getSeleccionParticipantes(eventoId: string): Observable<ParticipanteSeleccionApi[]> {
    return this.http
      .get<SeleccionParticipantesResponseApi>(`${this.eventoBasePath(eventoId)}/seleccion_participantes`)
      .pipe(map((r) => Array.isArray(r.participantes) ? r.participantes : []));
  }

  guardarSeleccionParticipantes(
    eventoId: string,
    participantes: ParticipanteSeleccionApi[],
  ): Observable<ParticipanteSeleccionApi[]> {
    return this.http
      .put<SeleccionParticipantesResponseApi>(`${this.eventoBasePath(eventoId)}/seleccion_participantes`, {
        participantes,
      })
      .pipe(map((r) => Array.isArray(r.participantes) ? r.participantes : []));
  }

  // ── Privados ──────────────────────────────────────────────────────────

  private eventoBasePath(eventoId: string): string {
    return `${this.apiBaseUrl}/api/eventos/${this.normalizeEventoId(eventoId)}`;
  }

  private normalizeEventoId(eventoId: string): string {
    const safeDecode = (() => {
      try { return decodeURIComponent(eventoId); } catch { return eventoId; }
    })();

    const cleaned = safeDecode.trim();
    return cleaned.startsWith('/api/eventos/')
      ? cleaned.slice('/api/eventos/'.length)
      : cleaned;
  }

  private readNoFallerosFromFallback(eventoId: string): NoFalleroApi[] {
    return this.getNoFallerosStorageEntries()
      .filter((e) => e.eventId === eventoId)
      .map(({ eventId: _eventId, ...item }) => item);
  }

  private createNoFalleroInFallback(eventoId: string, payload: AltaInvitadoPayload): NoFalleroApi {
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

    const { eventId: _eventId, ...result } = created;
    return result;
  }

  private deleteNoFalleroInFallback(eventoId: string, noFalleroId: string): void {
    const next = this.getNoFallerosStorageEntries().filter(
      (e) => !(e.eventId === eventoId && String(e.id) === noFalleroId),
    );
    this.setNoFallerosStorageEntries(next);
  }

  private getNoFallerosStorageEntries(): NoFalleroStorageEntry[] {
    if (typeof window === 'undefined') return [];
    try {
      const parsed = JSON.parse(window.localStorage.getItem(this.noFallerosStorageKey) ?? 'null');
      return Array.isArray(parsed) ? (parsed as NoFalleroStorageEntry[]) : [];
    } catch {
      return [];
    }
  }

  private setNoFallerosStorageEntries(entries: NoFalleroStorageEntry[]): void {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(this.noFallerosStorageKey, JSON.stringify(entries));
  }

  private toInscripcionResumen(item: InscripcionResumenCollectionItem): InscripcionResumenApi | null {
    const inscripcionId = this.extractResourceId(
      item.id ?? item['@id'] ?? '',
      '/api/inscripcions/',
    );
    const eventoId = this.extractResourceId(item.evento, '/api/eventos/');

    if (inscripcionId.length === 0 || eventoId.length === 0) {
      return null;
    }

    return {
      id: inscripcionId,
      codigo: String(item.codigo ?? ''),
      evento: {
        id: eventoId,
      },
    };
  }

  private toInscripcionCollection(item: InscripcionCollectionItem): InscripcionApi | null {
    const inscripcionId = this.extractResourceId(
      item.id ?? item['@id'] ?? '',
      '/api/inscripcions/',
    );

    const eventoPayload = typeof item.evento === 'string'
      ? { '@id': item.evento }
      : (item.evento ?? {});

    const eventoId = this.extractResourceId(eventoPayload, '/api/eventos/');

    if (inscripcionId.length === 0 || eventoId.length === 0) {
      return null;
    }

    return {
      id: inscripcionId,
      codigo: String(item.codigo ?? ''),
      evento: {
        id: eventoId,
        titulo: String(eventoPayload.titulo ?? ''),
        descripcion: eventoPayload.descripcion ?? null,
        fechaEvento: String(eventoPayload.fechaEvento ?? ''),
        horaInicio: eventoPayload.horaInicio ?? null,
        lugar: eventoPayload.lugar ?? null,
        inscripcionAbierta: eventoPayload.inscripcionAbierta,
        fechaLimiteInscripcion: eventoPayload.fechaLimiteInscripcion ?? eventoPayload.fechaFinInscripcion ?? null,
      },
      estadoInscripcion: String(item.estadoInscripcion ?? ''),
      estadoPago: String(item.estadoPago ?? ''),
      importeTotal: this.toNumber(item.importeTotal),
      importePagado: this.toNumber(item.importePagado),
      lineas: Array.isArray(item.lineas)
        ? item.lineas.map((linea) => ({
          id: this.extractResourceId(linea.id ?? linea['@id'] ?? '', '/api/inscripcion_lineas/') || String(linea.id ?? ''),
          nombrePersonaSnapshot: String(linea.nombrePersonaSnapshot ?? ''),
          nombreMenuSnapshot: String(linea.nombreMenuSnapshot ?? ''),
          precioUnitario: this.toNumber(linea.precioUnitario),
          estadoLinea: String(linea.estadoLinea ?? ''),
        }))
        : [],
    };
  }

  private extractResourceId(
    resource: { id?: string | number; '@id'?: string } | string | number | null | undefined,
    prefix: string,
  ): string {
    if (typeof resource === 'number') {
      return String(resource);
    }

    if (typeof resource === 'string') {
      return this.normalizeResourceValue(resource, prefix);
    }

    const id = resource?.id ?? resource?.['@id'];
    if (id === undefined || id === null) {
      return '';
    }

    return this.normalizeResourceValue(String(id), prefix);
  }

  private normalizeResourceValue(value: string, prefix: string): string {
    const normalized = this.safeDecode(value).trim();
    if (!normalized.length) {
      return '';
    }

    if (normalized.startsWith(prefix)) {
      return normalized.slice(prefix.length).trim();
    }

    if (normalized.startsWith('/')) {
      const prefixWithoutSlash = prefix.startsWith('/') ? prefix.slice(1) : prefix;
      if (normalized.startsWith(`/${prefixWithoutSlash}`)) {
        return normalized.slice(prefixWithoutSlash.length + 1).trim();
      }
    }

    return normalized;
  }

  private safeDecode(value: string): string {
    try {
      return decodeURIComponent(value);
    } catch {
      return value;
    }
  }

  private toNumber(value: number | string | null | undefined): number {
    if (typeof value === 'number') {
      return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string') {
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : 0;
    }

    return 0;
  }
}
