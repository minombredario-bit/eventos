export type EstadoValidacionAdmin = 'pendiente_validacion' | 'validado' | 'rechazado' | 'bloqueado' | string;
export type TipoUsuarioEconomicoAdmin = 'interno' | 'externo' | 'invitado' | string;

export interface AdminUsuario {
  id: string;
  nombre: string;
  apellidos: string;
  nombreCompleto: string;
  email: string;
  telefono: string | null;
  antiguedad: number | null;
  estadoValidacion: EstadoValidacionAdmin;
  tipoUsuarioEconomico: TipoUsuarioEconomicoAdmin;
  censadoVia: string | null;
  activo: boolean;
  fechaAltaCenso: string | null;
  fechaBajaCenso: string | null;
  fechaSolicitudAlta: string | null;
}

export interface AdminCrearUsuarioPayload {
  nombre: string;
  apellidos: string;
  email: string;
  password: string;
  telefono?: string | null;
  tipoUsuarioEconomico?: 'interno' | 'externo' | 'invitado';
}

export interface AdminActualizarUsuarioPayload {
  nombre?: string;
  apellidos?: string;
  telefono?: string | null;
  antiguedad?: number | null;
  tipoUsuarioEconomico?: 'interno' | 'externo' | 'invitado';
  activo?: boolean;
}

export interface AdminImportResult {
  total: number;
  insertadas: number;
  errores: Array<{ fila?: number; motivo?: string }>;
}

export type AdminUsuariosFiltro = 'todos' | 'censado' | 'no_censado';

export interface AdminUsuariosPage {
  items: AdminUsuario[];
  totalItems: number;
  page: number;
  itemsPerPage: number;
  hasNext: boolean;
  hasPrevious: boolean;
}

