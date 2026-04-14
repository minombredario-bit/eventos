import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { map, Observable } from 'rxjs';
import {
  AdminActualizarUsuarioPayload,
  AdminCrearUsuarioPayload,
  AdminImportResult,
  AdminUsuario,
  AdminUsuariosFiltro,
  AdminUsuariosPage,
} from '../domain/admin.models';

interface ApiCollection<T> {
  member?: T[];
  'hydra:member'?: T[];
  'hydra:totalItems'?: number;
  'hydra:view'?: {
    '@id'?: string;
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:next'?: string;
    'hydra:previous'?: string;
  };
}

interface AdminUsuarioRaw {
  id?: string | number;
  nombreCompleto?: string;
  nombre?: string;
  apellidos?: string;
  email?: string;
  telefono?: string | null;
  antiguedad?: number | string | null;
  estadoValidacion?: string;
  tipoUsuarioEconomico?: string;
  censadoVia?: string | null;
  activo?: boolean;
  fechaAltaCenso?: string | null;
  fechaBajaCenso?: string | null;
  fechaSolicitudAlta?: string | null;
}

@Injectable({ providedIn: 'root' })
export class AdminApi {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = 'http://localhost:8080';

  getUsuarios(options: {
    search?: string;
    filtro?: AdminUsuariosFiltro;
    page?: number;
    itemsPerPage?: number;
  } = {}): Observable<AdminUsuariosPage> {
    let queryParams = new HttpParams();

    const search = (options.search ?? '').trim();
    const filtro: AdminUsuariosFiltro = options.filtro ?? 'censado';
    const page = options.page ?? 1;
    const itemsPerPage = options.itemsPerPage ?? 10;

    queryParams = queryParams
      .set('page', String(page))
      .set('itemsPerPage', String(itemsPerPage))
      .set('order[nombreCompleto]', 'asc');

    if (filtro === 'censado') {
      queryParams = queryParams
        .set('exists[fechaAltaCenso]', 'true')
        .set('exists[fechaBajaCenso]', 'false');
    }

    if (filtro === 'no_censado') {
      queryParams = queryParams.set('exists[fechaBajaCenso]', 'true');
    }

    if (search.length > 0) {
      if (search.includes('@')) {
        queryParams = queryParams.set('email', search);
      } else {
        queryParams = queryParams.set('nombreCompleto', search);
      }
    }

    return this.http
      .get<ApiCollection<AdminUsuarioRaw>>(`${this.apiBaseUrl}/api/usuarios`, { params: queryParams })
      .pipe(
        map((r) => {
          const rows = (r.member ?? r['hydra:member'] ?? []).map((row) => this.toAdminUsuario(row));
          const currentPage = this.extractPageFromHydraUrl(r['hydra:view']?.['@id']) ?? page;

          return {
            items: rows,
            totalItems: Number(r['hydra:totalItems'] ?? rows.length),
            page: currentPage,
            itemsPerPage,
            hasNext: Boolean(r['hydra:view']?.['hydra:next']),
            hasPrevious: Boolean(r['hydra:view']?.['hydra:previous']),
          } satisfies AdminUsuariosPage;
        }),
      );
  }

  getUsuario(id: string): Observable<AdminUsuario> {
    return this.http
      .get<AdminUsuarioRaw>(`${this.apiBaseUrl}/api/usuarios/${encodeURIComponent(id)}`)
      .pipe(map((row) => this.toAdminUsuario(row)));
  }

  crearUsuario(payload: AdminCrearUsuarioPayload): Observable<{ id: string; email: string }> {
    return this.http.post<{ id: string; email: string }>(`${this.apiBaseUrl}/api/admin/usuarios`, payload);
  }

  updateUsuario(id: string, payload: AdminActualizarUsuarioPayload): Observable<AdminUsuario> {
    return this.http
      .patch<AdminUsuarioRaw>(`${this.apiBaseUrl}/api/usuarios/${encodeURIComponent(id)}`, payload)
      .pipe(map((row) => this.toAdminUsuario(row)));
  }

  importarExcel(file: File): Observable<AdminImportResult> {
    const formData = new FormData();
    formData.append('file', file);

    return this.http
      .post<Partial<AdminImportResult>>(`${this.apiBaseUrl}/api/admin/usuarios/importar-excel`, formData)
      .pipe(map((result) => ({
        total: Number(result.total ?? 0),
        insertadas: Number(result.insertadas ?? 0),
        errores: Array.isArray(result.errores) ? result.errores : [],
      })));
  }

  private toAdminUsuario(row: AdminUsuarioRaw): AdminUsuario {
    const nombre = String(row.nombre ?? '').trim();
    const apellidos = String(row.apellidos ?? '').trim();
    const nombreCompleto = String(row.nombreCompleto ?? '').trim() || `${nombre} ${apellidos}`.trim();

    return {
      id: String(row.id ?? '').trim(),
      nombre,
      apellidos,
      nombreCompleto,
      email: String(row.email ?? '').trim(),
      telefono: row.telefono ?? null,
      antiguedad: row.antiguedad !== null && row.antiguedad !== undefined ? Number(row.antiguedad) : null,
      estadoValidacion: String(row.estadoValidacion ?? 'pendiente_validacion'),
      tipoUsuarioEconomico: String(row.tipoUsuarioEconomico ?? 'interno'),
      censadoVia: row.censadoVia ?? null,
      activo: Boolean(row.activo),
      fechaAltaCenso: row.fechaAltaCenso ?? null,
      fechaBajaCenso: row.fechaBajaCenso ?? null,
      fechaSolicitudAlta: row.fechaSolicitudAlta ?? null,
    };
  }

  private extractPageFromHydraUrl(hydraId?: string): number | null {
    if (!hydraId) {
      return null;
    }

    const parsed = hydraId.match(/[?&]page=(\d+)/i);
    if (!parsed?.[1]) {
      return null;
    }

    const page = Number(parsed[1]);
    return Number.isFinite(page) && page > 0 ? page : null;
  }
}

