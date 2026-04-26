import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { QuillModule } from 'ngx-quill';
import { AdminApi } from '../../data/admin.api';
import { Entidad, EntidadCargo, CargoMaster } from '../../domain/admin.models';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize, forkJoin } from 'rxjs';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { ToastService } from '../../../shared/components/toast/toast.service';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { Router } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth';
import {ConfirmModal} from '../../../shared/components/confirm-modal/confirm-modal';

@Component({
  selector: 'app-admin-entidad-form',
  standalone: true,
  imports: [CommonModule, MobileHeader, ReactiveFormsModule, QuillModule, TranslateModule, ConfirmModal],
  templateUrl: './entidad-form.html',
  styleUrls: ['./entidad-form.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminEntidadForm {
  private readonly adminApi    = inject(AdminApi);
  private readonly fb          = inject(FormBuilder);
  private readonly destroyRef  = inject(DestroyRef);
  private readonly toast       = inject(ToastService);
  private readonly translate   = inject(TranslateService);
  private readonly router      = inject(Router);
  private readonly authService = inject(AuthService);

  // ── State ──────────────────────────────────────────────────────────────────

  protected readonly loading      = signal(false);
  protected readonly saving       = signal(false);
  protected readonly loadingCargos = signal(false);

  protected readonly entidades         = signal<Entidad[]>([]);
  protected readonly selectedEntidadId = signal<string | null>(null);

  /** Cargos maestros disponibles según tipoEntidad */
  protected readonly availableCargoMasters = signal<CargoMaster[]>([]);
  protected readonly cargoMastersList = computed(() => this.availableCargoMasters());

  /** EntidadCargos actuales de la entidad seleccionada */
  protected readonly entidadCargos = signal<EntidadCargo[]>([]);

  /** IDs de CargoMaster ya vinculados */
  protected readonly selectedCargoMasterIds = signal<Set<string>>(new Set());

  readonly showDeleteCargoModal = signal(false);
  readonly cargoToDelete = signal<EntidadCargo | null>(null);

  // ── Computed ───────────────────────────────────────────────────────────────

  protected readonly canAddCargo = computed(() => {
    return this.form.controls.newCargoName.value.trim().length > 0;
  });

  protected readonly pageTitle = computed(() =>
    this.translate.instant('admin.entidad.title'),
  );

  protected readonly canSelectEntidad = computed(() =>
    this.authService.hasAnyRole(['ROLE_SUPERADMIN', 'ROLE_ADMIN']),
  );

  protected readonly canEditTipoEntidad = computed(() =>
    this.authService.hasAnyRole(['ROLE_SUPERADMIN', 'ROLE_ADMIN']),
  );

  // ── Form ───────────────────────────────────────────────────────────────────

  protected readonly form = this.fb.group({
    nombre:        this.fb.nonNullable.control('', [Validators.required, Validators.minLength(2)]),
    emailContacto: this.fb.nonNullable.control('', [Validators.required, Validators.email]),
    telefono:      this.fb.nonNullable.control(''),
    direccion:     this.fb.nonNullable.control(''),
    textoLopd:     this.fb.control<string | null>(null),
    newCargoName:  this.fb.nonNullable.control(''),
  });

  // ── Init ───────────────────────────────────────────────────────────────────

  constructor() {
    this.loadEntidades();
  }

  // ── Load ───────────────────────────────────────────────────────────────────

  protected loadEntidades(): void {
    this.loading.set(true);
    this.adminApi
      .getEntidades()
      .pipe(finalize(() => this.loading.set(false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => {
          this.entidades.set(items);
          if (items.length === 1) {
            this.selectEntidad(items[0].id ?? null);
          }
        },
        error: () => {
          this.toast.showError(this.translate.instant('admin.entidad.errorLoad'));
        },
      });
  }

  protected selectEntidad(id: string | null): void {
    this.selectedEntidadId.set(id);

    const entidad = this.entidades().find((e) => e.id === id) ?? null;
    if (!entidad) {
      this.form.reset();
      return;
    }

    // Poblar formulario con los datos de la respuesta
    this.form.reset({
      nombre:        entidad.nombre ?? '',
      emailContacto: entidad.emailContacto ?? '',
      telefono:      entidad.telefono ?? '',
      direccion:     entidad.direccion ?? '',
      textoLopd:     entidad.textoLopd ?? null,
    });

    // Cargar cargos de la entidad y cargos maestros disponibles en paralelo
    this.loadCargosData();
  }

  private loadCargosData(): void {
    this.loadingCargos.set(true);

    forkJoin({
      cargosEntidad: this.adminApi.getEntidadCargos(),
      tipoEntidadCargos: this.adminApi.getTipoEntidadCargos(),
    })
      .pipe(
        finalize(() => this.loadingCargos.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: ({ cargosEntidad, tipoEntidadCargos }) => {
          this.entidadCargos.set(cargosEntidad);

          const masters = tipoEntidadCargos
            .map((tec) => tec.cargoMaster)
            .filter((cm): cm is CargoMaster => !!cm && !!cm.id);

          this.availableCargoMasters.set(masters);

          const linkedIds = new Set<string>(
            cargosEntidad
              .map((ec) => ec.cargoMaster?.id)
              .filter((id): id is string => !!id)
          );

          this.selectedCargoMasterIds.set(linkedIds);
        },
        error: () => {
          this.toast.showError(this.translate.instant('admin.entidad.errorLoad'));
        },
      });
  }

  // ── Cargos ─────────────────────────────────────────────────────────────────

  protected isCargoMasterLinked(cargoMasterId: string): boolean {
    return this.selectedCargoMasterIds().has(cargoMasterId);
  }

  protected async toggleCargoMaster(cm: CargoMaster): Promise<void> {
    if (!cm.id) return;
    const entidadId = this.selectedEntidadId();
    if (!entidadId) return;

    const linked = this.isCargoMasterLinked(cm.id);

    if (linked) {
      // Buscar el EntidadCargo existente y desactivarlo
      const existing = this.entidadCargos().find(
        (ec) => (ec.cargoMaster as any)?.id === cm.id,
      );
      if (existing?.id) {
        this.saving.set(true);
        this.adminApi
          .patchEntidadCargo(existing.id, { activo: false })
          .pipe(finalize(() => this.saving.set(false)), takeUntilDestroyed(this.destroyRef))
          .subscribe({
            next: () => {
              this.selectedCargoMasterIds.update((s) => {
                const next = new Set(s);
                next.delete(cm.id!);
                return next;
              });
            },
            error: () => this.toast.showError(this.translate.instant('admin.entidad.errorSave')),
          });
      }
    } else {
      // Crear EntidadCargo vinculado al CargoMaster
      this.saving.set(true);
      this.adminApi
        .crearEntidadCargo({
          entidad: `/api/entidads/${entidadId}`,
          cargoMaster: `/api/cargo_masters/${cm.id}`,
          activo: true,
        })
        .pipe(finalize(() => this.saving.set(false)), takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: (ec) => {
            this.entidadCargos.update((list) => [...list, ec]);
            this.selectedCargoMasterIds.update((s) => new Set([...s, cm.id!]));
          },
          error: () => this.toast.showError(this.translate.instant('admin.entidad.errorSave')),
        });
    }
  }

  getCargoNombre(ec: EntidadCargo): string {
    if (ec.cargo && typeof ec.cargo === 'object') {
      return ec.cargo.nombre;
    }

    if (ec.cargoMaster && typeof ec.cargoMaster === 'object') {
      return ec.cargoMaster.nombre;
    }

    return ec.nombre || 'Cargo personalizado';
  }

  protected addCustomCargo(): void {
    const nombre = this.form.controls.newCargoName.value?.trim();
    if (!nombre) return;

    const entidadId = this.selectedEntidadId();
    if (!entidadId) return;

    this.saving.set(true);
    this.adminApi.crearCargo({
      nombre,
      entidad: `/api/entidads/${entidadId}`
    }).pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (cargo) => {
          if (!cargo?.id) {
            this.saving.set(false);
            return;
          }

          this.adminApi
            .crearEntidadCargo({
              entidad: `/api/entidades/${entidadId}`,
              cargo: `/api/cargos/${cargo.id}`,
              activo: true,
            })
            .pipe(
              finalize(() => this.saving.set(false)),
              takeUntilDestroyed(this.destroyRef)
            )
            .subscribe({
              next: (ec) => {
                this.entidadCargos.update((list) => [...list, ec]);
                this.form.controls.newCargoName.reset('');
              },
              error: () => {
                this.toast.showError(
                  this.translate.instant('admin.entidad.errorSave')
                );
              },
            });
        },
        error: () => {
          this.saving.set(false);
          this.toast.showError(
            this.translate.instant('admin.entidad.errorSave')
          );
        },
      });
  }

  protected removeEntidadCargo(ec: EntidadCargo): void {
    this.cargoToDelete.set(ec);
    this.showDeleteCargoModal.set(true);
  }

  protected confirmRemoveEntidadCargo(): void {
    const ec = this.cargoToDelete();

    if (!ec) {
      return;
    }

    this.showDeleteCargoModal.set(false);
    this.saving.set(true);

    this.adminApi
      .deleteEntidadCargo(ec.id)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: () => {
          this.entidadCargos.update((items) =>
            items.filter((item) => item.id !== ec.id)
          );

          this.toast.showSuccess(
            this.translate.instant('admin.entidad.cargoDeleted')
          );
        },
        error: (error) => {
          if (error?.status === 409) {
            this.toast.showError(
              error?.error?.detail ||
              'No puedes eliminar este cargo porque está asignado a usuarios en la temporada actual.'
            );
            return;
          }

          this.toast.showError(
            this.translate.instant('admin.entidad.errorSave')
          );
        },
      });

    this.cargoToDelete.set(null);
  }

  protected cancelRemoveEntidadCargo(): void {
    this.showDeleteCargoModal.set(false);
    this.cargoToDelete.set(null);
  }
  // ── Save ───────────────────────────────────────────────────────────────────

  protected save(): void {
    const entidadId = this.selectedEntidadId();
    if (!entidadId) {
      this.toast.showError(this.translate.instant('admin.entidad.selectRequired'));
      return;
    }

    if (this.form.invalid) {
      this.toast.showError(this.translate.instant('admin.entidad.invalidForm'));
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);

    const payload: Partial<Entidad> = {
      nombre:        this.form.controls.nombre.value?.trim() ?? '',
      emailContacto: this.form.controls.emailContacto.value?.trim() ?? '',
      telefono:      this.form.controls.telefono.value?.trim() || null,
      direccion:     this.form.controls.direccion.value?.trim() || null,
      textoLopd:     this.form.controls.textoLopd.value ?? null,
    };

    this.adminApi
      .updateEntidad(entidadId, payload)
      .pipe(finalize(() => this.saving.set(false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (updated) => {
          this.toast.showSuccess(this.translate.instant('admin.entidad.successSaved'));
          this.entidades.update((list) =>
            list.map((e) => (e.id === updated.id ? updated : e)),
          );
        },
        error: () => {
          this.toast.showError(this.translate.instant('admin.entidad.errorSave'));
        },
      });
  }

  // ── Navigation ─────────────────────────────────────────────────────────────

  protected goBack(): void {
    void this.router.navigate(['/admin/dashboard']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }
}
