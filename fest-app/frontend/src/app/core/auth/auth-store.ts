import {Injectable, computed, signal, inject} from '@angular/core';
import {
  AuthUser,
  JwtPayload,
  LoginResponse,
  PersistedAuthState,
} from '../models/auth.models';
import {Router} from '@angular/router';

type SessionResponse = Pick<LoginResponse, 'token'> & { user?: AuthUser };

@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly router = inject(Router);

  private readonly storageKey = 'auth.session';

  private readonly _token = signal<string | null>(null);
  private readonly _user = signal<AuthUser | null>(null);

  readonly token = computed(() => this._token());
  readonly user = computed(() => this._user());
  readonly isAuthenticated = computed(() => {
    const token = this._token();
    const user = this._user();
    return Boolean(token && user && !this.isTokenExpired(token));
  });

  constructor() {
    this.restoreFromStorage();
  }

  login(response: SessionResponse): void {
    if (this.isTokenExpired(response.token)) {
      this.logout();
      return;
    }

    const user = this.resolveUser(response.token, response.user);
    if (!user) {
      this.logout();
      return;
    }

    this._token.set(response.token);
    this._user.set(user);

    this.persistToStorage({
      token: response.token,
      user,
    });
  }

  logout(): void {
    this._token.set(null);
    this._user.set(null);
    this.removeFromStorage();
    this.router.navigateByUrl('/auth/login');
  }

  setUser(user: AuthUser): void {
    this._user.set(user);

    const token = this._token();
    if (!token) {
      return;
    }

    this.persistToStorage({
      token,
      user,
    });
  }

  getToken(): string | null {
    return this._token();
  }

  private resolveUser(token: string, user?: AuthUser | null): AuthUser | null {
    return user ?? this.decodeUserFromToken(token);
  }

  private decodeJwtPayload(token: string): JwtPayload | null {
    try {
      const payloadPart = token.split('.')[1];
      if (!payloadPart) {
        return null;
      }

      const base64 = payloadPart.replace(/-/g, '+').replace(/_/g, '/');
      const padding = '='.repeat((4 - (base64.length % 4)) % 4);
      const binary = atob(base64 + padding);
      const bytes = Uint8Array.from(binary, char => char.charCodeAt(0));
      const json = new TextDecoder().decode(bytes);

      return JSON.parse(json) as JwtPayload;
    } catch {
      return null;
    }
  }

  private decodeUserFromToken(token: string): AuthUser | null {
    const decoded = this.decodeJwtPayload(token);
    if (!decoded) {
      return null;
    }

    const normalizeString = (value: unknown, fallback = ''): string =>
      typeof value === 'string' ? value : fallback;

    const normalizeNullableString = (value: unknown): string | null =>
      typeof value === 'string' ? value : null;

    const normalizeNullableNumber = (value: unknown): number | null =>
      typeof value === 'number' ? value : null;

    const normalizeStringArray = (value: unknown): string[] =>
      Array.isArray(value)
        ? value.filter((item): item is string => typeof item === 'string')
        : [];

    return {
      id: normalizeString(decoded.id ?? decoded.sub, ''),
      email: normalizeString(decoded.email ?? decoded.username ?? decoded.sub, ''),
      nombre: normalizeString(decoded.nombre ?? decoded.name, ''),
      apellidos: normalizeString(decoded.apellidos, ''),
      telefono: normalizeNullableString(decoded.telefono),
      formaPagoPreferida: normalizeNullableString(decoded.formaPagoPreferida),
      antiguedad: normalizeNullableNumber(decoded.antiguedad),
      antiguedadReal: normalizeNullableNumber(decoded.antiguedadReal),
      debeCambiarPassword: Boolean(decoded.debeCambiarPassword ?? false),
      fechaNacimiento: normalizeNullableString(decoded.fechaNacimiento),
      roles: normalizeStringArray(decoded.roles),
      aceptoLopd: Boolean(decoded.aceptoLopd ?? false),
      aceptoLopdAt: typeof decoded.aceptoLopdAt === 'string' ? String(decoded.aceptoLopdAt) : null,
    };
  }

  private restoreFromStorage(): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    const rawState = localStorage.getItem(this.storageKey);
    if (!rawState) {
      this.logout();
      return;
    }

    try {
      const state = JSON.parse(rawState) as Partial<PersistedAuthState>;

      if (!state.token || this.isTokenExpired(state.token)) {
        this.logout();
        return;
      }

      const user = state.user ?? this.decodeUserFromToken(state.token);
      if (!user) {
        this.logout();
        return;
      }

      this._token.set(state.token);
      this._user.set(user);

      this.persistToStorage({
        token: state.token,
        user,
      });
    } catch {
      this.logout();
    }
  }

  private persistToStorage(state: PersistedAuthState): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    localStorage.setItem(this.storageKey, JSON.stringify(state));
  }

  private removeFromStorage(): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    localStorage.removeItem(this.storageKey);
  }

  private isTokenExpired(token: string): boolean {
    const decoded = this.decodeJwtPayload(token);
    if (!decoded) {
      return true;
    }

    if (typeof decoded.exp !== 'number') {
      return false;
    }

    const nowInSeconds = Math.floor(Date.now() / 1000);
    return decoded.exp <= nowInSeconds;
  }

  patchLocalUser(partial: Partial<AuthUser>): void {
    const current = this._user();
    if (!current) return;

    const updated = { ...current, ...partial };
    this._user.set(updated);

    const token = this._token();
    if (token) {
      this.persistToStorage({ token, user: updated });
    }
  }
}
