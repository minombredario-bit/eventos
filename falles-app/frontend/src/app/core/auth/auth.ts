import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { AuthStore } from './auth-store';
import { AuthUser, LoginPayload, LoginResponse } from '../models/auth.models';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly authStore = inject(AuthStore);
  private readonly apiBaseUrl = 'http://localhost:8080';

  authenticate(payload: LoginPayload): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${this.apiBaseUrl}/api/login`, payload)
      .pipe(tap((response) => this.authStore.login(response)));
  }

  changePassword(currentPassword: string, newPassword: string): Observable<{ ok: boolean }> {
    return this.http.post<{ ok: boolean }>(`${this.apiBaseUrl}/api/me/cambiar-password`, {
      currentPassword,
      newPassword,
    });
  }

  getMe(): Observable<AuthUser> {
    return this.http.get<AuthUser>(`${this.apiBaseUrl}/api/me`).pipe(
      tap((user) => this.authStore.setUser({ ...(this.authStore.user() ?? {} as AuthUser), ...user })),
    );
  }

  updateMe(payload: Partial<Pick<AuthUser, 'telefono' | 'fechaNacimiento' | 'formaPagoPreferida'>>): Observable<AuthUser> {
    return this.http.patch<AuthUser>(`${this.apiBaseUrl}/api/me`, payload).pipe(
      tap((user) => this.authStore.setUser({ ...(this.authStore.user() ?? {} as AuthUser), ...user })),
    );
  }

  logout(): void {
    this.authStore.logout();
  }

  getUser(): AuthUser | null {
    return this.authStore.user();
  }

  get currentUserId(): string | null {
    const id = this.authStore.user()?.id;
    return id != null ? String(id) : null;
  }

  get userSignal() {
    return this.authStore.user;
  }
}
