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
