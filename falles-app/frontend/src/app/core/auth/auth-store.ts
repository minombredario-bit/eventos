import { Injectable, computed, signal } from '@angular/core';
import { AuthUser, LoginResponse } from '../models/auth.models';

interface PersistedAuthState {
  token: string;
  user: AuthUser;
}

@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly storageKey = 'auth.token';
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

  login(response: LoginResponse): void {
    if (this.isTokenExpired(response.token)) {
      this.logout();
      return;
    }

    this._token.set(response.token);

    const user = response.user ?? this.decodeUserFromToken(response.token);
    this._user.set(user);

    this.persistToStorage({ token: response.token, user: user ?? {} as AuthUser });
  }

  logout(): void {
    this._token.set(null);
    this._user.set(null);
    this.removeFromStorage();
  }

  setUser(user: AuthUser): void {
    this._user.set(user);
    const token = this._token();
    if (!token) return;
    this.persistToStorage({ token, user });
  }

  getToken(): string | null {
    return this._token();
  }

  private decodeUserFromToken(token: string): AuthUser | null {
    try {
      const payload = token.split('.')[1];
      const decoded = JSON.parse(atob(payload));
      return {
        id:        decoded.id       ?? decoded.sub,
        email:     decoded.email    ?? decoded.username ?? decoded.sub ?? '',
        nombre:    decoded.nombre   ?? decoded.name     ?? '',
        apellidos: decoded.apellidos ?? '',
        telefono: decoded.telefono ?? null,
        formaPagoPreferida: decoded.formaPagoPreferida ?? null,
        antiguedad: decoded.antiguedad ?? null,
        antiguedadReal: decoded.antiguedadReal ?? null,
        debeCambiarPassword: Boolean(decoded.debeCambiarPassword ?? false),
        fechaNacimiento: decoded.fechaNacimiento ?? null,
        roles:     decoded.roles    ?? [],
      };
    } catch {
      return null;
    }
  }

  private restoreFromStorage(): void {
    if (typeof localStorage === 'undefined') return;

    const rawState = localStorage.getItem(this.storageKey);
    if (!rawState) return;

    try {
      const state = JSON.parse(rawState) as PersistedAuthState;
      if (!state.token || this.isTokenExpired(state.token)) {
        this.logout();
        return;
      }

      this._token.set(state.token ?? null);

      // Si el user guardado está vacío, redecodifica del token
      const hasUser = state.user && Object.keys(state.user).length > 0;
      this._user.set(hasUser ? state.user : this.decodeUserFromToken(state.token));
    } catch {
      this.removeFromStorage();
    }
  }

  private persistToStorage(state: PersistedAuthState): void {
    if (typeof localStorage === 'undefined') return;
    localStorage.setItem(this.storageKey, JSON.stringify(state));
  }

  private removeFromStorage(): void {
    if (typeof localStorage === 'undefined') return;
    localStorage.removeItem(this.storageKey);
  }

  private isTokenExpired(token: string): boolean {
    try {
      const payload = token.split('.')[1];
      if (!payload) return true;

      const decoded = JSON.parse(atob(payload)) as { exp?: number };
      if (typeof decoded.exp !== 'number') return false;

      const nowInSeconds = Math.floor(Date.now() / 1000);
      return decoded.exp <= nowInSeconds;
    } catch {
      return true;
    }
  }
}
