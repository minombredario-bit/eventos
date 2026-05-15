import {EventoAdminListado} from '../../eventos/domain/eventos.models';

// ─── Inscripciones admin ────────────────────────────────────────────────────

export type EstadoInscripcion = 'pendiente' | 'confirmada' | 'cancelada' | 'lista_espera';
export type EstadoPago = 'no_requiere_pago' | 'pendiente' | 'parcial' | 'pagado' | 'devuelto' | 'cancelado';

export interface InscripcionLineaAdmin {
  id: string;
  nombrePersonaSnapshot: string;
  tipoPersonaSnapshot: string;
  tipoRelacionEconomicaSnapshot?: string;
  nombreActividadSnapshot: string;
  franjaComidaSnapshot?: string;
  esDePagoSnapshot: boolean;
  precioUnitario: number;
  estadoLinea: string;
  pagada: boolean;
  observaciones?: string | null;
}

export interface PagoAdmin {
  id: string;
  fecha: string;
  importe: number;
  metodoPago: string;
  referencia?: string | null;
  estado: string;
  observaciones?: string | null;
  registradoPor?: string;
  inscripcion?: { id: string; codigo: string };
  usuario?: { id: string; nombre: string; apellidos: string };
  evento?: { id: string; titulo: string };
}

export interface InscripcionAdmin {
  id: string;
  codigo: string;
  usuario: { id: string; nombre: string; apellidos: string; email: string };
  evento: { id: string; titulo: string; fecha: string };
  estadoInscripcion: EstadoInscripcion;
  estadoPago: EstadoPago;
  importeTotal: number;
  importePagado: number;
  observaciones?: string | null;
  createdAt: string;
  updatedAt?: string;
  lineas: InscripcionLineaAdmin[];
  pagos: PagoAdmin[];
}

export interface InscripcionAdminSummary {
  id: string;
  codigo: string;
  usuario: { id: string; nombre: string; apellidos: string; email: string };
  evento: { id: string; titulo: string };
  estadoInscripcion: EstadoInscripcion;
  estadoPago: EstadoPago;
  importeTotal: number;
  importePagado: number;
  createdAt: string;
}

export interface InscripcionesPage {
  items: InscripcionAdminSummary[];
  totalItems: number;
  totalPages: number;
  page: number;
  itemsPerPage: number;
  hasNext: boolean;
  hasPrevious: boolean;
}

export interface RegistrarPagoPayload {
  metodoPago: string;
  referencia?: string;
  observaciones?: string;
}

// ─── Dashboard ──────────────────────────────────────────────────────────────

export interface DashboardStats {
  totalCensados: number;
  totalEventosPublicados: number;
  proximosEventos: EventoAdminListado[];
}

export interface DashboardAsistenciaStats {
  totalAsistenciaHistorica: number;
  totalEventosPasados: number;
  mediaAsistenciaGeneral: number;
  mediaPorFranja: Record<string, number>;
  mediaPorTipo: Record<'adulto' | 'infantil' | 'ambos', number | null>;
  mediaPorActividad: Record<string, number | null>;
}


export type EstadoValidacion =
  | 'pendiente_validacion'
  | 'validado'
  | 'rechazado'
  | 'bloqueado';

export type MetodoPagoPreferida =
  | 'efectivo'
  | 'tarjeta'
  | 'transferencia'
  | 'bizum'
  | 'tpv'
  | 'online'
  | 'manual';

export type UserRole = 'ROLE_ADMIN_ENTIDAD' | 'ROLE_USER' | 'ROLE_EVENTO';

export interface EnumOption<T extends string = string> {
  name: string;
  value: T;
  label: string;
}

export const TIPOS_RELACION = [
  'familiar',
  'amistad',
] as const;

export type TipoRelacion = typeof TIPOS_RELACION[number];

export function isTipoRelacion(value: string): value is TipoRelacion {
  return (TIPOS_RELACION as readonly string[]).includes(value);
}

export interface Entidad {
  id?: string;
  nombre?: string;
  emailContacto?: string;
  telefono?: string | null;
  direccion?: string | null;
  textoLopd?: string | null;
}

export type TipoPersona = 'infantil' | 'cadete' | 'adulto';
export type CargoTipoPersona = 'infantil' | 'adulto';
export type CargoOrigen = 'cargo_master' | 'entidad_cargo';

export type UsuariosFiltro = 'todos' | 'censado' | 'no_censado';

