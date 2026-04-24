import { Injectable, inject } from '@angular/core';
import { CanActivate } from '@angular/router';
import {AuthStore} from '../core/auth/auth-store';

@Injectable({ providedIn: 'root' })
export class LopdGuard implements CanActivate {
  private readonly authStore = inject(AuthStore);


  canActivate(): boolean {
    const user = this.authStore.user();
    if (!user) return true;
    return !!user.aceptoLopd;
  }
}
