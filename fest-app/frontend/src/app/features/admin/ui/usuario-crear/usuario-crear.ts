import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { AdminApi } from '../../data/admin.api';
import { AdminCrearUsuarioPayload } from '../../domain/admin.models';

@Component({
  selector: 'app-admin-usuario-crear',
  standalone: true,
  imports: [MobileHeader, ReactiveFormsModule],
  templateUrl: './usuario-crear.html',
  styleUrl: './usuario-crear.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminUsuarioCrear {
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi = inject(AdminApi);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    nombre: ['', [Validators.required, Validators.minLength(2)]],
    apellidos: ['', [Validators.required, Validators.minLength(2)]],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    telefono: [''],
    tipoUsuarioEconomico: this.fb.nonNullable.control<'interno' | 'externo' | 'invitado'>('interno'),
  });

  protected goBack(): void {
    void this.router.navigate(['/admin/censo-usuarios']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected submit(): void {
    this.errorMessage.set(null);
    this.successMessage.set(null);

    if (this.form.invalid || this.saving()) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    const value = this.form.getRawValue();

    const payload: AdminCrearUsuarioPayload = {
      nombre: value.nombre.trim(),
      apellidos: value.apellidos.trim(),
      email: value.email.trim().toLowerCase(),
      password: value.password,
      telefono: value.telefono.trim() || null,
      tipoUsuarioEconomico: value.tipoUsuarioEconomico,
    };

    this.adminApi
      .crearUsuario(payload)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (created) => {
          this.successMessage.set('Usuario creado correctamente.');
          void this.router.navigate(['/admin/usuarios', created.id]);
        },
        error: (error: { error?: { error?: string } }) => {
          this.errorMessage.set(error?.error?.error ?? 'No se pudo crear el usuario.');
        },
      });
  }
}

