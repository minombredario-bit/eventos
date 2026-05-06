import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import {
  FormBuilder,
  ReactiveFormsModule,
  Validators,
  AbstractControl,
  ValidationErrors
} from '@angular/forms';
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

  // ✅ Validador: email o DNI/NIE
  static identificador(control: AbstractControl): ValidationErrors | null {
    const raw = (control.value || '').toString().trim();
    if (!raw) return null;

    const value = raw.toUpperCase();

    // Email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // DNI: 12345678A
    const dniRegex = /^[0-9]{8}[A-Z]$/;

    // NIE: X1234567A
    const nieRegex = /^[XYZ][0-9]{7}[A-Z]$/;

    if (emailRegex.test(raw)) return null;
    if (dniRegex.test(value)) return null;
    if (nieRegex.test(value)) return null;

    return { identificador: true };
  }

  protected readonly form = this.fb.nonNullable.group({
    identificador: ['', [Validators.required, Login.identificador]],
    password: ['', [Validators.required]],
  });

  constructor() {
    const user = this.authStore.user();

    if (this.authStore.isAuthenticated() && user) {
      if (user.debeCambiarPassword) {
        void this.router.navigateByUrl('/auth/cambiar-password');
      } else if (user.aceptoLopd === false) {
        void this.router.navigateByUrl('/lopd');
      } else {
        void this.router.navigateByUrl('/eventos');
      }
    }
  }

  protected submit(): void {
    this.showValidationMessages.set(true);

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { identificador, password } = this.form.getRawValue();

    // ✅ Normalización antes de enviar
    const identificadorNormalizado = identificador.includes('@')
      ? identificador.trim().toLowerCase()
      : identificador.trim().toUpperCase();

    this.loading.set(true);
    this.errorMessage.set(null);

    this.authService
      .authenticate({ identificador: identificadorNormalizado, password })
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: (response) => {
          const user = response.user;

          if (Boolean(user?.debeCambiarPassword)) {
            void this.router.navigateByUrl('/auth/cambiar-password');
            return;
          }

          if (!Boolean(user?.aceptoLopd)) {
            void this.router.navigate(['/lopd'], { queryParams: { userId: user?.id } });
            return;
          }

          const rawReturnUrl = this.route.snapshot.queryParamMap.get('returnUrl') ?? '/eventos';
          const safeReturnUrl =
            rawReturnUrl.startsWith('/') && !rawReturnUrl.startsWith('//')
              ? rawReturnUrl
              : '/eventos';

          void this.router.navigateByUrl(safeReturnUrl);
        },
        error: (error: HttpErrorResponse) => {
          if (error.status === 401) {
            this.errorMessage.set(
              'Identificador o contraseña incorrectos. Revísalos e intenta de nuevo.'
            );
            return;
          }

          this.errorMessage.set(
            'No pudimos iniciar sesión. Prueba nuevamente en unos minutos.'
          );
        },
      });
  }

  protected hasError(controlName: 'identificador' | 'password', errorName: string): boolean {
    const control = this.form.controls[controlName];
    const shouldShow = control.touched || control.dirty || this.showValidationMessages();
    return shouldShow && control.hasError(errorName);
  }
}
