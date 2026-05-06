import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map, tap } from 'rxjs';
import { AuthStore } from './auth-store';
import { environment } from '../../../environments/environment';
import {
  AuthUser,
  ChangePasswordResponse,
  LoginPayload,
  LoginResponse,
} from '../models/auth.models';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly authStore = inject(AuthStore);

  authenticate(payload: LoginPayload): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse | Record<string, unknown>>(
        `${environment.apiUrl}/login`,
        payload,
      )
      .pipe(
        map((response) => this.normalizeLoginResponse(response)),
        tap((response) => this.authStore.login(response)),
      );
  }

  changePassword(
    currentPassword: string,
    newPassword: string,
  ): Observable<ChangePasswordResponse> {
    return this.http
      .post<ChangePasswordResponse>(
        `${environment.apiUrl}/me/cambiar-password`,
        {
          currentPassword,
          newPassword,
        },
      )
      .pipe(
        tap((response) => {
          if (!response.token) {
            return;
          }

          this.authStore.login({
            token: response.token,
            user: response.user ?? this.authStore.user()!,
          });
        }),
      );
  }

  getMe(): Observable<AuthUser> {
    return this.http.get<AuthUser>(`${environment.apiUrl}/me`).pipe(
      tap((user) => {
        const currentUser = this.authStore.user();
        this.authStore.setUser({
          ...(currentUser ?? {}),
          ...user,
        } as AuthUser);
      }),
    );
  }

  updateMe(
    payload: Partial<
      Pick<AuthUser, 'nombre' | 'apellidos' | 'telefono' | 'direccion' | 'fechaNacimiento' | 'formaPagoPreferida'>
    >,
  ): Observable<AuthUser> {
    return this.http
      .patch<AuthUser>(`${environment.apiUrl}/me`, payload)
      .pipe(
        tap((user) => {
          const currentUser = this.authStore.user();
          this.authStore.setUser({
            ...(currentUser ?? {}),
            ...user,
          } as AuthUser);
        }),
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

  hasAnyRole(roles: string[]): boolean {
    const userRoles = this.authStore.user()?.roles ?? [];
    if (!Array.isArray(userRoles)) return false;
    return roles.some((r) => userRoles.includes(r));
  }

  private normalizeLoginResponse(
    response: LoginResponse | Record<string, unknown>,
  ): LoginResponse {
    if (typeof response !== 'object' || response === null) {
      throw new Error('Respuesta de login no válida');
    }

    const source = response as Record<string, unknown>;
    const tokenCandidate =
      source['token'] ?? source['jwt'] ?? source['access_token'];
    const token = typeof tokenCandidate === 'string' ? tokenCandidate : '';

    if (!token) {
      throw new Error('Respuesta de login sin token');
    }

    const userCandidate = source['user'];

    if (typeof userCandidate !== 'object' || userCandidate === null) {
      throw new Error('Respuesta de login sin usuario');
    }

    return {
      token,
      user: userCandidate as AuthUser,
    };
  }
}
