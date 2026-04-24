import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class LopdService {
  constructor(private http: HttpClient) {}

  // Get user by id (expects usuario:read to include aceptoLopd)
  getUsuario(userId: string): Observable<any> {
    return this.http.get(`/api/usuarios/${userId}`);
  }

  // Patch acepto flag
  patchAcepto(userId: string, acepto: boolean) {
    return this.http.patch(`/api/usuarios/${userId}/lopd`, { acepto });
  }

  // Get entidad LOPD text (by entidad id)
  getEntidad(entidadId: string) {
    return this.http.get(`/api/entidads/${entidadId}`);
  }
}

