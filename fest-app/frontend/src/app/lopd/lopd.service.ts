import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import {environment} from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class LopdService {
  private readonly http = inject(HttpClient);

  getLopd(): Observable<string | null> {
    return this.http.get<{ textoLopd: string | null }>(`${environment.apiUrl}/entidad/lopd`).pipe(
      map((response) => response.textoLopd ?? null),
    );
  }

  patchAcepto(userId: string, acepto: boolean): Observable<any> {
    return this.http.patch(`${environment.apiUrl}/usuarios/${userId}/lopd`, { acepto });
  }
}
