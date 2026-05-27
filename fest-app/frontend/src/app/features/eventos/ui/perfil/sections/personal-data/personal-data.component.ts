import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { finalize, Observable } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';
import {CtaButton} from '../../../../../shared/components/cta-button/cta-button';
import {AuthService} from '../../../../../../core/auth/auth';
import {AuthUser} from '../../../../../../core/models/auth.models';
import {EventosApi} from '../../../../data/eventos.api';
import {MetodoPago, METODOS_PAGO_OPTIONS} from '../../../../domain/eventos.models';


interface Feedback {
  text: string;
  type: 'success' | 'error';
}

/**
 * Personal Data Section Component
 *
 * Handles:
 * - User profile form (name, email, phone, birthday, address, payment method)
 * - Editing child/dependent data
 * - Profile saving
 */
@Component({
  selector: 'app-personal-data',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, CtaButton, TranslatePipe],
  templateUrl: './personal-data.component.html',
  styleUrl: './personal-data.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonalDataComponent {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly userSignal = this.authService.userSignal;

  // ── UI State ────────────────────────────────────────────────────────
  protected readonly saving = signal(false);
  protected readonly feedbackMessage = signal<Feedback | null>(null);
  protected readonly metodosPago = METODOS_PAGO_OPTIONS;

  // ── Edit Mode ───────────────────────────────────────────────────────
  protected readonly editingChildId = signal<string | null>(null);

  // ── Form ────────────────────────────────────────────────────────────
  protected readonly form = this.fb.nonNullable.group({
    nombre: [''],
    apellidos: [''],
    direccion: [''],
    telefono: [''],
    fechaNacimiento: [''],
    formaPagoPreferida: ['' as '' | MetodoPago],
  });

  constructor() {
    this.loadProfileData();
  }

  /**
   * Load current user data into form
   */
  private loadProfileData(): void {
    const user = this.userSignal();
    if (!user) return;

    this.patchForm(user);
  }

  /**
   * Fill form with user data
   */
  private patchForm(user: any): void {
    this.form.patchValue({
      nombre: user.nombre ?? '',
      apellidos: user.apellidos ?? '',
      direccion: user.direccion ?? '',
      telefono: user.telefono ?? '',
      fechaNacimiento: user.fechaNacimiento ?? '',
      formaPagoPreferida: user.formaPagoPreferida ?? '',
    });
    this.form.markAsPristine();
  }

  /**
   * Can save: form is dirty and not currently saving
   */
  protected canSave(): boolean {
    return this.form.dirty && !this.saving();
  }

  /**
   * Save profile changes
   */
  protected saveProfile(): void {
    if (!this.canSave()) return;

    this.feedbackMessage.set(null);
    this.saving.set(true);

    const { nombre, apellidos, direccion, telefono, fechaNacimiento, formaPagoPreferida } =
      this.form.getRawValue();

    const payload = {
      nombre: nombre.trim() || undefined,
      apellidos: apellidos.trim() || undefined,
      direccion: direccion.trim() || undefined,
      telefono: telefono.trim() || undefined,
      fechaNacimiento: fechaNacimiento || undefined,
      formaPagoPreferida: formaPagoPreferida || undefined,
    };

    const childId = this.editingChildId();
    const saveRequest$: Observable<AuthUser> = childId
      ? (this.eventosApi.updateUsuario(childId, payload) as Observable<AuthUser>)
      : this.authService.updateMe(payload);

    saveRequest$
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.form.markAsPristine();

          if (childId) {
            this.editingChildId.set(null);
            this.feedbackMessage.set({
              text: '✅ Datos del hijo/a actualizados correctamente.',
              type: 'success',
            });

            // Refresh user data
            this.authService.getMe()
              .pipe(takeUntilDestroyed(this.destroyRef))
              .subscribe({
                next: (user) => this.patchForm(user),
                error: () => {
                  const fallback = this.authService.getUser();
                  this.patchForm(fallback);
                },
              });
          } else {
            this.feedbackMessage.set({
              text: '✅ Perfil actualizado correctamente.',
              type: 'success',
            });
          }
        },
        error: (error: any) => {
          this.feedbackMessage.set({
            text: `⚠️ ${this.resolveError(error)}`,
            type: 'error',
          });
        },
      });
  }

  /**
   * Enter edit mode for child
   */
  protected editChild(childId: string, childData: any): void {
    this.editingChildId.set(childId);
    this.patchForm(childData);
    this.feedbackMessage.set(null);
  }

  /**
   * Exit edit mode
   */
  protected cancelEditChild(): void {
    this.editingChildId.set(null);
    const user = this.userSignal();
    if (user) {
      this.patchForm(user);
    }
  }

  /**
   * Resolve API error to user-friendly message
   */
  private resolveError(error: any): string {
    if (!error) return 'Error desconocido';
    if (typeof error === 'string') return error;
    if (error.error?.message) return error.error.message;
    if (error.message) return error.message;
    return 'No se pudo guardar los datos.';
  }
}

