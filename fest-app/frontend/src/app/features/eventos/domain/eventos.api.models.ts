import {
  EventoAdminListado,
  ActivityCompatibility,
  MealSlot,
  EventoParticipanteReporte,
  EventoActividadFormValue,
  EventoFormValue,
  Invitado,
  ParticipanteSeleccion,
} from './eventos.models';

export interface ApiCollection<T> {
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

export interface EventoListAdminResponse extends ApiCollection<EventoAdminListado> {
  member?: EventoAdminListado[];
  'hydra:member'?: EventoAdminListado[];
}

export interface EventoWritePayload {
  '@id'?: string;  // IRI para actividades existentes
  id?: string;
  titulo: string;
  descripcion: string;
  fechaEvento: string;
  tipoEvento?: string; // opcional: frontend ya no lo envía, backend asignará un valor por defecto si falta
  horaInicio?: string | undefined;
  horaFin?: string | undefined;
  lugar: string;
  aforo: number | null;
  fechaInicioInscripcion?: string | undefined;
  fechaFinInscripcion?: string | undefined;
  visible: boolean;
  admitePago: boolean;
  permiteInvitados: boolean;
  estado: string;
  requiereVerificacionAcceso: boolean;
  actividades: EventoActividadWritePayload[];
}

export interface EventoActividadWritePayload {
  id?: string | null;
  nombre: string;
  descripcion: string;
  franjaComida: MealSlot;
  compatibilidadPersona: ActivityCompatibility;
  esDePago: boolean;
  permiteInvitados?: boolean;
  precioBase: string;
  precioInfantil?: string;
  precioInfantilExterno?: string;
  precioAdultoInterno?: string;
  precioAdultoExterno?: string;
  ordenVisualizacion: number;
  activo: boolean;
}

export interface EventoParticipantesReporteApi {
  evento?: {
    id?: string | number;
    titulo?: string;
    fecha?: string;
  };
  totalPersonas?: number;
  personas?: EventoParticipanteReporte[];
}

export interface InvitadoStorageEntry extends Invitado {
  eventId: string;
}
