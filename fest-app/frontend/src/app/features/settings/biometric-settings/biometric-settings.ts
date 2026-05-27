import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { BiometricService, PasskeyCredentialInfo } from '../../../core/services/biometric.service';
import {CtaButton} from '../../shared/components/cta-button/cta-button';

@Component({
  selector: 'app-biometric-settings',
  standalone: true,
  imports: [CommonModule, RouterLink, CtaButton],
  templateUrl: './biometric-settings.html',
  styleUrl: './biometric-settings.scss',
})
export class BiometricSettings {
  private readonly biometricService = inject(BiometricService);

  readonly credentials = signal<PasskeyCredentialInfo[]>([]);
  readonly loading = signal(false);
  readonly registering = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly successMessage = signal<string | null>(null);
  readonly isSupported = this.biometricService.isSupported;

  constructor() {
    this.loadCredentials();
  }

  private loadCredentials(): void {
    this.loading.set(true);
    this.biometricService.listCredentials().subscribe({
      next: (creds) => {
        this.credentials.set(creds);
        this.loading.set(false);
      },
      error: () => {
        this.credentials.set([]);
        this.loading.set(false);
      },
    });
  }

  registerNewPasskey(): void {
    if (this.registering()) return;

    this.registering.set(true);
    this.errorMessage.set(null);
    this.successMessage.set(null);

    const deviceName = this.getDeviceName();

    this.biometricService
      .registerPasskey(deviceName)
      .then(() => {
        this.successMessage.set(`✅ ${deviceName} registrado con éxito`);
        this.registering.set(false);
        setTimeout(() => this.loadCredentials(), 500);
      })
      .catch((err: unknown) => {
        const msg = err instanceof Error ? err.message : 'Error al registrar';
        if (!msg.toLowerCase().includes('cancel') && !msg.toLowerCase().includes('user')) {
          this.errorMessage.set(msg);
        }
        this.registering.set(false);
      });
  }

  deleteCredential(id: string): void {
    if (confirm('¿Eliminar este dispositivo?')) {
      this.biometricService.deleteCredential(id).subscribe({
        next: () => {
          this.successMessage.set('Dispositivo eliminado');
          this.loadCredentials();
        },
        error: () => {
          this.errorMessage.set('No se pudo eliminar el dispositivo');
        },
      });
    }
  }

  private getDeviceName(): string {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    if (/Android/.test(ua)) return 'Android';
    if (/Macintosh/.test(ua)) return 'Mac';
    if (/Windows/.test(ua)) return 'Windows';
    return 'Dispositivo';
  }
}

