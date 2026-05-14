import {TipoPersona} from '../../features/admin/domain/admin.models';

export type TipoEntidad = 'falla' | 'comparsa';

export interface AuthUser {
  id?: string;
  email: string;
  direccion?: string | null;
  nombre?: string | null;
  apellidos?: string | null;
  nombreCompleto?: string;
  telefono?: string | null;
  formaPagoPreferida?: string | null;
  antiguedad?: number | null;
  antiguedadReal?: number | null;
  debeCambiarPassword?: boolean;
  fechaNacimiento?: string | null;
  roles?: string[];
  nombreEntidad?: string;
  tipoEntidad?: TipoEntidad | null;
  aceptoLopd?: boolean;
  aceptoLopdAt?: string | null;
  personType?: TipoPersona;
}

export interface LoginPayload {
  identificador: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  user: AuthUser;
}

export interface ChangePasswordResponse {
  ok: boolean;
  token?: string;
  user?: AuthUser;
}

export interface PersistedAuthState {
  token: string;
  user: AuthUser;
}

export interface JwtPayload {
  address: any;
  direccion: any;
  iat?: number;
  exp?: number;
  sub?: string;
  id?: string | number;
  email?: string;
  username?: string;
  nombre?: string;
  name?: string;
  apellidos?: string;
  nombreCompleto?: string;
  telefono?: string | null;
  formaPagoPreferida?: string | null;
  antiguedad?: number | null;
  antiguedadReal?: number | null;
  debeCambiarPassword?: boolean;
  fechaNacimiento?: string | null;
  roles?: string[];
  nombreEntidad?: string;
  tipoEntidad?: TipoEntidad | null;
  aceptoLopd?: boolean;
  aceptoLopdAt?: string | null;
}
