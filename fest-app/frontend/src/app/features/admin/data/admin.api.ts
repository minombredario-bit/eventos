import { inject, Injectable } from '@angular/core';
import { parseCollection } from '../../../core/utils/collection-utils';
import { HttpClient, HttpParams, HttpHeaders } from '@angular/common/http';
import { map, Observable } from 'rxjs';
import {
  ApiCollection,
  Cargo,
  CargoTipoPersona,
  EnumOption,
  ImportResult,
  EntidadCargo,
  Usuario,
  UsuarioCreatePayload,
  UsuarioPatch,
  UsuariosFiltro,
  UsuariosPage,
} from '../domain/admin.models';
import {environment} from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class AdminApi {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = 'http://localhost:8080';

  getUsuarios(options: {
    search?: string;
    filtro?: UsuariosFiltro;
    page?: number;
    itemsPerPage?: number;
  } = {}): Observable<UsuariosPage> {
    let params = new HttpParams();

    const search = (options.search ?? '').trim();
    const filtro = options.filtro ?? 'censado';
    const page = options.page ?? 1;
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

    if (search) {
      params = params.set(
        search.includes('@') ? 'email' : 'nombreCompleto',
        search
      );
    }

    return this.http
      .get<ApiCollection<Usuario>>(`${environment.apiUrl}/usuarios`, { params })
      .pipe(
        map((response) => {
          const items = parseCollection(response as unknown) as unknown as Usuario[];

          return {
            items,
            totalItems: Number(response['hydra:totalItems'] ?? items.length),
            page,
            itemsPerPage,
            hasNext: Boolean(response['hydra:view']?.['hydra:next']),
            hasPrevious: Boolean(response['hydra:view']?.['hydra:previous']),
          };
        })
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
    let params = new HttpParams();

    if (tipoPersona) {
      params = params.set('tipoPersona', tipoPersona);
      params = params.set('infantil_especial', tipoPersona === 'infantil' ? '1' : '0');
    }

    return this.http
      .get<ApiCollection<EntidadCargo> | EntidadCargo[]>(`${environment.apiUrl}/entidad_cargos`, { params })
      .pipe(map((response) => parseCollection<EntidadCargo>(response as unknown)));
  }

  getCargos(tipoPersona?: CargoTipoPersona): Observable<Cargo[]> {
    return this.getEntidadCargos(tipoPersona).pipe(
      map((entidadCargos) =>
        entidadCargos
          .filter((item) => item.activo !== false)
          .map((item): Cargo | null => {
            const cargo = item.cargo;

            if (!cargo) {
              return null;
            }

            const nombre = (item.nombre?.trim() || cargo.nombre).trim();
            const codigo = cargo.codigo ?? null;
            const tipoPersonaInferida = this.resolveCargoTipoPersona(codigo, nombre);

            return {
              id: cargo.id,
              registroId: item.id,
              nombre,
              codigo,
              descripcion: cargo.descripcion ?? null,
              activo: item.activo,
              infantilEspecial: cargo.infantilEspecial ?? false,
              tipoPersona: tipoPersonaInferida,
              origen: 'entidad_cargo' as const,
              iri: `/api/cargos/${cargo.id}`,
              entidadCargo: item,
            } as Cargo;
          })
          .filter((item): item is Cargo => item !== null)
          .filter((item) => (tipoPersona ? item.tipoPersona === tipoPersona : true))
          .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es', { sensitivity: 'base' }))
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

  importarExcel(file: File): Observable<ImportResult> {
    const formData = new FormData();
    formData.append('file', file);

    return this.http.post<ImportResult>(
      `${environment.apiUrl}/admin/usuarios/importar-excel`,
      formData
    );
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
