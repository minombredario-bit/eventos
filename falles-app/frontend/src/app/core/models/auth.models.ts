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
