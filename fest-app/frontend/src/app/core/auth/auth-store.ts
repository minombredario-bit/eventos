import { Injectable, computed, signal } from '@angular/core';
import {AuthUser, JwtPayload, LoginResponse, PersistedAuthState} from '../models/auth.models';

type SessionResponse = Pick<LoginResponse, 'token'> & { user?: AuthUser };

@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly storageKey = 'auth.session';

  // claves legacy, solo para migración/limpieza
  private readonly legacyStorageKey = 'auth.token';
  private readonly legacyStorageTokenKey = 'auth.session.token';
  private readonly legacyStorageUserKey = 'auth.session.user';

  private readonly _token = signal<string | null>(null);
  private readonly _user = signal<AuthUser | null>(null);

  readonly token = computed(() => this._token());
  readonly user = computed(() => this._user());
  readonly isAuthenticated = computed(() => {
    const token = this._token();
    return Boolean(token && !this.isTokenExpired(token));
  });

  constructor() {
    this.restoreFromStorage();
  }

  login(response: SessionResponse): void {
    if (this.isTokenExpired(response.token)) {
      this.logout();
      return;
    }

    const user = response.user ?? this.decodeUserFromToken(response.token);

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

  private decodeJwtPayload(token: string): JwtPayload | null {
    try {
      const payloadPart = token.split('.')[1];
      if (!payloadPart) {
        return null;
      }

      const base64 = payloadPart.replace(/-/g, '+').replace(/_/g, '/');
      const padding = '='.repeat((4 - (base64.length % 4)) % 4);
      const decoded = atob(base64 + padding);

      return JSON.parse(decoded) as JwtPayload;
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
      id: decoded.id ?? decoded.sub,
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
      nombreEntidad: undefined,
      tipoEntidad: undefined,
    };
  }

  private restoreFromStorage(): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    const rawState = localStorage.getItem(this.storageKey);

    if (rawState) {
      try {
        const state = JSON.parse(rawState) as PersistedAuthState;

        if (!state.token || this.isTokenExpired(state.token)) {
          this.logout();
          return;
        }

        this._token.set(state.token);
        this._user.set(state.user);
        return;
      } catch {
        this.removeFromStorage();
        return;
      }
    }

    // migración desde formato antiguo
    const rawLegacyState = localStorage.getItem(this.legacyStorageKey);
    const rawLegacyToken = localStorage.getItem(this.legacyStorageTokenKey);
    const rawLegacyUser = localStorage.getItem(this.legacyStorageUserKey);

    if (!rawLegacyState && !rawLegacyToken) {
      return;
    }

    try {
      let state: PersistedAuthState | null = null;

      if (rawLegacyState) {
        const parsed = JSON.parse(rawLegacyState) as Partial<PersistedAuthState>;

        if (
          typeof parsed.token === 'string' &&
          parsed.token &&
          parsed.user &&
          typeof parsed.user === 'object'
        ) {
          state = {
            token: parsed.token,
            user: parsed.user as AuthUser,
          };
        }
      }

      if (!state && rawLegacyToken && rawLegacyUser) {
        state = {
          token: rawLegacyToken,
          user: JSON.parse(rawLegacyUser) as AuthUser,
        };
      }

      if (!state || !state.token || this.isTokenExpired(state.token)) {
        this.logout();
        return;
      }

      this._token.set(state.token);
      this._user.set(state.user);

      // migrar a la nueva clave única
      this.persistToStorage(state);

      // limpiar claves antiguas
      localStorage.removeItem(this.legacyStorageKey);
      localStorage.removeItem(this.legacyStorageTokenKey);
      localStorage.removeItem(this.legacyStorageUserKey);
    } catch {
      this.removeFromStorage();
    }
  }

  private persistToStorage(state: PersistedAuthState): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    localStorage.setItem(this.storageKey, JSON.stringify(state));

    // limpieza defensiva de claves antiguas
    localStorage.removeItem(this.legacyStorageKey);
    localStorage.removeItem(this.legacyStorageTokenKey);
    localStorage.removeItem(this.legacyStorageUserKey);
  }

  private removeFromStorage(): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    localStorage.removeItem(this.storageKey);
    localStorage.removeItem(this.legacyStorageKey);
    localStorage.removeItem(this.legacyStorageTokenKey);
    localStorage.removeItem(this.legacyStorageUserKey);
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
}
