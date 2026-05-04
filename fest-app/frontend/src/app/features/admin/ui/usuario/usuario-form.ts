import {
  AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  ElementRef,
  OnDestroy,
  ViewChild,
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
  UserRole, isTipoRelacion,
} from '../../domain/admin.models';
import {TranslateModule, TranslateService} from '@ngx-translate/core';
import {ToastService} from '../../../shared/components/toast/toast.service';

type SubmitMode = 'floating' | 'bottom' | 'inline';

@Component({
  selector: 'app-admin-usuario-form',
  standalone: true,
  imports: [CommonModule, MobileHeader, ReactiveFormsModule, TranslateModule],
  templateUrl: './usuario-form.html',
  styleUrl: './usuario-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminUsuarioForm implements AfterViewInit, OnDestroy {
  @ViewChild('submitAnchor') private submitAnchorRef?: ElementRef<HTMLElement>;
  @ViewChild('submitBar')    private submitBarRef?:    ElementRef<HTMLElement>;
  @ViewChild('formEnd')      private formEndRef?:      ElementRef<HTMLElement>;

  private anchorObserver?: IntersectionObserver;
  private endObserver?:    IntersectionObserver;

  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi = inject(AdminApi);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
  private readonly toast = inject(ToastService);
  private readonly translate = inject(TranslateService);

  private readonly relacionUsuarioSearch$ = new Subject<string>();

  protected readonly loading = signal(false);
  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);

  protected readonly usuario = signal<Usuario | null>(null);
  protected readonly passwordGenerada = signal<string | null>(null);
  protected readonly userId = signal<string | null>(null);

  protected readonly cargos = signal<Cargo[]>([]);
  protected readonly cargosSeleccionados = signal<string[]>([]);

  protected readonly submitMode = signal<SubmitMode>('floating');

  readonly tipoPersonaCargo = computed<CargoTipoPersona>(() => {
    const usuario = this.usuario();

    if (usuario?.tipoPersona === 'infantil') return 'infantil';
    if (usuario?.tipoPersona === 'adulto')   return 'adulto';

    const fecha = this.form.controls.fechaNacimiento.value;
    if (!fecha) return 'adulto';

    const [year, month, day] = fecha.split('-').map(Number);
    if (!year || !month || !day) return 'adulto';

    const hoy = new Date();
    let edad = hoy.getFullYear() - year;
    const aunNoCumplio =
      hoy.getMonth() + 1 < month ||
      (hoy.getMonth() + 1 === month && hoy.getDate() < day);
    if (aunNoCumplio) edad--;

    return edad < 18 ? 'infantil' : 'adulto';
  });

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
    documentoIdentidad: this.fb.control<string | null>(null),
    activo: this.fb.nonNullable.control(true),
    motivoBajaCenso: this.fb.control<string | null>(null),
    fechaNacimiento: this.fb.control<string | null>(null, [Validators.required]),
    antiguedad: this.fb.control<number | null>(null),
    antiguedadReal: this.fb.control<number | null>(null),
    formaPagoPreferida: this.fb.nonNullable.control<MetodoPagoPreferida>('efectivo'),
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
          this.passwordGenerada.set(null);
          this.cargosSeleccionados.set([]);
          this.usuariosRelacionadosSeleccionados.set([]);
          this.resetForCreate();
          this.applyFieldPermissions();
          return;
        }

        this.loading.set(true);
        try { this.form.disable({ emitEvent: false }); } catch {}

        this.adminApi
          .getUsuarioAdmin(id.trim())
          .pipe(
            finalize(() => {
              this.loading.set(false);
              try { this.form.enable({ emitEvent: false }); } catch {}
              this.applyFieldPermissions();
            }),
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
              this.loadCargos();
            },
            error: (error: { error?: { error?: string } }) => {
              this.errorMessage.set(
                error?.error?.error ?? 'No se pudo cargar el usuario.'
              );
            },
          });
      });
  }

  ngAfterViewInit(): void {
    this.initSubmitBarObserver();
  }

  ngOnDestroy(): void {
    this.anchorObserver?.disconnect();
    this.endObserver?.disconnect();
  }

  // ── Submit bar: flotante → fijo al llegar al final ────────────────────────

  private initSubmitBarObserver(): void {
    if (!this.submitAnchorRef || !this.formEndRef) return;

    // Cuando el anchor sale de la vista → modo flotante
    this.anchorObserver = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) {
          this.submitMode.set('floating');
        }
      },
      { threshold: 0 }
    );
    this.anchorObserver.observe(this.submitAnchorRef.nativeElement);

    // Cuando el final del form entra en la vista → modo bottom (sticky)
    this.endObserver = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          this.submitMode.set('bottom');
        } else if (this.submitMode() === 'bottom') {
          this.submitMode.set('floating');
        }
      },
      { threshold: 0 }
    );
    this.endObserver.observe(this.formEndRef.nativeElement);
  }

  // ── Form actions ──────────────────────────────────────────────────────────

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.errorMessage.set('Revisa los campos obligatorios.');
      return;
    }

    this.errorMessage.set(null);
    this.successMessage.set(null);

    if (this.isEditMode()) {
      this.updateUsuario();
      return;
    }

    this.createUsuario();
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
    if (!id) return false;
    return this.usuariosRelacionadosSeleccionados().some((item) => item.id === id);
  }

  protected addUsuarioRelacionado(usuario: Usuario): void {
    if (!usuario.id || this.isUsuarioRelacionadoSelected(usuario.id)) return;
    if (usuario.id === this.userId()) return;

    this.usuariosRelacionadosSeleccionados.set([
      ...this.usuariosRelacionadosSeleccionados(),
      { id: usuario.id, nombreCompleto: usuario.nombreCompleto ?? '', tipoRelacion: null },
    ]);

    try {
      this.form.controls.relacionUsuarioSearch.setValue('', { emitEvent: false });
    } catch {}

    this.usuariosRelacionadosDisponibles.set([]);
  }

  protected removeUsuarioRelacionado(id: string): void {
    const eliminados = this.usuariosRelacionadosSeleccionados().filter((item) => item.id === id);

    this.usuariosRelacionadosSeleccionados.set(
      this.usuariosRelacionadosSeleccionados().filter((item) => item.id !== id)
    );

    if (eliminados.length > 0) {
      this.usuariosRelacionadosDisponibles.set([
        ...this.usuariosRelacionadosDisponibles(),
        ...eliminados.map((item) => ({ id: item.id, nombreCompleto: item.nombreCompleto }) as Usuario),
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

  // ── Private helpers ───────────────────────────────────────────────────────

  private createUsuario(): void {
    this.saving.set(true);
    this.errorMessage.set(null);
    this.passwordGenerada.set(null);

    const value = this.form.getRawValue();

    const relacionesInvalidas = this.usuariosRelacionadosSeleccionados().some(
      (item) => !item.id || !item.tipoRelacion
    );

    if (relacionesInvalidas) {
      this.errorMessage.set('Debes indicar el tipo de relación de cada usuario relacionado.');
      this.saving.set(false);
      return;
    }

    const roles: UserRole[] = [value.role];

    const payload: UsuarioCreatePayload = {
      nombre: value.nombre.trim(),
      apellidos: value.apellidos.trim(),
      email: (() => { const e = value.email.trim(); return e ? e.toLowerCase() : null; })(),
      telefono: value.telefono.trim() || null,
      documentoIdentidad: value.documentoIdentidad ? null : (value.documentoIdentidad?.trim() || null),
      activo: value.activo,
      motivoBajaCenso: value.activo ? null : (value.motivoBajaCenso?.trim() || null),
      fechaNacimiento: value.fechaNacimiento,
      antiguedad: value.antiguedad,
      antiguedadReal: value.antiguedadReal,
      formaPagoPreferida: value.formaPagoPreferida,
      debeCambiarPassword: value.debeCambiarPassword,
      roles,
      cargos: this.cargosSeleccionados().map((id) => `/api/entidad_cargos/${id}`),
      relacionUsuarios: this.usuariosRelacionadosSeleccionados().map((item) => ({
        usuario: `/api/usuarios/${item.id}`,
        tipoRelacion: item.tipoRelacion as TipoRelacion,
      })),
    };

    this.adminApi
      .crearUsuario(payload)
      .pipe(finalize(() => this.saving.set(false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response: any) => {
          const created: Usuario = response.usuario;
          this.successMessage.set('Usuario creado correctamente.');
          this.passwordGenerada.set(response.passwordPlano ?? null);
          this.userId.set(created.id ?? null);
          this.usuario.set(created);
          this.patchForm(created);
          this.patchCargos(created);
          this.patchRelacionUsuarios(created);
          this.applyFieldPermissions();

          if (created.id) {
            void this.router.navigate(['/admin/usuarios', created.id], { replaceUrl: true });
          }
        },
        error: (error: { error?: { detail?: string; error?: string } }) => {
          this.errorMessage.set(
            error?.error?.detail ?? error?.error?.error ?? 'No se pudo crear el usuario.'
          );
        },
      });
  }

  private updateUsuario(): void {
    const id = this.userId();
    this.passwordGenerada.set(null);
    if (!id) return;

    this.saving.set(true);
    this.errorMessage.set(null);

    const value = this.form.getRawValue();

    const relacionesInvalidas = this.usuariosRelacionadosSeleccionados().some(
      (item) => !item.id || !item.tipoRelacion
    );

    if (relacionesInvalidas) {
      this.errorMessage.set('Debes indicar el tipo de relación de cada usuario relacionado.');
      this.saving.set(false);
      return;
    }

    const roles: UserRole[] = [value.role];

    const payload: UsuarioPatch = {
      nombre: value.nombre.trim(),
      apellidos: value.apellidos.trim(),
      email: (() => { const e = value.email.trim(); return e ? e.toLowerCase() : null; })(),
      telefono: value.telefono.trim() || null,
      documentoIdentidad: value.documentoIdentidad ? null : (value.documentoIdentidad?.trim() || null),
      activo: value.activo,
      motivoBajaCenso: value.activo ? null : (value.motivoBajaCenso?.trim() || null),
      antiguedad: value.antiguedad,
      antiguedadReal: value.antiguedadReal,
      fechaNacimiento: value.fechaNacimiento,
      formaPagoPreferida: value.formaPagoPreferida,
      debeCambiarPassword: value.debeCambiarPassword,
      roles,
      cargos: this.buildCargoIris(this.cargosSeleccionados()),
      relacionUsuarios: this.usuariosRelacionadosSeleccionados().map((item) => ({
        usuario: `/api/usuarios/${item.id}`,
        tipoRelacion: item.tipoRelacion as TipoRelacion,
      })),
    };

    this.adminApi
      .updateUsuario(id, payload)
      .pipe(finalize(() => this.saving.set(false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response: any) => {
          const usuarioActualizado: Usuario = response.usuario ?? response;
          this.usuario.set(usuarioActualizado);
          this.patchForm(usuarioActualizado);
          this.patchCargos(usuarioActualizado);
          this.patchRelacionUsuarios(usuarioActualizado);
          this.applyFieldPermissions();
          this.successMessage.set('Usuario actualizado correctamente.');
          this.passwordGenerada.set(response.passwordPlano ?? null);
        },
        error: (error: { error?: { detail?: string; error?: string } }) => {
          this.errorMessage.set(
            error?.error?.detail ?? error?.error?.error ?? 'No se pudo actualizar el usuario.'
          );
        },
      });
  }

  private resetForCreate(): void {
    this.form.reset({
      nombre: '', apellidos: '', email: '', telefono: '',
      documentoIdentidad: null, activo: true, motivoBajaCenso: null,
      fechaNacimiento: null, antiguedad: null, antiguedadReal: null,
      formaPagoPreferida: 'efectivo', debeCambiarPassword: true,
      role: 'ROLE_USER', relacionUsuarioSearch: '',
    });
    this.usuariosRelacionadosSeleccionados.set([]);
    this.usuariosRelacionadosDisponibles.set([]);
    try { this.form.enable({ emitEvent: false }); } catch {}
  }

  private patchForm(usuario: Usuario): void {
    this.form.reset({
      nombre: usuario.nombre ?? '',
      apellidos: usuario.apellidos ?? '',
      email: usuario.email ?? '',
      telefono: usuario.telefono ?? '',
      documentoIdentidad: usuario.documentoIdentidad ?? null,
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
        .filter((id): id is string => id.length > 0)
    );
    this.cargosSeleccionados.set([...uniqueCargoIds]);
  }

  private patchRelacionUsuarios(usuario: Usuario): void {
    const relaciones = (usuario.relacionUsuarios ?? []).map((item) => ({
      id: item.usuario_id,
      nombreCompleto: item.usuario_nombre ?? '',
      tipoRelacion: this.normalizeTipoRelacion(item.tipoRelacion),
    }));
    this.usuariosRelacionadosSeleccionados.set(relaciones.filter((item) => !!item.id));
    this.usuariosRelacionadosDisponibles.set([]);
  }

  private toDateInputValue(value: string | null | undefined): string | null {
    if (!value) return null;
    return value.includes('T') ? value.split('T')[0] : value;
  }

  private parseRole(roles: string[] | null | undefined): UserRole {
    const normalized = new Set((roles ?? []).map((r) => r?.trim()));
    return normalized.has('ROLE_ADMIN_ENTIDAD') ? 'ROLE_ADMIN_ENTIDAD' : 'ROLE_USER';
  }

  private normalizeTipoRelacion(value: string | null | undefined): TipoRelacion | null {
    if (!value) return null;
    const normalized = value.trim().toLowerCase();
    return isTipoRelacion(normalized) ? normalized : null;
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
      .pipe(distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => { if (!this.loading()) this.loadCargos(); });
  }

  private applyFieldPermissions(): void {
    const fechaNacimientoControl = this.form.controls.fechaNacimiento;
    this.form.controls.email.enable({ emitEvent: false });

    if (this.isEditMode()) {
      fechaNacimientoControl.clearValidators();
    } else {
      fechaNacimientoControl.setValidators([Validators.required]);
    }

    fechaNacimientoControl.updateValueAndValidity({ emitEvent: false });
  }

  private loadCargos(): void {
    const tipo = this.tipoPersonaCargo();
    this.adminApi
      .getCargos(tipo)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => this.cargos.set(items),
        error: () => this.toast.showError(this.translate.instant('admin.usuario.error_load_cargos')),
      });
  }

  private normalizeTipoPersonaCargo(value: string | null | undefined): CargoTipoPersona | null {
    if (!value) return null;
    const normalized = value.trim().toLowerCase();
    return normalized === 'infantil' ? 'infantil' : 'adulto';
  }

  private buildCargoIris(cargoIds: string[]): string[] {
    return [...new Set(cargoIds)]
      .map((cargoId) => this.cargos().find((cargo) => this.getCargoSelectionKey(cargo) === cargoId))
      .filter((cargo): cargo is Cargo => !!cargo)
      .map((cargo) => cargo.iri ?? `/api/cargos/${cargo.id}`);
  }

  private getCargoSelectionKey(cargo: Cargo): string {
    if (cargo.id?.trim())        return cargo.id.trim();
    if (cargo.registroId?.trim()) return cargo.registroId.trim();
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
          this.errorMessage.set('No se pudo cargar el listado de tipos de relación.');
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
          if (value.length < 2) return of([] as Usuario[]);
          return this.adminApi.buscarUsuariosRelacionados(value);
        }),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (items) => {
          const currentUserId = this.userId();
          this.usuariosRelacionadosDisponibles.set(
            items.filter((item) => {
              if (!item.id) return false;
              if (currentUserId && item.id === currentUserId) return false;
              return !this.usuariosRelacionadosSeleccionados().some((s) => s.id === item.id);
            })
          );
        },
        error: () => { this.usuariosRelacionadosDisponibles.set([]); },
      });
  }
}
