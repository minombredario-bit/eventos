import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { CtaButton } from '../../../shared/components/cta-button/cta-button';
import { AuthService } from '../../../../core/auth/auth';
import { METODOS_PAGO_OPTIONS, MetodoPago } from '../../domain/eventos.models';

@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [ReactiveFormsModule, MobileHeader, CtaButton],
  templateUrl: './perfil.html',
  styleUrl: './perfil.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Perfil {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly loading = signal(true);
  protected readonly savingProfile = signal(false);
  protected readonly savingPassword = signal(false);
  protected readonly profileMessage = signal<string | null>(null);
  protected readonly passwordMessage = signal<string | null>(null);

  protected readonly metodosPago = METODOS_PAGO_OPTIONS;

  protected readonly profileForm = this.fb.nonNullable.group({
    telefono: [''],
    fechaNacimiento: [''],
    formaPagoPreferida: ['' as '' | MetodoPago],
  });

  protected readonly passwordForm = this.fb.nonNullable.group({
    currentPassword: ['', [Validators.required]],
    newPassword: ['', [Validators.required, Validators.minLength(8)]],
    confirmPassword: ['', [Validators.required]],
  });

  protected canSaveProfile(): boolean {
    return !this.savingProfile();
  }

  protected canSavePassword(): boolean {
    return this.passwordForm.valid && !this.savingPassword();
  }

  constructor() {
    this.loadProfile();
  }

  protected goBack(): void {
    void this.router.navigateByUrl('/eventos/inicio');
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected saveProfile(): void {
    if (!this.canSaveProfile()) {
      return;
    }

    this.profileMessage.set(null);
    this.savingProfile.set(true);

    const { telefono, fechaNacimiento, formaPagoPreferida } = this.profileForm.getRawValue();

    this.authService
      .updateMe({
        telefono: telefono.trim() || null,
        fechaNacimiento: fechaNacimiento || null,
        formaPagoPreferida: formaPagoPreferida || null,
      })
      .pipe(
        finalize(() => this.savingProfile.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.profileForm.markAsPristine();
          this.profileMessage.set('Perfil actualizado correctamente.');
        },
        error: (error) => {
          this.profileMessage.set(this.resolveApiError(error) ?? 'No se pudo actualizar el perfil.');
        },
      });
  }

  protected savePassword(): void {
    if (!this.passwordForm.valid) {
      this.passwordForm.markAllAsTouched();
      return;
    }

    const { currentPassword, newPassword, confirmPassword } = this.passwordForm.getRawValue();
    if (newPassword !== confirmPassword) {
      this.passwordMessage.set('La confirmación de contraseña no coincide.');
      return;
    }

    this.passwordMessage.set(null);
    this.savingPassword.set(true);

    this.authService
      .changePassword(currentPassword, newPassword)
      .pipe(
        finalize(() => this.savingPassword.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.passwordForm.reset();
          this.passwordMessage.set('Contraseña actualizada correctamente.');
        },
        error: (error) => {
          this.passwordMessage.set(this.resolveApiError(error) ?? 'No se pudo cambiar la contraseña.');
        },
      });
  }

  private loadProfile(): void {
    this.loading.set(true);

    this.authService
      .getMe()
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (user) => {
          this.profileForm.setValue({
            telefono: String(user.telefono ?? ''),
            fechaNacimiento: this.normalizeDateForInput(user.fechaNacimiento),
            formaPagoPreferida: this.normalizeMetodoPago(user.formaPagoPreferida),
          });
          this.profileForm.markAsPristine();
        },
        error: () => {
          const fallback = this.authService.getUser();
          this.profileForm.setValue({
            telefono: String(fallback?.telefono ?? ''),
            fechaNacimiento: this.normalizeDateForInput(fallback?.fechaNacimiento),
            formaPagoPreferida: this.normalizeMetodoPago(fallback?.formaPagoPreferida),
          });
          this.profileForm.markAsPristine();
        },
      });
  }

  private normalizeDateForInput(value: unknown): string {
    if (typeof value !== 'string' || value.trim().length === 0) return '';
    const raw = value.trim();
    return raw.includes('T') ? raw.split('T')[0] : raw;
  }

  private normalizeMetodoPago(value: unknown): '' | MetodoPago {
    if (typeof value !== 'string') return '';
    const found = this.metodosPago.find((item) => item.value === value);
    return found ? found.value : '';
  }

  private resolveApiError(error: unknown): string | null {
    if (!error || typeof error !== 'object') return null;
    const source = error as {
      message?: unknown;
      error?: {
        detail?: unknown;
        description?: unknown;
        'hydra:description'?: unknown;
        title?: unknown;
        message?: unknown;
      };
    };

    const candidates = [
      source.error?.detail,
      source.error?.description,
      source.error?.['hydra:description'],
      source.error?.title,
      source.error?.message,
      source.message,
    ];

    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim().length > 0) {
        return candidate.trim();
      }
    }

    return null;
  }
}

