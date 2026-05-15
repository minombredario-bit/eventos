import { inject, Injectable } from '@angular/core';
import { parseCollection } from '../../../core/utils/collection-utils';
import {HttpClient, HttpParams, HttpHeaders, HttpResponse} from '@angular/common/http';
import { map, Observable } from 'rxjs';
import {
  ApiCollection,
  Cargo,
  CargoTipoPersona,
  EnumOption,
  ImportResult,
  EntidadCargo,
  Entidad,
  Usuario,
  UsuarioCreatePayload,
  UsuarioPatch,
  UsuariosFiltro,
  UsuariosPage, CargoMaster, TipoEntidadCargo,
  DashboardAsistenciaStats,
  InscripcionAdmin,
  InscripcionAdminSummary,
  InscripcionesPage,
  PagoAdmin,
  RegistrarPagoPayload,
} from '../domain/admin.models';
import {environment} from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class AdminApi {
  private readonly http = inject(HttpClient);
  // Simple in-memory caches to avoid repeated network calls during a session
  private tipoEntidadesCache: any[] | null = null;

  /**
   * Devuelve únicamente el totalItems de usuarios que coinciden con los parámetros.
   * Útil para el dashboard (no carga los datos completos).
   */
  getUsuariosCount(options: {
    filtro?: UsuariosFiltro;
    estadoValidacion?: string;
  } = {}): Observable<number> {
    return this.getUsuarios({ ...options, page: 1, itemsPerPage: 1 }).pipe(
      map((page) => page.totalItems),
    );
  }

  getUsuarios(options: {
    search?: string;
    filtro?: UsuariosFiltro;
    estadoValidacion?: string;
    tipoPersona?: string;
    mesNacimientoDesde?: string;
    mesNacimientoHasta?: string;
    page?: number;
    itemsPerPage?: number;
  } = {}): Observable<UsuariosPage> {
    let params = new HttpParams();

    const search       = (options.search ?? '').trim();
    const filtro       = options.filtro ?? 'censado';
    const page         = options.page ?? 1;
    const itemsPerPage = options.itemsPerPage ?? 10;

    params = params
      .set('page', page)
      .set('itemsPerPage', itemsPerPage)
      .set('order[nombreCompleto]', 'asc');

    if (filtro === 'censado') {
      params = params
        .set('exists[fechaAltaCenso]', true)
        .set('exists[fechaBajaCenso]', false);
    }

    if (filtro === 'no_censado') {
      params = params.set('exists[fechaBajaCenso]', true);
    }

    if (options.estadoValidacion) {
      params = params.set('estadoValidacion', options.estadoValidacion);
    }

    if (options.tipoPersona) {
      params = params.set('tipoPersona', options.tipoPersona);
    }

    if (options.mesNacimientoDesde) {
      params = params.set('mesNacimientoDesde', options.mesNacimientoDesde);
    }

    if (options.mesNacimientoHasta) {
      params = params.set('mesNacimientoHasta', options.mesNacimientoHasta);
    }

    if (search) {
      params = params.set(
        search.includes('@') ? 'email' : 'nombreCompleto',
        search,
      );
    }

    return this.http
      .get<ApiCollection<Usuario>>(`${environment.apiUrl}/usuarios`, { params })
      .pipe(
        map((response) => {
          const raw = response as unknown as Record<string, any>;

          const items = (raw['member'] ?? raw['hydra:member'] ?? []) as Usuario[];

          const totalItems = Number(raw['totalItems'] ?? raw['hydra:totalItems'] ?? items.length);
          const totalPages = Math.ceil(totalItems / itemsPerPage);

          const view = raw['view'] ?? raw['hydra:view'];

          return {
            items,
            totalItems,
            totalPages,
            page,
            itemsPerPage,
            hasNext:     Boolean(view?.['next']     ?? view?.['hydra:next']),
            hasPrevious: Boolean(view?.['previous'] ?? view?.['hydra:previous']),
          } satisfies UsuariosPage;
        }),
      );
  }

  buscarUsuariosRelacionados(search: string): Observable<Usuario[]> {
    return this.getUsuarios({
      search,
      filtro: 'todos',
      page: 1,
      itemsPerPage: 20,
    }).pipe(map((response) => response.items));
  }

  getUsuario(id: string): Observable<Usuario> {
    return this.http.get<Usuario>(
      `${environment.apiUrl}/usuarios/${encodeURIComponent(id)}`
    );
  }

  // Admin-specific user GET that requests the admin serialization group
  getUsuarioAdmin(id: string): Observable<Usuario> {
    return this.http.get<Usuario>(
      `${environment.apiUrl}/admin/usuarios/${encodeURIComponent(id)}`
    );
  }

  crearUsuario(payload: UsuarioCreatePayload): Observable<Usuario> {
    return this.http.post<Usuario>(
      `${environment.apiUrl}/admin/usuarios`,
      payload
    );
  }

  updateUsuario(id: string, payload: UsuarioPatch): Observable<Usuario> {
    // API Platform expects PATCH requests to use the "application/merge-patch+json" media type
    // (otherwise Symfony/API Platform will return 415). Send the correct Content-Type header.
    const headers = new HttpHeaders({ 'Content-Type': 'application/merge-patch+json' });

    return this.http.patch<Usuario>(
      `${environment.apiUrl}/admin/usuarios/${encodeURIComponent(id)}`,
      payload,
      { headers }
    );
  }

  getEntidadCargos(tipoPersona?: CargoTipoPersona): Observable<EntidadCargo[]> {
    let params = new HttpParams()
      .set('order[orden]', 'asc')
      .set('order[cargo.ordenJerarquico]', 'asc')
      .set('order[cargoMaster.ordenJerarquico]', 'asc');

    if (tipoPersona) {
      params = params.set('tipoPersona', tipoPersona);
    }

    return this.http
      .get<ApiCollection<EntidadCargo> | EntidadCargo[]>(
        `${environment.apiUrl}/entidad_cargos`,
        { params }
      )
      .pipe(
        map((response) => {
          const raw = response as unknown as Record<string, any>;
          const members = raw['member'] ?? raw['hydra:member'] ?? null;

          if (Array.isArray(members)) {
            return members as EntidadCargo[];
          }

          return parseCollection<EntidadCargo>(response as unknown);
        })
      );
  }

  getCargos(tipoPersona?: CargoTipoPersona): Observable<Cargo[]> {
    return this.getEntidadCargos(tipoPersona).pipe(
      map((entidadCargos) =>
        entidadCargos
          .filter((item) => item.activo !== false)
          .map((item): Cargo | null => {
            const cargo = typeof item.cargo === 'string' ? null : item.cargo;
            const cargoMaster = typeof item.cargoMaster === 'string' ? null : item.cargoMaster;

            const source = cargo ?? cargoMaster;

            if (!source) {
              return null;
            }

            const nombre = (
              item.nombreVisible?.trim() ||
              item.nombre?.trim() ||
              source.nombre
            ).trim();

            const codigo = item.codigoVisible ?? source.codigo ?? null;
            const tipoPersonaInferida = this.resolveCargoTipoPersona(codigo, nombre);

            return {
              id: item.id,
              registroId: item.id,
              nombre,
              codigo,
              descripcion: item.descripcionVisible ?? source.descripcion ?? null,
              activo: item.activo,
              infantilEspecial: item.infantilEspecial ?? source.infantilEspecial ?? false,
              tipoPersona: tipoPersonaInferida,
              origen: cargoMaster ? 'cargo_master' : 'entidad_cargo',
              iri: `/api/entidad_cargos/${item.id}`,
              entidadCargo: item,
            } as Cargo;
          })
          .filter((item): item is Cargo => item !== null)
          .filter((item) => (tipoPersona ? item.tipoPersona === tipoPersona : true))
          .sort((a, b) =>
            a.nombre.localeCompare(b.nombre, 'es', { sensitivity: 'base' })
          )
      )
    );
  }

  getEnumOptions<T extends string = string>(
    enumName: string
  ): Observable<EnumOption<T>[]> {
    return this.http
      .get<{ enum: string; items: EnumOption<T>[] }>(
        `${environment.apiUrl}/generic/enums/${encodeURIComponent(enumName)}`
      )
      .pipe(map((response) => response.items));
  }

  /** Returns raw TipoEntidad objects (id, codigo, nombre) */
  getTipoEntidadesRaw(): Observable<any[]> {
    if (this.tipoEntidadesCache) {
      return new Observable((subscriber) => { subscriber.next(this.tipoEntidadesCache!); subscriber.complete(); });
    }

    return this.http.get<ApiCollection<any>>(`${environment.apiUrl}/tipo_entidads`).pipe(
      map((r) => {
        const items = parseCollection<any>(r as unknown);
        this.tipoEntidadesCache = items;
        return items;
      })
    );
  }

  getTipoEntidadCargos(): Observable<TipoEntidadCargo[]> {
    return this.http
      .get<ApiCollection<TipoEntidadCargo> | TipoEntidadCargo[]>(
        `${environment.apiUrl}/tipo_entidad_cargos`
      )
      .pipe(
        map((response) =>
          Array.isArray(response)
            ? response
            : response.member ?? response['hydra:member'] ?? []
        )
      );
  }

  /** Create a Cargo. The backend must infer the entidad from the authenticated user; do not send entidad id. */
  crearCargo(payload: Partial<Cargo>): Observable<Cargo> {
    const body = { ...payload } as any;
    return this.http.post<Cargo>(`${environment.apiUrl}/cargos`, body);
  }

  /** Create an EntidadCargo. Backend should associate it to the current user's entidad. */
  crearEntidadCargo(payload: any): Observable<any> {
    const body = { ...payload };
    return this.http.post<any>(`${environment.apiUrl}/entidad_cargos`, body);
  }

  deleteEntidadCargo(id: string): Observable<void> {
    return this.http.delete<void>(`${environment.apiUrl}/entidad_cargos/${encodeURIComponent(id)}`);
  }

  patchEntidadCargo(id: string, payload: Partial<any>): Observable<any> {
    const headers = new HttpHeaders({ 'Content-Type': 'application/merge-patch+json' });
    return this.http.patch<any>(`${environment.apiUrl}/entidad_cargos/${encodeURIComponent(id)}`, payload, { headers });
  }

  getEntidades(): Observable<Entidad[]> {
    return this.http.get<ApiCollection<Entidad>>(`${environment.apiUrl}/entidad`).pipe(
      map((response) => parseCollection<Entidad>(response as unknown))
    );
  }

  updateEntidad(id: string, payload: Partial<Entidad>): Observable<Entidad> {
    const headers = new HttpHeaders({
      'Content-Type': 'application/merge-patch+json',
    });

    return this.http.patch<Entidad>(
      `${environment.apiUrl}/entidads/${encodeURIComponent(id)}`,
      payload,
      { headers }
    );
  }

  importarExcel(file: File): Observable<HttpResponse<Blob>> {
    const formData = new FormData();
    formData.append('file', file);

    return this.http.post(`${environment.apiUrl}/admin/usuarios/importar-excel`, formData, {
      responseType: 'blob',
      observe: 'response',
    });
  }

  exportarUsuariosExcel(params?: {
    search?: string;           // ?nombreCompleto=mar
    filtro?: string;           // 'censado' | 'no_censado' | 'todos'
    tipoPersona?: string;      // ?tipoPersona[]=adulto
    mesNacimientoDesde?: string;  // ?fechaNacimiento[after]=YYYY-MM-DD
    mesNacimientoHasta?: string;  // ?fechaNacimiento[before]=YYYY-MM-DD
    soloCumples?: boolean;
  }): Observable<HttpResponse<Blob>> {
    let httpParams = new HttpParams().set('pagination', 'false')

    if (params?.search)              httpParams = httpParams.set('nombreCompleto', params.search);
    if (params?.tipoPersona)         httpParams = httpParams.set('tipoPersona[]', params.tipoPersona);
    if (params?.mesNacimientoDesde) httpParams = httpParams.set('fechaNacimiento[after]',  params.mesNacimientoDesde);
    if (params?.mesNacimientoHasta) httpParams = httpParams.set('fechaNacimiento[before]', params.mesNacimientoHasta);
    if (params?.soloCumples) httpParams = httpParams.set('soloCumples', '1');

    if (params?.filtro === 'censado')    httpParams = httpParams.set('fechaBajaCenso[exists]', 'false');
    if (params?.filtro === 'no_censado') httpParams = httpParams.set('fechaBajaCenso[exists]', 'true');
    // 'todos' → sin parámetro extra

    return this.http.get(`${environment.apiUrl}/admin/usuarios-exportar-excel`, {
      params: httpParams,
      responseType: 'blob',
      observe: 'response',
    });
  }

  getDashboardAsistenciaStats(): Observable<DashboardAsistenciaStats> {
    return this.http.get<DashboardAsistenciaStats>(`${environment.apiUrl}/admin/dashboard-stats`);
  }

  // ─── Inscripciones ────────────────────────────────────────────────────────

  getInscripciones(options: {
    eventoId?: string;
    estadoInscripcion?: string;
    estadoPago?: string;
    page?: number;
    itemsPerPage?: number;
  } = {}): Observable<InscripcionesPage> {
    const page         = options.page ?? 1;
    const itemsPerPage = options.itemsPerPage ?? 20;

    let params = new HttpParams()
      .set('page', page)
      .set('itemsPerPage', itemsPerPage);

    if (options.eventoId)          params = params.set('evento', options.eventoId);
    if (options.estadoInscripcion) params = params.set('estadoInscripcion', options.estadoInscripcion);
    if (options.estadoPago)        params = params.set('estadoPago', options.estadoPago);

    return this.http
      .get<{ 'hydra:member': InscripcionAdminSummary[]; 'hydra:totalItems'?: number }>(
        `${environment.apiUrl}/admin/inscripciones`, { params }
      )
      .pipe(
        map((response) => {
          const raw        = response as unknown as Record<string, any>;
          const items      = (raw['hydra:member'] ?? []) as InscripcionAdminSummary[];
          const totalItems = Number(raw['hydra:totalItems'] ?? items.length);
          const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
          const view       = raw['hydra:view'] ?? {};

          return {
            items,
            totalItems,
            totalPages,
            page,
            itemsPerPage,
            hasNext:     Boolean(view['hydra:next']),
            hasPrevious: Boolean(view['hydra:previous']),
          } satisfies InscripcionesPage;
        })
      );
  }

  getInscripcion(id: string): Observable<InscripcionAdmin> {
    return this.http.get<InscripcionAdmin>(
      `${environment.apiUrl}/admin/inscripciones/${encodeURIComponent(id)}`
    );
  }

  registrarPago(inscripcionId: string, payload: RegistrarPagoPayload): Observable<{
    pagoId: string;
    importe: number;
    metodoPago: string;
    estadoPago: string;
    estadoInscripcion: string;
    importeTotal: number;
    importePagado: number;
  }> {
    return this.http.post<any>(
      `${environment.apiUrl}/admin/inscripciones/${encodeURIComponent(inscripcionId)}/registrar_pago`,
      payload
    );
  }

  // ─── Pagos ────────────────────────────────────────────────────────────────

  getPagos(options: { eventoId?: string } = {}): Observable<PagoAdmin[]> {
    let params = new HttpParams();
    if (options.eventoId) params = params.set('evento', options.eventoId);

    return this.http
      .get<{ 'hydra:member': PagoAdmin[] }>(`${environment.apiUrl}/admin/pagos`, { params })
      .pipe(map((r) => (r as any)['hydra:member'] ?? []));
  }

  private resolveCargoTipoPersona(codigo: string | null | undefined, nombre: string): CargoTipoPersona {
    const normalizedCode = (codigo ?? '').trim().toUpperCase();
    const normalizedName = nombre.trim().toLowerCase();

    if (normalizedCode.includes('INFANTIL') || normalizedName.includes('infantil')) {
      return 'infantil';
    }

    return 'adulto';
  }
}
