import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpHeaders, HttpParams } from '@angular/common/http';
import { catchError, map, Observable, of, switchMap, throwError } from 'rxjs';
import { EventosMapper } from './eventos.mapper';
import { AuthService } from '../../../core/auth/auth';
import {
  ActividadEvento,
  AltaInvitadoPayload,
  EventoApuntadosResponse,
  EventoDetalle,
  EventoResumen,
  Inscripcion,
  InscripcionResumen,
  Invitado,
  MetodoPago,
  ParticipanteSeleccion,
  RelacionUsuario,
} from '../domain/eventos.models';

import {
  ApiCollection,
  CrearInscripcionResponse,
  EventoApuntadosCollectionResponse,
  InscripcionCollectionItem,
  InscripcionResumenCollectionItem,
  InvitadoStorageEntry,
  RelacionUsuarioCollectionItem,
  SeleccionParticipantesResponseApi,
} from '../domain/eventos.api.models';

@Injectable({ providedIn: 'root' })
export class EventosApi {
  private readonly http = inject(HttpClient);
  private readonly mapper = inject(EventosMapper);
  private readonly authService = inject(AuthService);
  private readonly apiBaseUrl = 'http://localhost:8080';
  private readonly invitadosStorageKey = 'asociacion:invitados';

  // ── Eventos ───────────────────────────────────────────────────────────

  getEventosByDateRange(startDate?: string, endDate?: string): Observable<EventoResumen[]> {
    let params = new HttpParams();
    if (startDate) {
      params = params.set('fechaEvento[after]', startDate);
    }
    if (endDate) {
      params = params.set('fechaEvento[before]', endDate);
    }

    params = params
      .set('order[fechaEvento]', 'asc')
      .set('order[horaInicio]', 'asc');

    return this.http
      .get<ApiCollection<EventoResumen>>(`${this.apiBaseUrl}/api/eventos`, { params })
      .pipe(map((r) => r.member ?? r['hydra:member'] ?? []));
  }

  getEvento(id: string): Observable<EventoDetalle> {
    return this.http
      .get<EventoDetalle & { actividades?: ActividadEvento[] }>(this.eventoBasePath(id))
      .pipe(map((evento) => this.normalizeEventoDetalle(evento)));
  }

  // Fallback legacy: usar solo cuando GET /api/eventos/{id} no incluya actividades embebidas.
  getActividadesByEvento(eventoId: string): Observable<ActividadEvento[]> {
    return this.http
      .get<ApiCollection<ActividadEvento>>(`${this.apiBaseUrl}/api/actividades?evento=${encodeURIComponent(eventoId)}`)
      .pipe(map((r) => r.member ?? r['hydra:member'] ?? []));
  }

  // ── Inscripciones ─────────────────────────────────────────────────────

  crearInscripcion(
    eventoId: string,
    personas: Array<{ usuario: string; actividad: string; observaciones?: string }>,
  ): Observable<CrearInscripcionResponse> {
    return this.http.post<CrearInscripcionResponse>(
      `${this.eventoBasePath(eventoId)}/inscribirme`,
      { personas },
    );
  }

