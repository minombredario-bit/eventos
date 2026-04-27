import { inject, Injectable } from '@angular/core';
import { parseCollection } from '../../../core/utils/collection-utils';
import { HttpClient, HttpErrorResponse, HttpHeaders, HttpParams } from '@angular/common/http';
import {catchError, map, Observable, of, switchMap, tap, throwError} from 'rxjs';
import { EventosMapper } from './eventos.mapper';
import { AuthService } from '../../../core/auth/auth';
import {
  ActividadEvento,
  AltaInvitadoPayload,
  EventoAdminListado,
  EventoApuntadosResponse,
  EventoDetalle,
  EventoParticipanteReporte,
  EventoParticipantesAgrupadosPorFranja,
  EventoParticipantesReporteResponse,
  PersonType,
  EventoResumen,
  Inscripcion,
  InscripcionResumen,
  Invitado,
  MetodoPago,
  ParticipanteSeleccion,
  RelacionUsuario, EventosAdminParams, EventosPage, InscripcionesPage,
} from '../domain/eventos.models';

import {
  ApiCollection,
  CrearInscripcionResponse,
  EventoListAdminResponse,
  EventoParticipantesReporteApi,
  EventoWritePayload,
  EventoApuntadosCollectionResponse,
  InscripcionCollectionItem,
  InscripcionResumenCollectionItem,
  InvitadoStorageEntry,
  RelacionUsuarioCollectionItem,
  SeleccionParticipantesResponseApi,
} from '../domain/eventos.api.models';
import {Usuario, UsuariosFiltro, UsuariosPage} from '../../admin/domain/admin.models';
import {Eventos} from '../eventos';
import {environment} from '../../../../environments/environment';


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
      .get<ApiCollection<EventoResumen> | EventoResumen[]>(`${environment.apiUrl}/eventos`, { params })
      .pipe(map((r) => parseCollection<EventoResumen>(r as unknown)));
  }

  getEventosAdmin(params: EventosAdminParams = {}): Observable<EventosPage> {
    const {
      page = 1,
      itemsPerPage = 10,
      search,
      monthOnly,
      monthKey,
    } = params;

    let httpParams = new HttpParams()
      .set('page', page)
      .set('itemsPerPage', itemsPerPage)
      .set('order[fechaEvento]', 'asc')
      .set('order[horaInicio]', 'asc');

    if (search?.trim()) {
      httpParams = httpParams.set('titulo', search.trim());
    }

    if (monthOnly && monthKey) {
      const [year, month] = monthKey.split('-');
      const lastDay = new Date(Number(year), Number(month), 0).getDate();

      httpParams = httpParams
        .set('fechaEvento[after]', `${monthKey}-01`)
        .set('fechaEvento[before]', `${monthKey}-${String(lastDay).padStart(2, '0')}`);
    }

    return this.http
      .get<ApiCollection<EventoAdminListado>>(`${environment.apiUrl}/eventos`, { params: httpParams })
      .pipe(
        map((response) => {
          const raw = response as unknown as Record<string, any>;

          const members = (raw['member'] ?? raw['hydra:member'] ?? []) as EventoAdminListado[];
          const items = members.map((item) => this.normalizeEventoListado(item));

          const totalItems = Number(raw['totalItems'] ?? raw['hydra:totalItems'] ?? items.length);
          const totalPages = Math.ceil(totalItems / itemsPerPage);

          const view = raw['view'] ?? raw['hydra:view'];

          return {
            items,
            totalItems,
            totalPages,
            page,
            itemsPerPage,
            hasNext: Boolean(view?.['next'] ?? view?.['hydra:next']),
            hasPrevious: Boolean(view?.['previous'] ?? view?.['hydra:previous']),
          } satisfies EventosPage;
        }),
      );
  }

  getEvento(id: string): Observable<EventoDetalle> {
    return this.http
      .get<EventoDetalle & { actividades?: ActividadEvento[] }>(this.eventoBasePath(id))
      .pipe(map((evento) => this.normalizeEventoDetalle(evento)));
  }

  getEventoAdmin(id: string): Observable<EventoDetalle> {
    return this.getEvento(id);
  }

  crearEvento(payload: EventoWritePayload): Observable<EventoDetalle> {
    return this.http.post<EventoDetalle>(`${environment.apiUrl}/eventos`, payload);
  }

  actualizarEvento(id: string, payload: EventoWritePayload): Observable<EventoDetalle> {
    const headers = new HttpHeaders({ 'Content-Type': 'application/merge-patch+json' });

    return this.http.patch<EventoDetalle>(
      this.eventoBasePath(id),
      payload,
      { headers },
    );
  }

  publicarEvento(id: string): Observable<void> {
    return this.http.post<void>(`${this.eventoBasePath(id)}/publicar`, {});
  }

  cerrarEvento(id: string): Observable<void> {
    return this.http.post<void>(`${this.eventoBasePath(id)}/cerrar`, {});
  }

  descargarReportePdf(eventoId: string): Observable<Blob> {
    return this.http.get(
      `${this.eventoBasePath(eventoId)}/reporte-participantes`,
      {
        responseType: 'blob',
        headers: new HttpHeaders({ 'Accept': 'application/pdf' }),
      },
    );
  }

  // Fallback legacy: usar solo cuando GET /api/eventos/{id} no incluya actividades embebidas.
  getActividadesByEvento(eventoId: string): Observable<ActividadEvento[]> {
    return this.http
      .get<ApiCollection<ActividadEvento> | ActividadEvento[]>(`${environment.apiUrl}/actividades?evento=${encodeURIComponent(eventoId)}`)
      .pipe(map((r) => parseCollection<ActividadEvento>(r as unknown)));
  }

  /**
   * Borra una actividad por id en el servidor.
   * Devuelve void en caso de éxito.
   */
  deleteActividad(actividadId: string): Observable<number> {
    // Observe the full response to allow callers to act based on the status code
    return this.http
      .delete<void>(
        `${environment.apiUrl}/actividad_eventos/${encodeURIComponent(actividadId)}`,
        { observe: 'response' as const }
      )
      .pipe(map((resp) => resp.status));
  }

  // ── Inscripciones ─────────────────────────────────────────────────────

  crearInscripcion(
    eventoId: string,
    persona: { usuario: string; actividad: string; observaciones?: string },
  ): Observable<CrearInscripcionResponse> {
    return this.http.post<CrearInscripcionResponse>(
      `${this.eventoBasePath(eventoId)}/inscribirme`,
      { persona },
    );
  }

  /**
   * POST /api/seleccion_participante_eventos
   * Inscribe un participante en el evento y devuelve la selección creada con su id.
   */
  postSeleccionParticipante(body: Record<string, string>): Observable<{ id: string }> {
    return this.http.post<{ id: string }>(
      `${environment.apiUrl}/seleccion_participante_eventos`,
      body,
      { headers: new HttpHeaders({ 'Content-Type': 'application/ld+json' }) },
    );
  }

  /**
   * DELETE /api/eventos/{eventoId}/seleccion_participantes/{id}
   * Elimina la selección; el backend cancela las líneas no pagadas
   * y borra la inscripción si queda vacía.
   */
  deleteSeleccionParticipante(eventoId: string, seleccionId: string): Observable<void> {
    return this.http.delete<void>(
      `${environment.apiUrl}/eventos/${encodeURIComponent(eventoId)}/seleccion_participantes/${encodeURIComponent(seleccionId)}`
    );
  }

  getInscripcionesMias(): Observable<InscripcionResumen[]> {
    const currentUserId = this.authService.currentUserId;
    if (!currentUserId) {
      return of([]);
    }

    const params = new HttpParams().set('usuario.id', currentUserId.trim());

    return this.http
      .get<ApiCollection<InscripcionResumenCollectionItem> | InscripcionResumenCollectionItem[]>(`${environment.apiUrl}/inscripcions`, { params })
      .pipe(
        map((r) => parseCollection<InscripcionResumenCollectionItem>(r as unknown)),
        map((items) => items
          .map((item) => this.toInscripcionResumen(item))
          .filter((item): item is InscripcionResumen => item !== null)),
      );
  }

  getInscripcionesMiasCollection(options: {
    search?: string;
    page?: number;
    itemsPerPage?: number;
  } = {}): Observable<InscripcionesPage> {

    let params = new HttpParams();

    const search = (options.search ?? '').trim();
    const page = options.page ?? 1;
    const itemsPerPage = options.itemsPerPage ?? 10;

    params = params
      .set('page', page)
      .set('itemsPerPage', itemsPerPage)
      .set('order[fechaEvento]', 'desc');

    if (search?.trim() && search.length > 3) {
      params = params.set('titulo', search.trim());
    }

    return this.http
      .get<ApiCollection<Inscripcion>>(`${environment.apiUrl}/inscripcions`, { params })
      .pipe(
        map((response) => {
          const raw = response as unknown as Record<string, any>;

          const items = (raw['member'] ?? raw['hydra:member'] ?? []) as Inscripcion[];

          const totalItems = Number(raw['totalItems'] ?? raw['hydra:totalItems'] ?? items.length);
          const totalPages = Math.ceil(totalItems / itemsPerPage);

          const view = raw['view'] ?? raw['hydra:view'];

          return {
            items,
            totalItems,
            totalPages,
            page,
            itemsPerPage,
            hasNext: Boolean(view?.['next'] ?? view?.['hydra:next']),
            hasPrevious: Boolean(view?.['previous'] ?? view?.['hydra:previous']),
          } satisfies InscripcionesPage;
        }),
      );
  }

  // getInscripcionesMiasCollection(): Observable<Inscripcion[]> {
  //   const currentUserId = this.authService.currentUserId;
  //   if (!currentUserId) {
  //     return of([]);
  //   }
  //
  //   const params = new HttpParams().set('usuario.id', currentUserId.trim());
  //
  //   return this.http
  //     .get<ApiCollection<InscripcionCollectionItem> | InscripcionCollectionItem[]>(`${environment.apiUrl}/inscripcions`, { params })
  //     .pipe(
  //       map((r) => parseCollection<InscripcionCollectionItem>(r as unknown)),
  //       map((items) => items
  //         .map((item) => this.toInscripcionCollection(item))
  //         .filter((item): item is Inscripcion => item !== null)),
  //     );
  // }

  /**
   * Devuelve la respuesta completa de /seleccion_participantes incluyendo participantes
   * e inscripciones encontradas. Útil para evitar múltiples llamadas desde el cliente.
   */
  getSeleccionParticipantesFull(eventoId: string): Observable<{ participantes: ParticipanteSeleccion[]; inscripciones: Inscripcion[] }> {
    return this.http
      .get<SeleccionParticipantesResponseApi & { inscripciones?: unknown }>(`${this.eventoBasePath(eventoId)}/seleccion_participantes`)
      .pipe(
        map((r) => {
          const participantes = (Array.isArray(r.participantes) ? r.participantes : [])
            .map((item) => this.normalizeParticipanteSeleccion(item))
            .filter((item): item is ParticipanteSeleccion => item !== null);

          const inscripciones = Array.isArray((r as any).inscripciones)
            ? parseCollection<InscripcionCollectionItem>((r as any).inscripciones as unknown).map((it) => this.toInscripcionCollection(it)).filter((it): it is Inscripcion => it !== null)
            : [];

          return { participantes, inscripciones };
        }),
      );
  }

  getInscripcion(id: string): Observable<Inscripcion> {
    return this.http
      .get<InscripcionCollectionItem>(`${environment.apiUrl}/inscripcions/${id}`)
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

  getInscripcionActualPorEvento(eventoId: string): Observable<Inscripcion | null> {
    const params = new HttpParams()
      .set('evento.id', this.normalizeEventoId(eventoId))
      .set('pagination', 'false');

    return this.http
      .get<ApiCollection<InscripcionCollectionItem> | InscripcionCollectionItem[]>(
        `${environment.apiUrl}/inscripcions`,
        { params },
      )
      .pipe(
        map((response) => parseCollection<InscripcionCollectionItem>(response as unknown)),
        map((items) => items
          .map((item) => this.toInscripcionCollection(item))
          .filter((item): item is Inscripcion => item !== null),
        ),
        map((items) => items[0] ?? null),
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
      .get<EventoApuntadosCollectionResponse | any>(`${this.eventoBasePath(eventoId)}/apuntados`, { params })
      .pipe(map((r) => {
        const items = parseCollection<any>(r as unknown);
        const apuntados = (items ?? []).map((item) => ({
          inscripcionId: String(item.inscripcionId ?? ''),
          nombreCompleto: String(item.nombreCompleto ?? '').trim(),
            opciones: Array.isArray(item.opciones)
              ? item.opciones.map((opcion: unknown) => String(opcion).trim()).filter(Boolean)
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
      `${environment.apiUrl}/inscripcion_lineas/${encodeURIComponent(lineaId)}`,
    );
  }

  actualizarActividadLineaInscripcion(lineaId: string, actividadId: string): Observable<unknown> {
    return this.http.patch(
      `${environment.apiUrl}/inscripcion_lineas/${encodeURIComponent(lineaId)}`,
      { actividad: `/api/actividades/${actividadId}` },
      {
        headers: new HttpHeaders({ 'Content-Type': 'application/merge-patch+json' }),
      },
    );
  }

  actualizarFormaPagoPreferida(formaPagoPreferida: MetodoPago | null): Observable<unknown> {
    return this.http.patch(`${environment.apiUrl}/me`, { formaPagoPreferida });
  }

  // ── Invitados ─────────────────────────────────────────────────────────

  getInvitadosByEvento(eventoId: string): Observable<Invitado[]> {
    return this.http
      .get<ApiCollection<Invitado> | Invitado[]>(`${this.eventoBasePath(eventoId)}/invitados`)
      .pipe(
        map((r) => this.mapper.mapInvitadosList(parseCollection<Invitado>(r as unknown), eventoId)),
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
      .post<Invitado>(`${environment.apiUrl}/invitados`, {
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
      .delete<unknown>(`${environment.apiUrl}/invitados/${encodeURIComponent(invitadoId)}`)
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
        `${environment.apiUrl}/eventos/${encodeURIComponent(normalizedEventoId)}/seleccion_participantes`,
        { participantes: requestParticipantes },
      )
      .pipe(
        // El PUT puede devolver snapshot reducido; recargamos GET para mantener campos de relación (lineas/pagada).
        switchMap(() => this.getSeleccionParticipantesFull(normalizedEventoId).pipe(map((r) => r.participantes))),
      );
  }

  // ── Relaciones ────────────────────────────────────────────────────────

  getRelacionesByUsuario(usuarioId: string): Observable<RelacionUsuario[]> {
    return this.http
      .get<ApiCollection<RelacionUsuarioCollectionItem> | RelacionUsuarioCollectionItem[]>(
        `${environment.apiUrl}/usuarios/${usuarioId}/relaciones`,
      )
      .pipe(
        map((r) => parseCollection<RelacionUsuarioCollectionItem>(r as unknown)),
        map((items) => items
          .map((item) => this.toRelacionUsuario(item))
          .filter((item): item is RelacionUsuario => item !== null)),
      );
  }

  // ── Privados ──────────────────────────────────────────────────────────

  private eventoBasePath(eventoId: string): string {
    return `${environment.apiUrl}/eventos/${this.normalizeEventoId(eventoId)}`;
  }

  private normalizeEventoDetalle(
    evento: EventoDetalle & { actividades?: ActividadEvento[] },
  ): EventoDetalle {
    const actividades = Array.isArray(evento.actividades)
      ? [...evento.actividades]
        .map((actividad, index) => ({
          ...actividad,
          tipoActividad: String(actividad.tipoActividad ?? '').trim() || 'libre',
          ordenVisualizacion: this.toNumber(actividad.ordenVisualizacion ?? index),
        }))
        .sort((left, right) => (left.ordenVisualizacion ?? 0) - (right.ordenVisualizacion ?? 0))
      : undefined;

    return {
      ...evento,
      actividades: actividades,
    };
  }

  private normalizeEventoListado(item: EventoAdminListado): EventoAdminListado {
    return {
      ...item,
      id: String(item.id ?? '').trim(),
      titulo: String(item.titulo ?? '').trim(),
      fechaEvento: String(item.fechaEvento ?? '').trim(),
      estado: String(item.estado ?? '').trim() || 'borrador',
      inscripcionAbierta: item.inscripcionAbierta,
      personasApuntadas: this.toNumber(item.personasApuntadas),
    };
  }

  private normalizeReportePersonas(
    response: EventoParticipantesReporteApi,
  ): EventoParticipantesReporteResponse {
    return {
      evento: {
        id: String(response.evento?.id ?? '').trim(),
        titulo: String(response.evento?.titulo ?? 'Evento').trim() || 'Evento',
        fecha: String(response.evento?.fecha ?? '').trim(),
      },
      totalPersonas: Number(response.totalPersonas ?? response.personas?.length ?? 0),
      personas: Array.isArray(response.personas)
        ? response.personas.map((persona): EventoParticipanteReporte => {
          const tipoPersona: PersonType = persona.tipoPersona === 'infantil' ? 'infantil' : 'adulto';

          return {
            nombreCompleto: String(persona.nombreCompleto ?? '').trim(),
            tipoPersona,
            actividad: String(persona.actividad ?? '').trim(),
            franjaComida: this.normalizeMealSlot(persona.franjaComida),
            observaciones: typeof persona.observaciones === 'string' ? persona.observaciones.trim() : null,
            inscripcionCodigo: String(persona.inscripcionCodigo ?? '').trim(),
            inscriptor: String(persona.inscriptor ?? '').trim(),
          };
        }).filter((persona) => persona.nombreCompleto.length > 0)
        : [],
    };
  }

  private groupReportePersonasByFranja(
    personas: EventoParticipanteReporte[],
    actividades: ActividadEvento[],
  ): EventoParticipantesAgrupadosPorFranja[] {
    const actividadPorNombre = new Map(
      actividades.map((actividad) => [actividad.nombre.trim().toLowerCase(), actividad.franjaComida]),
    );

    const grupos = new Map<EventoParticipantesAgrupadosPorFranja['franja'], EventoParticipanteReporte[]>();

    for (const persona of personas) {
      const franja = persona.franjaComida
        ?? actividadPorNombre.get(persona.actividad.trim().toLowerCase())
        ?? 'sin_franja';

      const current = grupos.get(franja) ?? [];
      current.push({
        ...persona,
        franjaComida: franja === 'sin_franja' ? null : franja,
      });
      grupos.set(franja, current);
    }

    const order: Array<EventoParticipantesAgrupadosPorFranja['franja']> = ['almuerzo', 'comida', 'merienda', 'cena', 'sin_franja'];

    return order
      .filter((franja) => (grupos.get(franja) ?? []).length > 0)
      .map((franja) => ({
        franja,
        etiqueta: this.formatMealSlot(franja),
        participantes: grupos.get(franja) ?? [],
      }));
  }

  private normalizeMealSlot(value: string | null | undefined): ActividadEvento['franjaComida'] | null {
    if (!value) {
      return null;
    }

    const normalized = value.trim().toLowerCase();
    return normalized === 'almuerzo' || normalized === 'comida' || normalized === 'merienda' || normalized === 'cena'
      ? normalized
      : null;
  }

  private formatMealSlot(value: EventoParticipantesAgrupadosPorFranja['franja']): string {
    if (value === 'sin_franja') {
      return 'Sin franja';
    }

    const labels: Record<ActividadEvento['franjaComida'], string> = {
      almuerzo: 'Almuerzo',
      comida: 'Comida',
      merienda: 'Merienda',
      cena: 'Cena',
    };

    return labels[value];
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

    // Allow relaciones even if the usuario nodes don't provide an id.
    // Generate a synthetic id from nombreCompleto when necessary so the UI
    // can render and track the user (non-persistent).
    const ensureId = (node: RelacionUsuario['usuarioOrigen']) => {
      if (node.id && String(node.id).trim().length) return node.id;
      const name = (node.nombreCompleto ?? `${node.nombre ?? ''} ${node.apellidos ?? ''}`).trim();
      if (!name) return '';
      // simple slug: lowercase, remove diacritics, keep alnum and hyphens
      const slug = name
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
      return `nf-${slug}`;
    };

    const origenId = ensureId(usuarioOrigen);
    const destinoId = ensureId(usuarioDestino);

    if (!id) {
      // If relación itself lacks an id, still allow rendering by creating one
      // from the usuarios involved.
    }

    if (!origenId || !destinoId) {
      return null;
    }

    return {
      id: id || `${origenId}--${destinoId}`,
      usuarioOrigen: { ...usuarioOrigen, id: origenId },
      usuarioDestino: { ...usuarioDestino, id: destinoId },
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
