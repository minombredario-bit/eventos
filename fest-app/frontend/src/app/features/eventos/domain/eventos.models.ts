import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {EventosMapper} from '../data/eventos.mapper';
import {Usuario} from '../../admin/domain/admin.models';

export type EventStatus = 'abierto' | 'ultimas_plazas' | 'cerrado';
export type PersonType = 'adulto' | 'infantil';
export type ParticipantOrigin = 'familiar' | 'invitado';
export type PaymentBadgeStatus = 'pagado' | 'pendiente' | 'no_requiere' | 'parcial';
export type MealSlot = 'almuerzo' | 'comida' | 'merienda' | 'cena';
export type ActivityCompatibility = 'adulto' | 'infantil' | 'ambos';
export type MetodoPago = 'efectivo' | 'transferencia' | 'bizum' | 'tpv' | 'online' | 'manual';
export type EventoEstado = 'borrador' | 'publicado' | 'cerrado' | 'finalizado' | 'cancelado';

export interface EventSummary {
  id: string;
  title: string;
  date: string;
  time: string;
  location: string;
  status: EventStatus;
  description: string;
  fechaLimiteInscripcion?: string | null;
  fechaFinInscripcion?: string | null;
}

export interface EnrollmentEvent {
  id: string;
  titulo: string;
  fechaEvento: string;
  horaInicio?: string | null;
}

export interface Enrollment {
  evento: EnrollmentEvent;
  estadoPago: string;
}

export interface FamilyMember {
  id: string;
  name: string;
  role: string;
  personType: PersonType;
  origin: ParticipantOrigin;
  avatarInitial: string;
  notes?: string;
  enrollment?: MemberEnrollment;
}

export interface PersonaFamiliar {
  id: string;
  nombre: string;
  apellidos: string;
  nombreCompleto: string;
  parentesco: string;
  tipoPersona: PersonType;
  observaciones?: string | null;
  inscripcion?: Enrollment | null;
}

export interface Invitado {
  id: string;
  nombre: string;
  apellidos?: string;
  nombreCompleto?: string;
  parentesco?: string;
  tipoPersona: PersonType;
  observaciones?: string | null;
  origen?: ParticipantOrigin;
  esInvitado?: boolean;
  inscripcion?: Enrollment | null;
}

export interface InvitadoDelete {
  id: string;
  eventId: string;
}

export interface MemberEnrollment {
  eventId?: string;
  eventTitle?: string;
  eventLabel: string;
  paymentStatus: PaymentBadgeStatus;
  paymentStatusRaw: string;
}

export interface ActivityOption {
  id: string;
  label: string;
  slot: MealSlot;
  compatibility: 'ambos' | 'infantil' | 'adulto';
  price: number;           // precio base / fallback
  precioAdultoInterno: number;
  precioAdultoExterno: number;
  precioInfantilExterno: number;
  precioInfantil: number;
  isPaid: boolean;
  disabled?: boolean;
}

export interface ActividadEvento {
  id: string;
  evento?: string | { id?: string };
  nombre: string;
  descripcion?: string | null;
  tipoActividad?: string;
  franjaComida: MealSlot;
  compatibilidadPersona: ActivityCompatibility;
  esDePago: boolean;
  precioBase: number;
  precioInfantil?: number;
  precioAdultoInterno?: number;
  precioAdultoExterno?: number;
  ordenVisualizacion?: number;
  precioInfantilExterno?: number;
  activo?: boolean;
}

export interface EventoDetalle {
  id: string;
  titulo: string;
  descripcion?: string | null;
  fechaEvento: string;
  horaInicio?: string | null;
  horaFin?: string | null;
  lugar?: string | null;
  estado?: string;
  inscripcionAbierta?: boolean;
  permiteInvitados?: boolean;
  actividades?: ActividadEvento[];
  fechaLimiteInscripcion?: string | null;
  fechaInicioInscripcion?: string | null;
  tipoEvento: string;
  aforo?: number | null;
  visible?: boolean;
  admitePago?: boolean;
  fechaFinInscripcion?: string | null;
}

export interface EventoAdminListado {
  id: string;
  titulo: string;
  fechaEvento: string;
  estado: EventoEstado | string;
  inscripcionAbierta?: boolean;
  personasApuntadas?: number;
}

export interface EventoFormValue {
  titulo: string;
  descripcion: string;
  fechaEvento: string;
  horaInicio: string | null;
  horaFin: string | null;
  lugar: string;
  aforo: number | null;
  fechaInicioInscripcion?: string | null;
  fechaFinInscripcion?: string | null;
  visible: boolean;
  admitePago: boolean;
  permiteInvitados: boolean;
  estado: EventoEstado | string;
  requiereVerificacionAcceso: boolean;
  actividades: EventoActividadFormValue[];
}

export interface EventoActividadFormValue {
  uiId: string;
  id?: string | null;
  nombre: string;
  descripcion: string;
  franjaComida: MealSlot;
  compatibilidadPersona: ActivityCompatibility;
  esDePago: boolean;
  permiteInvitados: boolean;
  precioBase: string;
  precioInfantil: number;
  precioInfantilExterno: number;
  precioAdultoInterno: number;
  precioAdultoExterno: number;
  ordenVisualizacion: number;
  activo: boolean;
}

export interface EventoParticipanteReporte {
  nombreCompleto: string;
  tipoPersona: PersonType;
  actividad: string;
  franjaComida?: MealSlot | null;
  observaciones?: string | null;
  inscripcionCodigo: string;
  inscriptor: string;
}

