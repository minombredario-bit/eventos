import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  debounceTime,
  distinctUntilChanged,
  finalize,
  map,
  of,
  Subject,
  startWith,
  switchMap,
} from 'rxjs';

import { AuthService } from '../../../../core/auth/auth';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { AdminApi } from '../../data/admin.api';
import {
  Cargo,
  CargoTipoPersona,
  EnumOption,
  MetodoPagoPreferida,
  TipoRelacion,
  Usuario,
  UsuarioCreatePayload,
  UsuarioPatch,
  UsuarioRelacionadoSeleccionado,
  UserRole,
} from '../../domain/admin.models';
import {TranslateModule} from '@ngx-translate/core';

@Component({
  selector: 'app-admin-usuario-form',
  standalone: true,
  imports: [CommonModule, MobileHeader, ReactiveFormsModule, TranslateModule],
  templateUrl: './usuario-form.html',
  styleUrl: './usuario-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminUsuarioForm {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi = inject(AdminApi);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  private readonly relacionUsuarioSearch$ = new Subject<string>();

  protected readonly loading = signal(false);
  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);

  protected readonly usuario = signal<Usuario | null>(null);
  protected readonly userId = signal<string | null>(null);

  protected readonly cargos = signal<Cargo[]>([]);
  protected readonly cargosSeleccionados = signal<string[]>([]);
  protected readonly tipoPersonaCargo = signal<CargoTipoPersona>('adulto');

  protected readonly tiposRelacion = signal<EnumOption<TipoRelacion>[]>([]);

  protected readonly usuariosRelacionadosDisponibles = signal<Usuario[]>([]);
  protected readonly usuariosRelacionadosSeleccionados =
    signal<UsuarioRelacionadoSeleccionado[]>([]);

  protected readonly isEditMode = computed(() => !!this.userId());

  protected readonly pageTitle = computed(() =>
    this.isEditMode() ? 'Editar usuario' : 'Nuevo usuario'
  );

  protected readonly submitLabel = computed(() => {
    if (this.saving()) {
      return this.isEditMode() ? 'Guardando...' : 'Creando...';
    }

    return this.isEditMode() ? 'Guardar cambios' : 'Crear usuario';
  });

  protected readonly form = this.fb.group({
    nombre: this.fb.nonNullable.control('', [
      Validators.required,
      Validators.minLength(2),
    ]),
    apellidos: this.fb.nonNullable.control('', [
      Validators.required,
      Validators.minLength(2),
    ]),
    email: this.fb.nonNullable.control('', [Validators.email]),
    telefono: this.fb.nonNullable.control(''),

    activo: this.fb.nonNullable.control(true),
    motivoBajaCenso: this.fb.control<string | null>(null),

    fechaNacimiento: this.fb.control<string | null>(null, [
      Validators.required,
    ]),

    antiguedad: this.fb.control<number | null>(null),
    antiguedadReal: this.fb.control<number | null>(null),

    formaPagoPreferida: this.fb.nonNullable.control<MetodoPagoPreferida>(
      'efectivo'
    ),

    debeCambiarPassword: this.fb.nonNullable.control(true),

    role: this.fb.nonNullable.control<UserRole>('ROLE_USER'),

    relacionUsuarioSearch: this.fb.nonNullable.control(''),
  });

  constructor() {
    this.loadTiposRelacion();
    this.initCargoTypeWatcher();
    this.initRelacionUsuariosSearch();

    this.route.paramMap
      .pipe(
        map((params) => params.get('id')),
        distinctUntilChanged(),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe((id) => {
        this.errorMessage.set(null);
        this.successMessage.set(null);

        if (!id?.trim()) {
          this.userId.set(null);
          this.usuario.set(null);
          this.cargosSeleccionados.set([]);
          this.usuariosRelacionadosSeleccionados.set([]);
          this.resetForCreate();
          this.applyFieldPermissions();
          return;
        }

        this.loading.set(true);

        this.adminApi
          .getUsuarioAdmin(id.trim())
          .pipe(
            finalize(() => this.loading.set(false)),
            takeUntilDestroyed(this.destroyRef)
          )
          .subscribe({
            next: (usuario) => {
              this.userId.set(usuario.id ?? null);
              this.usuario.set(usuario);
              this.patchForm(usuario);
              this.patchCargos(usuario);
              this.patchRelacionUsuarios(usuario);
              this.applyFieldPermissions();
            },
            error: (error: { error?: { error?: string } }) => {
              this.errorMessage.set(
                error?.error?.error ?? 'No se pudo cargar el usuario.'
              );
            },
          });
      });
  }

  protected submit(): void {
    this.errorMessage.set(null);
    this.successMessage.set(null);

    if (this.form.invalid || this.saving()) {
      this.form.markAllAsTouched();
      if (this.form.invalid) {
        this.errorMessage.set(
          'Revisa los campos obligatorios antes de guardar los cambios.'
        );
      }
      return;
    }

    this.isEditMode() ? this.updateUsuario() : this.createUsuario();
  }

  protected goBack(): void {
    void this.router.navigate(['/admin/censo-usuarios']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected isCargoSelected(id: string): boolean {
    return this.cargosSeleccionados().includes(id);
  }


  protected toggleCargo(id: string): void {
    const current = this.cargosSeleccionados();

    if (current.includes(id)) {
      this.cargosSeleccionados.set(current.filter((x) => x !== id));
      return;
    }

    this.cargosSeleccionados.set([...current, id]);
  }

  protected onRelacionUsuarioSearch(value: string): void {
    this.relacionUsuarioSearch$.next(value);
  }

  protected isUsuarioRelacionadoSelected(id: string | undefined): boolean {
    if (!id) {
      return false;
    }

    return this.usuariosRelacionadosSeleccionados().some(
      (item) => item.id === id
    );
  }

  protected addUsuarioRelacionado(usuario: Usuario): void {
    if (!usuario.id || this.isUsuarioRelacionadoSelected(usuario.id)) {
      return;
    }

    if (usuario.id === this.userId()) {
      return;
    }

    this.usuariosRelacionadosSeleccionados.set([
      ...this.usuariosRelacionadosSeleccionados(),
      {
        id: usuario.id,
        nombreCompleto: usuario.nombreCompleto ?? '',
        tipoRelacion: null,
      },
    ]);

    // Clear the search input and suggestion list so the search disappears
    try {
      this.form.controls.relacionUsuarioSearch.setValue('', { emitEvent: false });
    } catch (e) {
      // ignore if control not present
    }

    this.usuariosRelacionadosDisponibles.set([]);
  }

  protected removeUsuarioRelacionado(id: string): void {
    const eliminados = this.usuariosRelacionadosSeleccionados().filter(
      (item) => item.id === id
    );

    this.usuariosRelacionadosSeleccionados.set(
      this.usuariosRelacionadosSeleccionados().filter((item) => item.id !== id)
    );

    if (eliminados.length > 0) {
      this.usuariosRelacionadosDisponibles.set([
        ...this.usuariosRelacionadosDisponibles(),
        ...eliminados.map(
          (item) =>
            ({
              id: item.id,
              nombreCompleto: item.nombreCompleto,
            }) as Usuario
        ),
      ]);
    }
  }

  protected setTipoRelacionUsuario(id: string, tipoRelacion: string): void {
    const value = this.normalizeTipoRelacion(tipoRelacion);

    this.usuariosRelacionadosSeleccionados.set(
      this.usuariosRelacionadosSeleccionados().map((item) =>
        item.id === id ? { ...item, tipoRelacion: value } : item
      )
    );
  }

  private createUsuario(): void {
    this.saving.set(true);
    this.errorMessage.set(null);

    const value = this.form.getRawValue();

    const relacionesInvalidas = this.usuariosRelacionadosSeleccionados().some(
      (item) => !item.id || !item.tipoRelacion
    );

    if (relacionesInvalidas) {
      this.errorMessage.set(
        'Debes indicar el tipo de relación de cada usuario relacionado.'
      );
      this.saving.set(false);
      return;
    }

    const roles: UserRole[] = [value.role];

    const payload: UsuarioCreatePayload = {
      nombre: value.nombre.trim(),
      apellidos: value.apellidos.trim(),
      email: (() => {
        const e = value.email.trim();
        return e ? e.toLowerCase() : null;
      })(),
      telefono: value.telefono.trim() || null,
      activo: value.activo,
      motivoBajaCenso: value.activo
        ? null
        : (value.motivoBajaCenso?.trim() || null),
      fechaNacimiento: value.fechaNacimiento,
      antiguedad: value.antiguedad,
      antiguedadReal: value.antiguedadReal,
      formaPagoPreferida: value.formaPagoPreferida,
      debeCambiarPassword: value.debeCambiarPassword,
      roles,
      cargos: this.buildCargoIris(this.cargosSeleccionados()),
      relacionUsuarios: this.usuariosRelacionadosSeleccionados().map(
        (item) => ({
          usuario: `/api/usuarios/${item.id}`,
          tipoRelacion: item.tipoRelacion as TipoRelacion,
        })
      ),
    };

    this.adminApi
      .crearUsuario(payload)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (created) => {
          this.successMessage.set('Usuario creado correctamente.');
          void this.router.navigate(['/admin/usuarios', created.id]);
        },
        error: (error: { error?: { detail?: string; error?: string } }) => {
          this.errorMessage.set(
            error?.error?.detail ??
            error?.error?.error ??
            'No se pudo crear el usuario.'
          );
        },
      });
  }

  private updateUsuario(): void {
    const id = this.userId();

    if (!id) {
      return;
    }

    this.saving.set(true);
    this.errorMessage.set(null);

    const value = this.form.getRawValue();

    const relacionesInvalidas = this.usuariosRelacionadosSeleccionados().some(
      (item) => !item.id || !item.tipoRelacion
    );

    if (relacionesInvalidas) {
      this.errorMessage.set(
        'Debes indicar el tipo de relación de cada usuario relacionado.'
      );
      this.saving.set(false);
      return;
    }

    const roles: UserRole[] = [value.role];

    const payload: UsuarioPatch = {
      nombre: value.nombre.trim(),
      apellidos: value.apellidos.trim(),
      email: (() => {
        const e = value.email.trim();
        return e ? e.toLowerCase() : null;
      })(),
      telefono: value.telefono.trim() || null,
      activo: value.activo,
      motivoBajaCenso: value.activo
        ? null
        : (value.motivoBajaCenso?.trim() || null),
      antiguedad: value.antiguedad,
      antiguedadReal: value.antiguedadReal,
      fechaNacimiento: value.fechaNacimiento,
      formaPagoPreferida: value.formaPagoPreferida,
      debeCambiarPassword: value.debeCambiarPassword,
      roles,
      cargos: this.buildCargoIris(this.cargosSeleccionados()),
      relacionUsuarios: this.usuariosRelacionadosSeleccionados().map(
        (item) => ({
          usuario: `/api/usuarios/${item.id}`,
          tipoRelacion: item.tipoRelacion as TipoRelacion,
        })
      ),
    };

    this.adminApi
      .updateUsuario(id, payload)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (usuarioActualizado) => {
          this.usuario.set(usuarioActualizado);
          this.patchForm(usuarioActualizado);
          this.patchCargos(usuarioActualizado);
          this.patchRelacionUsuarios(usuarioActualizado);
          this.applyFieldPermissions();
          this.successMessage.set('Usuario actualizado correctamente.');
        },
        error: (error: { error?: { detail?: string; error?: string } }) => {
          this.errorMessage.set(
            error?.error?.detail ??
            error?.error?.error ??
            'No se pudo actualizar el usuario.'
          );
        },
      });
  }

  private resetForCreate(): void {
    this.form.reset({
      nombre: '',
      apellidos: '',
      email: '',
      telefono: '',
      activo: true,
      motivoBajaCenso: null,
      fechaNacimiento: null,
      antiguedad: null,
      antiguedadReal: null,
      formaPagoPreferida: 'efectivo',
      debeCambiarPassword: true,
      role: 'ROLE_USER',
      relacionUsuarioSearch: '',
    });

    this.usuariosRelacionadosSeleccionados.set([]);
    this.usuariosRelacionadosDisponibles.set([]);
  }

  private patchForm(usuario: Usuario): void {
    this.form.reset({
      nombre: usuario.nombre ?? '',
      apellidos: usuario.apellidos ?? '',
      email: usuario.email ?? '',
      telefono: usuario.telefono ?? '',
      activo: usuario.activo ?? true,
      motivoBajaCenso: usuario.motivoBajaCenso ?? null,
      fechaNacimiento: this.toDateInputValue(usuario.fechaNacimiento),
      antiguedad: usuario.antiguedad ?? null,
      antiguedadReal: usuario.antiguedadReal ?? null,
      formaPagoPreferida: usuario.formaPagoPreferida ?? 'efectivo',
      debeCambiarPassword: usuario.debeCambiarPassword ?? true,
      role: this.parseRole(usuario.roles),
      relacionUsuarioSearch: '',
    });
  }

  private patchCargos(usuario: Usuario): void {
    const uniqueCargoIds = new Set(
      (usuario.cargos ?? [])
        .map((cargo) => this.getCargoSelectionKey(cargo))
        .filter((cargoId): cargoId is string => cargoId.length > 0)
    );

    this.cargosSeleccionados.set([...uniqueCargoIds]);
  }

  private patchRelacionUsuarios(usuario: Usuario): void {
    const relaciones = (usuario.relacionUsuarios ?? []).map((item) => ({
      id: item.usuario_id,
      nombreCompleto: item.usuario_nombre ?? '',
      tipoRelacion: this.normalizeTipoRelacion(item.tipoRelacion),
    }));

    this.usuariosRelacionadosSeleccionados.set(
      relaciones.filter((item) => !!item.id)
    );

    this.usuariosRelacionadosDisponibles.set([]);
  }

  private toDateInputValue(value: string | null | undefined): string | null {
    if (!value) {
      return null;
    }

    return value.includes('T') ? value.split('T')[0] : value;
  }

  private parseRole(roles: string[] | null | undefined): UserRole {
    const normalized = new Set((roles ?? []).map((role) => role?.trim()));

    return normalized.has('ROLE_ADMIN_ENTIDAD')
      ? 'ROLE_ADMIN_ENTIDAD'
      : 'ROLE_USER';
  }

  private normalizeTipoRelacion(
    value: string | null | undefined
  ): TipoRelacion | null {
    if (!value) {
      return null;
    }

    const normalized = value.trim().toLowerCase() as TipoRelacion;

    const allowed: TipoRelacion[] = [
      'conyuge',
      'padre',
      'madre',
      'pareja',
      'hijo',
      'hija',
      'sobrino',
      'sobrina',
      'abuelo',
      'abuela',
    ];

    return allowed.includes(normalized) ? normalized : null;
  }

  private refreshRelacionTiposFromCatalog(): void {
    const allowed = new Set(this.tiposRelacion().map((item) => item.value));

    this.usuariosRelacionadosSeleccionados.set(
      this.usuariosRelacionadosSeleccionados().map((item) => ({
        ...item,
        tipoRelacion:
          item.tipoRelacion && allowed.has(item.tipoRelacion)
            ? item.tipoRelacion
            : this.normalizeTipoRelacion(item.tipoRelacion),
      }))
    );
  }

  private initCargoTypeWatcher(): void {
    this.form.controls.fechaNacimiento.valueChanges
      .pipe(
        startWith(this.form.controls.fechaNacimiento.value),
        distinctUntilChanged(),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe(() => {
        this.loadCargos();
      });
  }

  private applyFieldPermissions(): void {
    const fechaNacimientoControl = this.form.controls.fechaNacimiento;

    if (this.isEditMode()) {
      this.form.controls.email.enable({ emitEvent: false });
      fechaNacimientoControl.clearValidators();
      fechaNacimientoControl.updateValueAndValidity({ emitEvent: false });
      return;
    }

    this.form.controls.email.enable({ emitEvent: false });
    fechaNacimientoControl.setValidators([Validators.required]);
    fechaNacimientoControl.updateValueAndValidity({ emitEvent: false });
  }

  private loadCargos(): void {
    const tipoPersona = this.resolveTipoPersonaCargo();

    this.tipoPersonaCargo.set(tipoPersona);

    this.adminApi
      .getCargos(tipoPersona)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => {
          this.cargos.set(items);

          const validIds = new Set(items.map((item) => this.getCargoSelectionKey(item)));
          this.cargosSeleccionados.set(
            this.cargosSeleccionados().filter((cargoId) => validIds.has(cargoId))
          );
        },
        error: () => {
          this.errorMessage.set('No se pudo cargar el listado de cargos.');
        },
      });
  }

  private resolveTipoPersonaCargo(): CargoTipoPersona {
    const fechaNacimiento = this.form.controls.fechaNacimiento.value;

    if (!fechaNacimiento) {
      const usuarioTipo = this.normalizeTipoPersonaCargo(
        this.usuario()?.tipoPersona ?? null
      );

      return usuarioTipo ?? 'adulto';
    }

    const fecha = new Date(`${fechaNacimiento}T00:00:00`);
    if (Number.isNaN(fecha.getTime())) {
      return 'adulto';
    }

    const hoy = new Date();
    let edad = hoy.getFullYear() - fecha.getFullYear();
    const mes = hoy.getMonth() - fecha.getMonth();

    if (mes < 0 || (mes === 0 && hoy.getDate() < fecha.getDate())) {
      edad -= 1;
    }

    return edad < 14 ? 'infantil' : 'adulto';
  }

  private normalizeTipoPersonaCargo(value: string | null | undefined): CargoTipoPersona | null {
    if (!value) {
      return null;
    }

    const normalized = value.trim().toLowerCase();

    if (normalized === 'infantil') {
      return 'infantil';
    }

    return 'adulto';
  }

  private buildCargoIris(cargoIds: string[]): string[] {
    return [...new Set(cargoIds)]
      .map((cargoId) => this.cargos().find((cargo) => this.getCargoSelectionKey(cargo) === cargoId))
      .filter((cargo): cargo is Cargo => !!cargo)
      .map((cargo) => cargo.iri ?? `/api/cargos/${cargo.id}`);
  }

  private getCargoSelectionKey(cargo: Cargo): string {
    if (cargo.id?.trim()) {
      return cargo.id.trim();
    }

    if (cargo.registroId?.trim()) {
      return cargo.registroId.trim();
    }

    if (cargo.iri) {
      const parts = cargo.iri.split('/').filter(Boolean);
      return parts.at(-1) ?? '';
    }


    return '';
  }

  private loadTiposRelacion(): void {
    this.adminApi
      .getEnumOptions<TipoRelacion>('tipo-relacion')
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => {
          this.tiposRelacion.set(items);
          this.refreshRelacionTiposFromCatalog();
        },
        error: () => {
          this.errorMessage.set(
            'No se pudo cargar el listado de tipos de relación.'
          );
        },
      });
  }

  private initRelacionUsuariosSearch(): void {
    this.relacionUsuarioSearch$
      .pipe(
        debounceTime(250),
        map((value) => value.trim()),
        distinctUntilChanged(),
        switchMap((value) => {
          if (value.length < 2) {
            return of([] as Usuario[]);
          }

          return this.adminApi.buscarUsuariosRelacionados(value);
        }),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (items) => {
          const currentUserId = this.userId();

          this.usuariosRelacionadosDisponibles.set(
            items.filter((item) => {
              if (!item.id) {
                return false;
              }

              if (currentUserId && item.id === currentUserId) {
                return false;
              }

              return !this.usuariosRelacionadosSeleccionados().some(
                (selected) => selected.id === item.id
              );
            })
          );
        },
        error: () => {
          this.usuariosRelacionadosDisponibles.set([]);
        },
      });
  }
}