export interface CargoMaster {
  id: string;
  nombre: string;
  codigo?: string | null;
  descripcion?: string | null;
  activo?: boolean;
  infantilEspecial?: boolean;
}

export interface EntidadCargo {
  id: string;
  entidad?: string;
  cargo?: Cargo | string | null;
  cargoMaster?: CargoMaster | null;
  nombre?: string | null;
  orden?: number;
  activo?: boolean;

  esOficial?: boolean;
  nombreVisible?: string | null;
  codigoVisible?: string | null;
  descripcionVisible?: string | null;
  esInfantil?: boolean;
  infantilEspecial?: boolean;
  computaComoDirectivo?: boolean;
  esRepresentativo?: boolean;
  ordenJerarquicoVisible?: number;
  aniosComputables?: number;
}

export interface TipoEntidadCargo {
  id: string;
  cargoMaster: CargoMaster;
  activo?: boolean;
}

export interface Cargo {
  id: string;
  nombre: string;
  registroId?: string;
  codigo?: string | null;
  descripcion?: string | null;
  activo?: boolean;
  infantilEspecial?: boolean;
  tipoPersona?: CargoTipoPersona;
  origen?: CargoOrigen;
  iri?: string;
  entidadCargo?: EntidadCargo;
  cargo?: Cargo | null;
  entidad?: string;
}

export interface TipoEntidadCargo {
  id: string;
  tipoEntidad?: string;
  cargoMaster: CargoMaster;
  activo?: boolean;
}

export interface RelacionUsuario {
  id?: string;
  usuario_id: string;
  usuario_nombre?: string;
  tipoRelacion: TipoRelacion;
}

export interface RelacionUsuarioWritePayload {
  usuario: string;
  tipoRelacion: TipoRelacion;
}

interface UsuarioBase {
  nombre?: string;
  apellidos?: string;
  direccion?: string | null;
  email?: string | null;
  telefono?: string | null;
  documentoIdentidad?: string | null;

  activo?: boolean;
  motivoBajaCenso?: string | null;

  antiguedad?: number | null;
  antiguedadReal?: number | null;

  fechaNacimiento?: string | null;
  fechaAltaCenso?: string | null;
  fechaBajaCenso?: string | null;

  formaPagoPreferida?: MetodoPagoPreferida | null;
  debeCambiarPassword?: boolean;

  tipoPersona?: TipoPersona | null;
  censadoVia?: string | null;
  estadoValidacion?: EstadoValidacion | null;

  ultimoReconocimiento?: string | null;
  proximoReconocimiento?: string | null;

  roles?: UserRole[];
}

export interface Usuario extends UsuarioBase {
  id?: string;
  nombreCompleto?: string;
  cargos?: Cargo[];
  relacionUsuarios?: RelacionUsuario[];
  opciones?: String[];
}

export interface UsuarioWrite extends UsuarioBase {
  cargos?: string[];
  relacionUsuarios?: RelacionUsuarioWritePayload[];
}

export interface UsuarioCreatePayload {
  nombre: string;
  apellidos: string;
  direccion?: string | null;
  email?: string | null;
  documentoIdentidad?: string | null;
  activo: boolean;
  fechaNacimiento: string | null;
  formaPagoPreferida: MetodoPagoPreferida;
  debeCambiarPassword: boolean;
  roles: UserRole[];

  telefono?: string | null;
  motivoBajaCenso?: string | null;
  antiguedad?: number | null;
  antiguedadReal?: number | null;
  cargos?: string[];
  relacionUsuarios?: RelacionUsuarioWritePayload[];
}

export type UsuarioPatch = Partial<UsuarioWrite>;

export interface UsuarioRelacionadoSeleccionado {
  id: string;
  nombreCompleto: string;
  tipoRelacion: TipoRelacion | null;
}

export interface UsuariosPage {
  items: Usuario[];
  totalPages: number;
  totalItems: number;
  page: number;
  itemsPerPage: number;
  hasNext: boolean;
  hasPrevious: boolean;
}

export interface ImportResult {
  total: number;
  creados?: number;
  actualizados?: number;
  relaciones?: number;
  insertadas?: number;
  passwords_excel?: null;
  errores: string[];
}

export interface ApiCollection<T> {
  member?: T[];
  'hydra:member'?: T[];
  'hydra:totalItems'?: number;
  'hydra:view'?: {
    '@id'?: string;
    'hydra:next'?: string;
    'hydra:previous'?: string;
  };
}
