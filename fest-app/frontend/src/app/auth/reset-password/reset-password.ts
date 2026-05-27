import { ChangeDetectionStrategy, Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../core/auth/auth';
import { CtaButton } from '../../features/shared/components/cta-button/cta-button';

@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink, CtaButton],
  templateUrl: './reset-password.html',
  styleUrl: './reset-password.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ResetPassword implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly loading = signal(false);
  protected readonly success = signal(false);
  protected readonly tokenInvalid = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly showPassword = signal(false);
  protected readonly showConfirm = signal(false);

  private token = '';

  static passwordsMatch(control: AbstractControl): ValidationErrors | null {
    const newPassword = control.get('newPassword')?.value;
    const confirmPassword = control.get('confirmPassword')?.value;
    if (newPassword && confirmPassword && newPassword !== confirmPassword) {
      return { passwordsMismatch: true };
    }
    return null;
  }

  protected readonly form = this.fb.nonNullable.group(
    {
      newPassword: ['', [Validators.required, Validators.minLength(8)]],
      confirmPassword: ['', [Validators.required]],
    },
    { validators: ResetPassword.passwordsMatch },
  );

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParamMap.get('token') ?? '';
    if (!this.token) {
      this.tokenInvalid.set(true);
    }
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    const { newPassword } = this.form.getRawValue();

    this.authService
      .resetPassword(this.token, newPassword)
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => {
          this.success.set(true);
          setTimeout(() => void this.router.navigateByUrl('/auth/login'), 3000);
        },
        error: (err) => {
          const msg = err?.error?.detail
            ?? err?.error?.message
            ?? 'El enlace no es válido o ha expirado.';
          this.errorMessage.set(msg);
        },
      });
  }

  protected hasError(controlName: 'newPassword' | 'confirmPassword', errorName: string): boolean {
    const control = this.form.controls[controlName];
    return (control.touched || control.dirty) && control.hasError(errorName);
  }

  protected hasFormError(errorName: string): boolean {
    const c = this.form.controls.confirmPassword;
    return (c.touched || c.dirty) && !!this.form.hasError(errorName);
  }
}