export interface EventoParticipantesAgrupadosPorFranja {
  franja: MealSlot | 'sin_franja';
  etiqueta: string;
  participantes: EventoParticipanteReporte[];
}

export interface EventoParticipantesReporteResponse {
  evento: {
    id: string;
    titulo: string;
    fecha: string;
  };
  totalPersonas: number;
  personas: EventoParticipanteReporte[];
}

export interface EventoResumen {
  id: string;
  titulo: string;
  descripcion?: string | null;
  fechaEvento: string;
  horaInicio?: string | null;
  lugar?: string | null;
  estado: string;
  inscripcionAbierta?: boolean;
  permiteInvitados?: boolean;
  fechaLimiteInscripcion?: string | null;
  fechaFinInscripcion?: string | null;
}

export interface InscripcionLinea {
  id: string;
  actividadId?: string;
  usuarioId?: string;
  invitadoId?: string;
  nombrePersonaSnapshot: string;
  tipoPersonaSnapshot?: string;
  nombreActividadSnapshot: string;
  franjaComidaSnapshot?: string;
  precioUnitario: number;
  estadoLinea: string;
  pagada?: boolean;
}

export interface InscripcionEvento {
  id: string;
  titulo: string;
  descripcion?: string | null;
  fechaEvento: string;
  horaInicio?: string | null;
  lugar?: string | null;
  inscripcionAbierta?: boolean;
  fechaLimiteInscripcion?: string | null;
  fechaFinInscripcion?: string | null;
}

export interface Inscripcion {
  id: string;
  codigo: string;
  evento: InscripcionEvento;
  estadoInscripcion: string;
  estadoPago: string;
  importeTotal: number;
  importePagado: number;
  formaPagoPreferida?: MetodoPago | null;
  lineas: InscripcionLinea[];
  totalLineas?: number;
}

export interface InscripcionResumen {
  id: string;
  codigo: string;
  evento: { id: string };
}

export interface  ApuntadosPage {
  evento: EnrollmentEvent;
  items: Usuario[];
  totalPages: number;
  totalItems: number;
  page: number;
  itemsPerPage: number;
  hasNext: boolean;
  hasPrevious: boolean;
}

export interface EventoApuntado {
  inscripcionId: string;
  nombreCompleto: string;
  opciones: string[];
}

export interface EventoApuntadosResponse {
  evento: {
    id: string;
    titulo: string;
    descripcion?: string | null;
    fechaEvento: string;
  };
  apuntados: EventoApuntado[];
  totalItems: number;
  currentPage: number;
  itemsPerPage: number;
}


export interface InscripcionesPage {
  items: Inscripcion[];
  totalPages: number;
  totalItems: number;
  page: number;
  itemsPerPage: number;
  hasNext: boolean;
  hasPrevious: boolean;
}

export interface AltaInvitadoPayload {
  nombre: string;
  apellidos: string;
  tipoPersona: PersonType;
  parentesco?: string;
  observaciones?: string;
}

export interface RelacionUsuario {
  id: string;
  usuarioOrigen: {
    id?: string;
    '@id'?: string;
    nombre?: string;
    apellidos?: string;
    nombreCompleto?: string,
    tipoPersona?: PersonType
  };
  usuarioDestino: {
    id?: string;
    '@id'?: string;
    nombre?: string;
    apellidos?: string;
    nombreCompleto?: string,
    tipoPersona?: PersonType
  };
  tipoRelacion: string;
  createdAt: string;
}

export interface ParticipanteSeleccionLinea {
  id: string;
  actividadId?: string;
  usuarioId?: string;
  invitadoId?: string;
  nombreActividadSnapshot: string;
  franjaComidaSnapshot?: string;
  estadoLinea: string;
  precioUnitario: number;
  pagada?: boolean;
}

export interface ParticipanteSeleccion {
  id: string;
  origen: ParticipantOrigin;
  tipoPersona?: PersonType;
  nombre?: string;
  apellidos?: string;
  inscripcionRelacion?: {
    id: string;
    codigo: string;
    estadoPago: string;
    totalLineas?: number;
    totalPagado?: number;
    lineas: ParticipanteSeleccionLinea[];
  };
  // id de la entidad SeleccionParticipanteEvento asociada (opcional)
  seleccionId?: string;
}

export interface MetodoPagoOption {
  value: MetodoPago;
  label: string;
}

export const METODOS_PAGO_OPTIONS: MetodoPagoOption[] = [
  {value: 'efectivo', label: 'Efectivo'},
  {value: 'transferencia', label: 'Transferencia'},
  {value: 'bizum', label: 'Bizum'},
  {value: 'tpv', label: 'TPV'},
  {value: 'online', label: 'Pago online'},
  {value: 'manual', label: 'Manual'},
];

export interface NavItem {
  key: string;
  label: string;
  icon: string;
  route: string;
}

export interface CredentialLine {
  id: string;
  personName: string;
  activityName?: string;
}

export interface CredentialData {
  eventTitle: string;
  eventDate: string;
  holderName: string;
  eventZone: string;
  qrToken: string;
  lines: CredentialLine[];
}


export interface EventosPage {
  items: EventoAdminListado[];
  totalItems: number;
  totalPages: number;
  page: number;
  itemsPerPage: number;
  hasNext: boolean;
  hasPrevious: boolean;
}

export interface EventosAdminParams {
  search?: string;
  monthOnly?: boolean;
  monthKey?: string;   // formato "YYYY-MM"
  pagination?: boolean;
  page?: number;
  itemsPerPage?: number;
}
