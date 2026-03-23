import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { AuthStore } from './auth-store';
import { AuthUser, LoginPayload, LoginResponse } from '../models/auth';

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

  isAuthenticated(): boolean {
    return this.authStore.isAuthenticated();
  }

  getToken(): string | null {
    return this.authStore.getToken();
  }

  getUser(): AuthUser | null {
    return this.authStore.user();
  }
}
