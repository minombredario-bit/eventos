import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, finalize, map, switchMap } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { AdminApi } from '../../data/admin.api';
import { AdminUsuario } from '../../domain/admin.models';

@Component({
  selector: 'app-admin-usuario-perfil',
  standalone: true,
  imports: [MobileHeader, ReactiveFormsModule],
  templateUrl: './usuario-perfil.html',
  styleUrl: './usuario-perfil.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminUsuarioPerfil {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi = inject(AdminApi);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly editMode = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly usuario = signal<AdminUsuario | null>(null);
  protected readonly userId = signal<string | null>(null);

  protected readonly editForm = this.fb.nonNullable.group({
    nombre: ['', [Validators.required, Validators.minLength(2)]],
    apellidos: ['', [Validators.required, Validators.minLength(2)]],
    telefono: [''],
    antiguedad: this.fb.control<number | null>(null),
    tipoUsuarioEconomico: this.fb.nonNullable.control<'interno' | 'externo' | 'invitado'>('interno'),
    activo: this.fb.nonNullable.control(true),
  });

  constructor() {
    this.route.paramMap
      .pipe(
        map((params) => params.get('id') ?? ''),
        filter((id) => id.trim().length > 0),
        distinctUntilChanged(),
        switchMap((id) => this.adminApi.getUsuario(id.trim())),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (usuario) => {
          this.userId.set(usuario.id);
          this.usuario.set(usuario);
          this.patchForm(usuario);
          this.loading.set(false);
        },
        error: (error: { error?: { error?: string } }) => {
          this.errorMessage.set(error?.error?.error ?? 'No se pudo cargar el perfil de usuario.');
          this.loading.set(false);
        },
      });
  }

  protected goBack(): void {
    void this.router.navigate(['/admin/censo-usuarios']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected toggleEditMode(): void {
    const usuario = this.usuario();
    if (!usuario) {
      return;
    }

    this.errorMessage.set(null);
    this.successMessage.set(null);
    this.editMode.update((value) => !value);

    if (this.editMode()) {
      this.patchForm(usuario);
    }
  }

  protected saveChanges(): void {
    const id = this.userId();
    if (!id || this.editForm.invalid || this.saving()) {
      this.editForm.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.errorMessage.set(null);
    this.successMessage.set(null);

    const value = this.editForm.getRawValue();

    this.adminApi
      .updateUsuario(id, {
        nombre: value.nombre.trim(),
        apellidos: value.apellidos.trim(),
        telefono: value.telefono.trim() || null,
        antiguedad: value.antiguedad,
        tipoUsuarioEconomico: value.tipoUsuarioEconomico,
        activo: value.activo,
      })
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (usuarioActualizado) => {
          this.usuario.set(usuarioActualizado);
          this.patchForm(usuarioActualizado);
          this.editMode.set(false);
          this.successMessage.set('Usuario actualizado correctamente.');
        },
        error: (error: { error?: { error?: string } }) => {
          this.errorMessage.set(error?.error?.error ?? 'No se pudo actualizar el usuario.');
        },
      });
  }

  protected fullName(usuario: AdminUsuario | null): string {
    if (!usuario) {
      return '';
    }

    return `${usuario.nombre} ${usuario.apellidos}`.trim() || 'Usuario';
  }

  protected boolLabel(value: boolean): string {
    return value ? 'Sí' : 'No';
  }

  protected antiguedadLabel(value: number | null): string {
    return value === null ? '-' : String(value);
  }

  private patchForm(usuario: AdminUsuario): void {
    this.editForm.reset({
      nombre: usuario.nombre,
      apellidos: usuario.apellidos,
      telefono: usuario.telefono ?? '',
      antiguedad: usuario.antiguedad,
      tipoUsuarioEconomico: usuario.tipoUsuarioEconomico === 'externo' || usuario.tipoUsuarioEconomico === 'invitado'
        ? usuario.tipoUsuarioEconomico
        : 'interno',
      activo: usuario.activo,
    });
  }
}

