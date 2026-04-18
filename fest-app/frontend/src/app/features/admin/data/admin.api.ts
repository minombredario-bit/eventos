import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams, HttpHeaders } from '@angular/common/http';
import { map, Observable } from 'rxjs';
import {
  ApiCollection,
  Cargo,
  EnumOption,
  ImportResult,
  Usuario,
  UsuarioCreatePayload,
  UsuarioPatch,
  UsuariosFiltro,
  UsuariosPage,
} from '../domain/admin.models';

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
      .get<ApiCollection<Usuario>>(`${this.apiBaseUrl}/api/usuarios`, { params })
      .pipe(
        map((response) => {
          const items = response.member ?? response['hydra:member'] ?? [];

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
      `${this.apiBaseUrl}/api/usuarios/${encodeURIComponent(id)}`
    );
  }

  // Admin-specific user GET that requests the admin serialization group
  getUsuarioAdmin(id: string): Observable<Usuario> {
    return this.http.get<Usuario>(
      `${this.apiBaseUrl}/api/admin/usuarios/${encodeURIComponent(id)}`
    );
  }

  crearUsuario(payload: UsuarioCreatePayload): Observable<Usuario> {
    return this.http.post<Usuario>(
      `${this.apiBaseUrl}/api/admin/usuarios`,
      payload
    );
  }

  updateUsuario(id: string, payload: UsuarioPatch): Observable<Usuario> {
    // API Platform expects PATCH requests to use the "application/merge-patch+json" media type
    // (otherwise Symfony/API Platform will return 415). Send the correct Content-Type header.
    const headers = new HttpHeaders({ 'Content-Type': 'application/merge-patch+json' });

    return this.http.patch<Usuario>(
      `${this.apiBaseUrl}/api/admin/usuarios/${encodeURIComponent(id)}`,
      payload,
      { headers }
    );
  }

  getCargos(): Observable<Cargo[]> {
    return this.http
      .get<ApiCollection<Cargo>>(`${this.apiBaseUrl}/api/cargos`)
      .pipe(
        map((response) => response.member ?? response['hydra:member'] ?? [])
      );
  }

  getEnumOptions<T extends string = string>(
    enumName: string
  ): Observable<EnumOption<T>[]> {
    return this.http
      .get<{ enum: string; items: EnumOption<T>[] }>(
        `${this.apiBaseUrl}/api/generic/enums/${encodeURIComponent(enumName)}`
      )
      .pipe(map((response) => response.items));
  }

  importarExcel(file: File): Observable<ImportResult> {
    const formData = new FormData();
    formData.append('file', file);

    return this.http.post<ImportResult>(
      `${this.apiBaseUrl}/api/admin/usuarios/importar-excel`,
      formData
    );
  }
}
