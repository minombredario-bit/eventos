import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../core/auth/auth';
import { CtaButton } from '../../features/shared/components/cta-button/cta-button';

@Component({
  selector: 'app-password-change',
  standalone: true,
  imports: [ReactiveFormsModule, CtaButton],
  templateUrl: './password-change.html',
  styleUrl: './password-change.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PasswordChange {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly loading = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly showValidationMessages = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    currentPassword: ['', [Validators.required]],
    newPassword: ['', [Validators.required, Validators.minLength(8)]],
    confirmPassword: ['', [Validators.required]],
  });

  protected submit(): void {
    this.showValidationMessages.set(true);

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { currentPassword, newPassword, confirmPassword } = this.form.getRawValue();
    if (newPassword !== confirmPassword) {
      this.errorMessage.set('La confirmación de contraseña no coincide.');
      this.form.controls.confirmPassword.setErrors({ mismatch: true });
      return;
    }

    if (this.form.controls.confirmPassword.hasError('mismatch')) {
      this.form.controls.confirmPassword.setErrors(null);
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    this.authService
      .changePassword(currentPassword, newPassword)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => {
          this.form.reset();
          void this.router.navigateByUrl('/eventos');
        },
        error: (error) => {
          this.errorMessage.set(this.resolveApiError(error) ?? 'No se pudo cambiar la contraseña.');
        },
      });
  }

  protected hasError(controlName: 'currentPassword' | 'newPassword' | 'confirmPassword', errorName: string): boolean {
    const control = this.form.controls[controlName];
    const shouldShow = control.touched || control.dirty || this.showValidationMessages();
    return shouldShow && control.hasError(errorName);
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

