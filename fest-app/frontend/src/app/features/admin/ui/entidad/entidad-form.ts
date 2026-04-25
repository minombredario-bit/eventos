import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { QuillModule } from 'ngx-quill';
import { AdminApi } from '../../data/admin.api';
import { Entidad } from '../../domain/admin.models';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import {ToastService} from '../../../shared/components/toast/toast.service';

@Component({
  selector: 'app-admin-entidad-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, QuillModule, TranslateModule],
  templateUrl: './entidad-form.html',
  styleUrls: ['./entidad-form.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminEntidadForm {
  private readonly adminApi = inject(AdminApi);
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);

  protected entidades: Entidad[] = [];
  protected selectedEntidadId: string | null = null;

  protected loading = false;
  protected saving = false;

  protected readonly form = this.fb.group({
    nombre: this.fb.nonNullable.control('', [Validators.required, Validators.minLength(2)]),
    emailContacto: this.fb.nonNullable.control('', [Validators.required, Validators.email]),
    telefono: this.fb.nonNullable.control(''),
    direccion: this.fb.nonNullable.control(''),
    textoLopd: this.fb.control<string | null>(null),
  });

  private readonly toast = inject(ToastService);
  private readonly translate = inject(TranslateService);
  protected readonly pageTitle = computed(() => 'admin.entidad.title');

  constructor() {
    this.loadEntidades();
  }

  protected loadEntidades(): void {
    this.loading = true;
    this.adminApi
      .getEntidades()
      .pipe(finalize(() => (this.loading = false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (items) => {
          this.entidades = items;
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
    this.selectedEntidadId = id;

    const entidad = this.entidades.find((e) => e.id === id) ?? null;
    if (!entidad) {
      this.form.reset();
      return;
    }

    this.form.reset({
      nombre: entidad.nombre ?? '',
      emailContacto: entidad.emailContacto ?? '',
      telefono: entidad.telefono ?? '',
      direccion: entidad.direccion ?? '',
      textoLopd: entidad.textoLopd ?? null,
    });
  }

  protected save(): void {
    if (!this.selectedEntidadId) {
      this.toast.showError(this.translate.instant('admin.entidad.selectRequired'));
      return;
    }

    if (this.form.invalid) {
      this.toast.showError(this.translate.instant('admin.entidad.invalidForm'));
      this.form.markAllAsTouched();
      return;
    }

    this.saving = true;

    const payload: Partial<Entidad> = {
      nombre: this.form.controls.nombre.value?.trim() ?? '',
      emailContacto: this.form.controls.emailContacto.value?.trim() ?? '',
      telefono: this.form.controls.telefono.value?.trim() || null,
      direccion: this.form.controls.direccion.value?.trim() || null,
      textoLopd: this.form.controls.textoLopd.value ?? null,
    };

    this.adminApi
      .updateEntidad(this.selectedEntidadId, payload)
      .pipe(finalize(() => (this.saving = false)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (updated) => {
          this.toast.showSuccess(this.translate.instant('admin.entidad.successSaved'));
          // Update local cache
          const idx = this.entidades.findIndex((e) => e.id === updated.id);
          if (idx >= 0) this.entidades[idx] = updated;
        },
        error: () => {
          this.toast.showError(this.translate.instant('admin.entidad.errorSave'));
        },
      });
  }
}

