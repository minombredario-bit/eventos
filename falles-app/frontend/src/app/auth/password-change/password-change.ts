import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../core/auth/auth';

@Component({
  selector: 'app-password-change',
  standalone: true,
  imports: [ReactiveFormsModule],
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

  protected readonly form = this.fb.nonNullable.group({
    currentPassword: ['', [Validators.required]],
    newPassword: ['', [Validators.required, Validators.minLength(8)]],
    confirmPassword: ['', [Validators.required]],
  });

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { currentPassword, newPassword, confirmPassword } = this.form.getRawValue();
    if (newPassword !== confirmPassword) {
      this.errorMessage.set('La confirmación de contraseña no coincide.');
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    this.authService
      .changePassword(currentPassword, newPassword)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => {
          void this.router.navigateByUrl('/eventos');
        },
        error: () => {
          this.errorMessage.set('No se pudo cambiar la contraseña.');
        },
      });
  }
}

