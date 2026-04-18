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

export type UserRole = 'ROLE_ADMIN_ENTIDAD' | 'ROLE_USER';

export interface EnumOption<T extends string = string> {
  name: string;
  value: T;
  label: string;
}

export type TipoRelacion =
  | 'conyuge'
  | 'padre'
  | 'madre'
  | 'pareja'
  | 'hijo'
  | 'hija'
  | 'sobrino'
  | 'sobrina'
  | 'abuelo'
  | 'abuela';

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
}

export interface EntidadCargo {
  id: string;
  entidad?: string;
  cargo?: Cargo | null;
  cargoMaster?: CargoMaster | null;
  nombre?: string | null;
  orden?: number;
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
  email?: string | null;
  telefono?: string | null;

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

  roles?: UserRole[];
}

export interface Usuario extends UsuarioBase {
  id?: string;
  nombreCompleto?: string;
  cargos?: Cargo[];
  relacionUsuarios?: RelacionUsuario[];
}

export interface UsuarioWrite extends UsuarioBase {
  cargos?: string[];
  relacionUsuarios?: RelacionUsuarioWritePayload[];
}

export interface UsuarioCreatePayload {
  nombre: string;
  apellidos: string;
  email?: string | null;
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
  insertadas?: number;
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
