import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { finalize } from 'rxjs';
import { AuthService } from '../../core/auth/auth';
import { AuthStore } from '../../core/auth/auth-store';
import { CtaButton } from '../../features/shared/components/cta-button/cta-button';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink, CtaButton],
  templateUrl: './login.html',
  styleUrl: './login.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Login {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly authStore = inject(AuthStore);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly loading = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly showValidationMessages = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  constructor() {
    if (this.authStore.isAuthenticated()) {
      void this.router.navigateByUrl('/eventos');
    }
  }

  protected submit(): void {
    this.showValidationMessages.set(true);

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { email, password } = this.form.getRawValue();

    this.loading.set(true);
    this.errorMessage.set(null);

    this.authService
      .authenticate({ email, password })
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: () => {
          const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl') ?? '/eventos';
          void this.router.navigateByUrl(returnUrl);
        },
        error: (error: HttpErrorResponse) => {
          if (error.status === 401) {
            this.errorMessage.set('Email o contraseña incorrectos. Revisalos e intenta de nuevo.');
            return;
          }

          this.errorMessage.set('No pudimos iniciar sesión. Prueba nuevamente en unos minutos.');
        },
      });
  }

  protected hasError(controlName: 'email' | 'password', errorName: string): boolean {
    const control = this.form.controls[controlName];
    const shouldShow = control.touched || control.dirty || this.showValidationMessages();
    return shouldShow && control.hasError(errorName);
  }
}
