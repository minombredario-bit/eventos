export interface AuthUser {
  id?: string | number;
  email: string;
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
