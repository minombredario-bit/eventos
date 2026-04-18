import {Injectable, signal} from '@angular/core';

export type TipoEntidad = 'Falla' | 'Comparsa';

export interface AuthUser {
  id?: string | number;
  email: string;
  nombre?: string;
  apellidos?: string;
  telefono?: string | null;
  formaPagoPreferida?: string | null;
  antiguedad?: number | null;
  antiguedadReal?: number | null;
  debeCambiarPassword?: boolean;
  fechaNacimiento?: string | null;
  roles?: string[];
  nombreEntidad?: string;
  tipoEntidad?: TipoEntidad | null;
  [key: string]: unknown;
}

export interface LoginPayload {
  email: string;
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
  exp?: number;
  sub?: string;
  id?: string | number;
  email?: string;
  username?: string;
  nombre?: string;
  name?: string;
  apellidos?: string;
  telefono?: string | null;
  formaPagoPreferida?: string | null;
  antiguedad?: number | null;
  antiguedadReal?: number | null;
  debeCambiarPassword?: boolean;
  fechaNacimiento?: string | null;
  roles?: string[];
}
