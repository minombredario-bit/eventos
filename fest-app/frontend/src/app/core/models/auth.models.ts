export type TipoEntidad = 'falla' | 'comparsa';

export interface AuthUser {
  id?: string;
  email: string;
  nombre?: string;
  apellidos?: string;
  // FIX: añadido nombreCompleto para evitar el cast `as any` en perfil.ts
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
  // FIX: eliminado [key: string]: unknown — anulaba el tipado estricto de todo el modelo
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