  getInscripcionesMias(): Observable<InscripcionResumen[]> {
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
          .filter((item): item is InscripcionResumen => item !== null)),
      );
  }

  getInscripcionesMiasCollection(): Observable<Inscripcion[]> {
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
          .filter((item): item is Inscripcion => item !== null)),
      );
  }

  getInscripcion(id: string): Observable<Inscripcion> {
    return this.http
      .get<InscripcionCollectionItem>(`${this.apiBaseUrl}/api/inscripcions/${id}`)
      .pipe(
        map((item) => this.toInscripcionCollection(item)),
        map((item) => {
          if (item === null) {
            throw new Error('No se pudo normalizar la inscripción solicitada.');
          }

          return item;
        }),
      );
  }

  getApuntadosByEvento(
    eventoId: string,
    options?: { search?: string; paginate?: boolean; page?: number },
  ): Observable<EventoApuntadosResponse> {
    let params = new HttpParams();
    const query = options?.search?.trim();
    const paginate = options?.paginate ?? true;
    const page = options?.page ?? 1;

    if (query) {
      params = params.set('q', query);
    }

    params = params
      .set('paginate', String(paginate))
      .set('page', String(Math.max(1, page)));

    return this.http
      .get<EventoApuntadosCollectionResponse>(`${this.eventoBasePath(eventoId)}/apuntados`, { params })
      .pipe(map((r) => {
        const apuntados = (r.member ?? r['hydra:member'] ?? []).map((item) => ({
          inscripcionId: String(item.inscripcionId ?? ''),
          nombreCompleto: String(item.nombreCompleto ?? '').trim(),
          opciones: Array.isArray(item.opciones)
            ? item.opciones.map((opcion) => String(opcion).trim()).filter(Boolean)
            : [],
        }));

        return {
          evento: {
            id: String(r.evento?.id ?? this.normalizeEventoId(eventoId)),
            titulo: String(r.evento?.titulo ?? 'Evento').trim() || 'Evento',
            descripcion: typeof r.evento?.descripcion === 'string'
              ? r.evento.descripcion.trim()
              : null,
            fechaEvento: String(r.evento?.fechaEvento ?? '').trim(),
          },
          apuntados,
          totalItems: Number(r['hydra:totalItems'] ?? apuntados.length),
          currentPage: Number(r['hydra:currentPage'] ?? 1),
          itemsPerPage: Number(r['hydra:itemsPerPage'] ?? apuntados.length),
        };
      }));
  }

  cancelarLineaInscripcion(inscripcionId: string, lineaId: string): Observable<unknown> {
    void inscripcionId;
    return this.http.delete(
      `${this.apiBaseUrl}/api/inscripcion_lineas/${encodeURIComponent(lineaId)}`,
    );
  }

  actualizarActividadLineaInscripcion(lineaId: string, actividadId: string): Observable<unknown> {
    return this.http.patch(
      `${this.apiBaseUrl}/api/inscripcion_lineas/${encodeURIComponent(lineaId)}`,
      { actividad: `/api/actividades/${actividadId}` },
      {
        headers: new HttpHeaders({ 'Content-Type': 'application/merge-patch+json' }),
      },
    );
  }

  actualizarFormaPagoPreferida(formaPagoPreferida: MetodoPago | null): Observable<unknown> {
    return this.http.patch(`${this.apiBaseUrl}/api/me`, { formaPagoPreferida });
  }

  // ── Invitados ─────────────────────────────────────────────────────────

  getInvitadosByEvento(eventoId: string): Observable<Invitado[]> {
    return this.http
      .get<ApiCollection<Invitado>>(`${this.eventoBasePath(eventoId)}/invitados`)
      .pipe(
        map((r) => this.mapper.mapInvitadosList(r.member ?? r['hydra:member'] ?? [], eventoId)),
        catchError(() =>
          of(this.mapper.mapInvitadosList(this.readInvitadosFromFallback(eventoId), eventoId)),
        ),
      );
  }

  altaInvitadoEnEvento(eventoId: string, payload: AltaInvitadoPayload): Observable<Invitado> {
    const currentUserId = this.authService.currentUserId;
    if (!currentUserId) {
      return of(this.createInvitadoInFallback(eventoId, payload));
    }

    return this.http
      .post<Invitado>(`${this.apiBaseUrl}/api/invitados`, {
        ...payload,
        creadoPor: `/api/usuarios/${currentUserId.trim()}`,
        evento: `/api/eventos/${this.normalizeEventoId(eventoId)}`,
      })
      .pipe(
        map((r) => this.mapper.mapInvitadoCreate(r, eventoId, payload.nombre, payload.apellidos)),
        catchError((error: unknown) => {
          if (this.shouldCreateInvitadoFallback(error)) {
            return of(this.createInvitadoInFallback(eventoId, payload));
          }

          return throwError(() => error);
        }),
      );
  }

  bajaInvitadoEnEvento(eventoId: string, invitadoId: string): Observable<void> {
    return this.http
      .delete<unknown>(`${this.apiBaseUrl}/api/invitados/${encodeURIComponent(invitadoId)}`)
      .pipe(
        map((r) => this.mapper.mapInvitadoDelete(r, eventoId, invitadoId)),
        map(() => void 0),
        catchError(() => {
          this.deleteInvitadoInFallback(eventoId, invitadoId);
          return of(void 0);
        }),
      );
  }

  getSeleccionParticipantes(eventoId: string): Observable<ParticipanteSeleccion[]> {
    return this.http
      .get<SeleccionParticipantesResponseApi>(`${this.eventoBasePath(eventoId)}/seleccion_participantes`)
      .pipe(
        map((r) => (Array.isArray(r.participantes) ? r.participantes : [])
          .map((item) => this.normalizeParticipanteSeleccion(item))
          .filter((item): item is ParticipanteSeleccion => item !== null)),
      );
  }

  guardarSeleccionParticipantes(
    eventoId: string,
    participantes: ParticipanteSeleccion[],
  ): Observable<ParticipanteSeleccion[]> {
    const currentUserId = this.authService.currentUserId;
    if (!currentUserId) {
      return of(participantes);
    }

    const normalizedEventoId = this.normalizeEventoId(eventoId);
    const payloadParticipantes = participantes
      .map((item) => this.normalizeParticipanteSeleccion(item))
      .filter((item): item is ParticipanteSeleccion => item !== null);
    const requestParticipantes = payloadParticipantes
      .map((item) => ({
        ...item,
        id: this.buildParticipanteSeleccionId(item),
      }));

    return this.http
      .put<SeleccionParticipantesResponseApi>(
        `${this.apiBaseUrl}/api/eventos/${encodeURIComponent(normalizedEventoId)}/seleccion_participantes`,
        { participantes: requestParticipantes },
      )
      .pipe(
        // El PUT puede devolver snapshot reducido; recargamos GET para mantener campos de relación (lineas/pagada).
        switchMap(() => this.getSeleccionParticipantes(normalizedEventoId)),
      );
  }

  // ── Relaciones ────────────────────────────────────────────────────────

  getRelacionesByUsuario(usuarioId: string): Observable<RelacionUsuario[]> {
    return this.http
      .get<ApiCollection<RelacionUsuarioCollectionItem>>(
        `${this.apiBaseUrl}/api/usuarios/${usuarioId}/relaciones`,
      )
      .pipe(
        map((r) => r.member ?? r['hydra:member'] ?? []),
        map((items) => items
          .map((item) => this.toRelacionUsuario(item))
          .filter((item): item is RelacionUsuario => item !== null)),
      );
  }

  // ── Privados ──────────────────────────────────────────────────────────

  private eventoBasePath(eventoId: string): string {
    return `${this.apiBaseUrl}/api/eventos/${this.normalizeEventoId(eventoId)}`;
  }

  private normalizeEventoDetalle(
    evento: EventoDetalle & { actividades?: ActividadEvento[] },
  ): EventoDetalle {
    const actividades = Array.isArray(evento.actividades) ? evento.actividades : undefined;

    return {
      ...evento,
      actividades: actividades,
    };
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

  private readInvitadosFromFallback(eventoId: string): Invitado[] {
    return this.getInvitadosStorageEntries()
      .filter((e) => e.eventId === eventoId)
      .map(({ eventId: _eventId, ...item }) => item);
  }

  private createInvitadoInFallback(eventoId: string, payload: AltaInvitadoPayload): Invitado {
    const created: InvitadoStorageEntry = {
      id: `nf-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
      eventId: eventoId,
      nombre: payload.nombre.trim(),
      apellidos: payload.apellidos.trim(),
      nombreCompleto: `${payload.nombre} ${payload.apellidos}`.trim(),
      parentesco: payload.parentesco?.trim() || 'Invitado/a',
      tipoPersona: payload.tipoPersona,
      observaciones: payload.observaciones?.trim() || null,
      origen: 'invitado',
      esInvitado: true,
      inscripcion: null,
    };

    const entries = this.getInvitadosStorageEntries();
    entries.push(created);
    this.setInvitadosStorageEntries(entries);

    const { eventId: _eventId, ...result } = created;
    return result;
  }

  private deleteInvitadoInFallback(eventoId: string, invitadoId: string): void {
    const next = this.getInvitadosStorageEntries().filter(
      (e) => !(e.eventId === eventoId && String(e.id) === invitadoId),
    );
    this.setInvitadosStorageEntries(next);
  }

  private getInvitadosStorageEntries(): InvitadoStorageEntry[] {
    if (typeof window === 'undefined') return [];
    try {
      const parsed = JSON.parse(window.localStorage.getItem(this.invitadosStorageKey) ?? 'null');
      return Array.isArray(parsed) ? (parsed as InvitadoStorageEntry[]) : [];
    } catch {
      return [];
    }
  }

  private setInvitadosStorageEntries(entries: InvitadoStorageEntry[]): void {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(this.invitadosStorageKey, JSON.stringify(entries));
  }

  private toInscripcionResumen(item: InscripcionResumenCollectionItem): InscripcionResumen | null {
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

  private toInscripcionCollection(item: InscripcionCollectionItem): Inscripcion | null {
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
          actividadId: this.extractResourceId(linea.actividad ?? null, '/api/actividades/') || undefined,
          usuarioId: this.extractResourceId(linea.usuario ?? null, '/api/usuarios/') || undefined,
          invitadoId: this.extractResourceId(linea.invitado ?? null, '/api/invitados/') || undefined,
          nombrePersonaSnapshot: String(linea.nombrePersonaSnapshot ?? ''),
          tipoPersonaSnapshot: typeof linea.tipoPersonaSnapshot === 'string' ? linea.tipoPersonaSnapshot : undefined,
          nombreActividadSnapshot: String(linea.nombreActividadSnapshot ?? ''),
          franjaComidaSnapshot: typeof linea.franjaComidaSnapshot === 'string' ? linea.franjaComidaSnapshot : undefined,
          precioUnitario: this.toNumber(linea.precioUnitario),
          estadoLinea: String(linea.estadoLinea ?? ''),
          pagada: Boolean(linea.pagada),
        }))
        : [],
    };
  }

  private extractResourceId(
    resource: { id?: string | number; '@id'?: string } | string | number | null | undefined,
    prefix = '',
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

    if (!prefix) {
      if (!normalized.includes('/')) {
        return normalized;
      }

      const parts = normalized.split('/').filter(Boolean);
      return parts.at(-1) ?? '';
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

  private toRelacionUsuario(item: RelacionUsuarioCollectionItem): RelacionUsuario | null {
    const id = this.extractResourceId(item.id ?? item['@id'] ?? '');
    const usuarioOrigen = this.normalizeRelacionUsuarioNode(item.usuarioOrigen);
    const usuarioDestino = this.normalizeRelacionUsuarioNode(item.usuarioDestino);

    if (!id || !usuarioOrigen.id || !usuarioDestino.id) {
      return null;
    }

    return {
      id,
      usuarioOrigen,
      usuarioDestino,
      tipoRelacion: String(item.tipoRelacion ?? '').trim() || 'familiar',
      createdAt: String(item.createdAt ?? '').trim(),
    };
  }

  private normalizeRelacionUsuarioNode(
    node: RelacionUsuarioCollectionItem['usuarioOrigen'],
  ): RelacionUsuario['usuarioOrigen'] {
    const source: {
      id?: string | number;
      '@id'?: string;
      nombre?: string;
      apellidos?: string;
      nombreCompleto?: string;
    } = typeof node === 'string'
      ? { '@id': node }
      : (node ?? {});

    const id = this.extractResourceId(source.id ?? source['@id'] ?? '', '/api/usuarios/');
    const nombreCompleto = String(source.nombreCompleto ?? '').trim();
    const nombre = String(source.nombre ?? '').trim();
    const apellidos = String(source.apellidos ?? '').trim();

    if (!nombre && nombreCompleto) {
      const [firstName, ...rest] = nombreCompleto.split(' ').filter(Boolean);
      return {
        id,
        nombre: firstName ?? '',
        apellidos: rest.join(' '),
        nombreCompleto,
      };
    }

    return {
      id,
      nombre,
      apellidos,
      nombreCompleto: nombreCompleto || [nombre, apellidos].filter(Boolean).join(' ').trim() || undefined,
    };
  }

  private normalizeParticipanteSeleccion(item: ParticipanteSeleccion): ParticipanteSeleccion | null {
    const originRaw = (item as { origen?: string }).origen;
    const origin = originRaw === 'invitado' ? 'invitado' : 'familiar';
    const normalizedId = this.extractResourceId(item.id).trim();

    if (!normalizedId) {
      return null;
    }

    return {
      ...item,
      id: normalizedId,
      origen: origin,
      tipoPersona: item.tipoPersona === 'infantil' ? 'infantil' : 'adulto',
      inscripcionRelacion: item.inscripcionRelacion
        ? {
          ...item.inscripcionRelacion,
          id: this.extractResourceId(item.inscripcionRelacion.id, '/api/inscripcions/') || item.inscripcionRelacion.id,
          totalLineas: this.toNumber(item.inscripcionRelacion.totalLineas),
          totalPagado: this.toNumber(item.inscripcionRelacion.totalPagado),
          lineas: Array.isArray(item.inscripcionRelacion.lineas)
            ? item.inscripcionRelacion.lineas.map((linea) => ({
              nombreActividadSnapshot: String(linea.nombreActividadSnapshot ?? '').trim(),
              franjaComidaSnapshot: typeof linea.franjaComidaSnapshot === 'string'
                ? linea.franjaComidaSnapshot
                : undefined,
              estadoLinea: String(linea.estadoLinea ?? '').trim(),
              precioUnitario: this.toNumber(linea.precioUnitario),
              pagada: Boolean((linea as { pagada?: unknown }).pagada),
              id: this.extractResourceId(linea.id, '/api/inscripcion_lineas/') || linea.id,
              actividadId: this.extractResourceId(
                (linea as { actividad?: { id?: string | number; '@id'?: string } | string | null }).actividad
                ?? linea.actividadId
                ?? null,
                '/api/actividades/',
              ) || undefined,
              usuarioId: this.extractResourceId(
                (linea as { usuarioId?: string | number | { id?: string | number; '@id'?: string } | null }).usuarioId ?? null,
                '/api/usuarios/',
              ) || undefined,
              invitadoId: this.extractResourceId(
                (linea as { invitadoId?: string | number | { id?: string | number; '@id'?: string } | null }).invitadoId ?? null,
                '/api/invitados/',
              ) || undefined,
            }))
            : [],
        }
        : undefined,
    };
  }

  private buildParticipanteSeleccionId(item: Pick<ParticipanteSeleccion, 'id' | 'origen'>): string {
    const normalizedId = this.extractResourceId(item.id).trim();

    if (!normalizedId) {
      return '';
    }

    return item.origen === 'invitado'
      ? `/api/invitados/${normalizedId}`
      : `/api/usuarios/${normalizedId}`;
  }

  private shouldCreateInvitadoFallback(error: unknown): boolean {
    if (!(error instanceof HttpErrorResponse)) {
      return true;
    }

    return error.status === 0;
  }
}
