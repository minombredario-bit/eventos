import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../core/auth/auth';
import { CtaButton } from '../../features/shared/components/cta-button/cta-button';
import { emailOrDocumentValidator } from '../validators/identifier.validator';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, CtaButton],
  templateUrl: './forgot-password.html',
  styleUrl: './forgot-password.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForgotPassword {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);

  protected readonly loading = signal(false);
  protected readonly sent = signal(false);
  protected readonly errorMessage = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    identifier: ['', [Validators.required]],
  });

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    const { identifier } = this.form.getRawValue();

    this.authService
      .forgotPassword(identifier.trim().toLowerCase())
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => this.sent.set(true),
        error: () => {
          this.errorMessage.set(
            'No pudimos procesar la solicitud. Inténtalo de nuevo en unos minutos.',
          );
        },
      });
  }

  protected hasError(controlName: 'identifier', errorName: 'required' | 'invalidIdentifier' | 'minLength'): boolean {
    const control = this.form.controls[controlName];
    return (control.touched || control.dirty) && control.hasError(errorName);
  }
}

