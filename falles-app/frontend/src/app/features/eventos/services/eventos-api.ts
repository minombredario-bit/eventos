import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { map, Observable } from 'rxjs';

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
  lugar?: string | null;
  inscripcionAbierta: boolean;
  menus: MenuEventoApi[];
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

@Injectable({ providedIn: 'root' })
export class EventosApi {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = 'http://localhost:8080';

  getEvento(id: string): Observable<EventoDetalleApi> {
    return this.http.get<EventoDetalleApi>(`${this.apiBaseUrl}/api/eventos/${id}`);
  }

  getPersonasMias(): Observable<PersonaFamiliarApi[]> {
    return this.http
      .get<HydraCollection<PersonaFamiliarApi>>(`${this.apiBaseUrl}/api/persona_familiares/mias`)
      .pipe(map((response) => response['hydra:member'] ?? []));
  }

  crearInscripcion(eventoId: string, personas: Array<{ persona: string; menu: string; observaciones?: string }>): Observable<CrearInscripcionResponse> {
    return this.http.post<CrearInscripcionResponse>(`${this.apiBaseUrl}/api/eventos/${eventoId}/inscribirme`, {
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
}
