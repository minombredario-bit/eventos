import { Invitado, ParticipanteSeleccion } from '../domain/eventos.models';

export interface ApiCollection<T> {
  member?: T[];
  'hydra:member'?: T[];
}

export interface EventoApuntadosCollectionItem {
  inscripcionId: string;
  nombreCompleto: string;
  opciones?: string[];
}

export interface EventoApuntadosCollectionResponse {
  evento?: {
    id?: string | number;
    titulo?: string;
    descripcion?: string | null;
    fechaEvento?: string;
  };
  member?: EventoApuntadosCollectionItem[];
  'hydra:member'?: EventoApuntadosCollectionItem[];
  'hydra:totalItems'?: number;
  'hydra:currentPage'?: number;
  'hydra:itemsPerPage'?: number;
}

export interface InscripcionResumenCollectionItem {
  id?: string | number;
  '@id'?: string;
  codigo?: string;
  evento?: { id?: string | number; '@id'?: string } | string | null;
}

export interface InscripcionLineaCollectionItem {
  id?: string | number;
  '@id'?: string;
  actividad?: { id?: string | number; '@id'?: string } | string | null;
  usuario?: { id?: string | number; '@id'?: string } | string | null;
  invitado?: { id?: string | number; '@id'?: string } | string | null;
  nombrePersonaSnapshot?: string;
  tipoPersonaSnapshot?: string;
  nombreActividadSnapshot?: string;
  franjaComidaSnapshot?: string;
  precioUnitario?: number | string | null;
  estadoLinea?: string;
  pagada?: boolean;
}

export interface InscripcionCollectionItem {
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

export interface RelacionUsuarioCollectionItem {
  id?: string | number;
  '@id'?: string;
  usuarioOrigen?: {
    id?: string | number;
    '@id'?: string;
    nombre?: string;
    apellidos?: string;
    nombreCompleto?: string;
  } | string;
  usuarioDestino?: {
    id?: string | number;
    '@id'?: string;
    nombre?: string;
    apellidos?: string;
    nombreCompleto?: string;
  } | string;
  tipoRelacion?: string;
  createdAt?: string;
}

export interface SeleccionParticipantesResponseApi {
  eventoId: string;
  participantes: ParticipanteSeleccion[];
  updatedAt: string | null;
}

export interface InvitadoStorageEntry extends Invitado {
  eventId: string;
}
