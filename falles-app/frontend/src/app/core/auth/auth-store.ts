import { Injectable, computed, signal } from '@angular/core';
import { AuthUser, LoginResponse } from '../models/auth';

interface PersistedAuthState {
  token: string;
  user: AuthUser;
}

@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly storageKey = 'falles.auth';
  private readonly _token = signal<string | null>(null);
  private readonly _user = signal<AuthUser | null>(null);

  readonly token = computed(() => this._token());
  readonly user = computed(() => this._user());
  readonly isAuthenticated = computed(() => Boolean(this._token()));

  constructor() {
    this.restoreFromStorage();
  }

  login(response: LoginResponse): void {
    this._token.set(response.token);
    this._user.set(response.user);

    this.persistToStorage({
      token: response.token,
      user: response.user,
    });
  }

  logout(): void {
    this._token.set(null);
    this._user.set(null);
    this.removeFromStorage();
  }

  getToken(): string | null {
    return this._token();
  }

  private restoreFromStorage(): void {
    if (typeof localStorage === 'undefined') {
      return;
    }

    const rawState = localStorage.getItem(this.storageKey);
    if (!rawState) {
      return;
    }

    try {
      const state = JSON.parse(rawState) as PersistedAuthState;
      this._token.set(state.token ?? null);
      this._user.set(state.user ?? null);
    } catch {
      this.removeFromStorage();
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
}
