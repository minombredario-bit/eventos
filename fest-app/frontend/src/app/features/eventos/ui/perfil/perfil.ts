import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { CtaButton } from '../../../shared/components/cta-button/cta-button';
import { AuthService } from '../../../../core/auth/auth';
import { EventosStore } from '../../store/eventos.store';
import { EventosApi } from '../../data/eventos.api';
import { METODOS_PAGO_OPTIONS, MetodoPago } from '../../domain/eventos.models';

interface Feedback {
  text: string;
  type: 'success' | 'error';
}

@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [ReactiveFormsModule, MobileHeader, CtaButton],
  templateUrl: './perfil.html',
  styleUrl: './perfil.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Perfil {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  protected readonly eventosStore = inject(EventosStore);
  private readonly eventosApi = inject(EventosApi);
  protected readonly userSignal = this.authService.userSignal;

  protected userFullName(): string {
    const u = this.userSignal();
    if (!u) return '';
    const anyU = u as any;
    const nombreCompleto = typeof anyU['nombreCompleto'] === 'string' && anyU['nombreCompleto'].trim() ? anyU['nombreCompleto'].trim() : null;
    if (nombreCompleto) return nombreCompleto;
    const nombre = typeof anyU.nombre === 'string' ? anyU.nombre.trim() : '';
    const apellidos = typeof anyU.apellidos === 'string' ? anyU.apellidos.trim() : '';
    const combined = [nombre, apellidos].filter(Boolean).join(' ');
    if (combined) return combined;
    return String(u.email ?? '');
  }

  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly loading = signal(true);
  protected readonly savingProfile = signal(false);
  protected readonly savingPassword = signal(false);
  protected readonly profileMessage = signal<Feedback | null>(null);
  protected readonly passwordMessage = signal<Feedback | null>(null);
  protected readonly showPasswordCard = signal(false);

  protected readonly metodosPago = METODOS_PAGO_OPTIONS;

  protected readonly profileForm = this.fb.nonNullable.group({
    telefono: [''],
    fechaNacimiento: [''],
    formaPagoPreferida: ['' as '' | MetodoPago],
  });

  protected readonly passwordForm = this.fb.nonNullable.group({
    currentPassword: ['', [Validators.required]],
    newPassword: ['', [Validators.required, Validators.minLength(8)]],
    confirmPassword: ['', [Validators.required]],
  });

  protected readonly showUnsubscribeForm = signal(false);
  protected readonly unsubscribeSelected = signal<Set<string>>(new Set());
  protected readonly unsubscribeReason = signal('');
  protected readonly unsubmitting = signal(false);
  protected readonly unsubscribeMessage = signal<Feedback | null>(null);

  protected canSaveProfile(): boolean {
    return !this.savingProfile();
  }

  protected canSavePassword(): boolean {
    return this.passwordForm.valid && !this.savingPassword();
  }

  constructor() {
    this.loadProfile();
    void this.eventosStore.loadPersonasMias().subscribe();
  }

  protected goBack(): void {
    void this.router.navigateByUrl('/eventos/inicio');
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected saveProfile(): void {
    if (!this.canSaveProfile()) return;

    this.profileMessage.set(null);
    this.savingProfile.set(true);

    const { telefono, fechaNacimiento, formaPagoPreferida } = this.profileForm.getRawValue();

    this.authService
      .updateMe({
        telefono: telefono.trim() || null,
        fechaNacimiento: fechaNacimiento || null,
        formaPagoPreferida: formaPagoPreferida || null,
      })
      .pipe(
        finalize(() => this.savingProfile.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.profileForm.markAsPristine();
          this.profileMessage.set({ text: 'Perfil actualizado correctamente.', type: 'success' });
        },
        error: (error) => {
          this.profileMessage.set({
            text: this.resolveApiError(error) ?? 'No se pudo actualizar el perfil.',
            type: 'error',
          });
        },
      });
  }

  protected savePassword(): void {
    if (!this.passwordForm.valid) {
      this.passwordForm.markAllAsTouched();
      return;
    }

    const { currentPassword, newPassword, confirmPassword } = this.passwordForm.getRawValue();
    if (newPassword !== confirmPassword) {
      this.passwordMessage.set({ text: 'La confirmación de contraseña no coincide.', type: 'error' });
      return;
    }

    this.passwordMessage.set(null);
    this.savingPassword.set(true);

    this.authService
      .changePassword(currentPassword, newPassword)
      .pipe(
        finalize(() => this.savingPassword.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.passwordForm.reset();
          this.passwordMessage.set({ text: 'Contraseña actualizada correctamente.', type: 'success' });
          this.showPasswordCard.set(false);
        },
        error: (error) => {
          this.passwordMessage.set({
            text: this.resolveApiError(error) ?? 'No se pudo cambiar la contraseña.',
            type: 'error',
          });
        },
      });
  }

  protected toggleUnsubscribeMember(memberId: string): void {
    const set = new Set(this.unsubscribeSelected());
    if (set.has(memberId)) set.delete(memberId); else set.add(memberId);
    this.unsubscribeSelected.set(set);
  }

  protected submitUnsubscribe(): void {
    if (this.unsubmitting()) return;
    const memberIds = Array.from(this.unsubscribeSelected());
    if (memberIds.length === 0) {
      this.unsubscribeMessage.set({ text: 'Seleccioná al menos un miembro para continuar.', type: 'error' });
      return;
    }

    this.unsubscribeMessage.set(null);
    this.unsubmitting.set(true);

    this.eventosApi.requestUserUnsubscribe({ memberIds, reason: this.unsubscribeReason() }).pipe(
      finalize(() => this.unsubmitting.set(false)),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe({
      next: () => {
        this.unsubscribeMessage.set({ text: 'Solicitud enviada correctamente. Recibirás noticias por correo.', type: 'success' });
        this.showUnsubscribeForm.set(false);
        this.unsubscribeSelected.set(new Set());
        this.unsubscribeReason.set('');
      },
      error: (err) => {
        this.unsubscribeMessage.set({
          text: this.resolveApiError(err) ?? 'No se pudo enviar la solicitud.',
          type: 'error',
        });
      },
    });
  }

  private loadProfile(): void {
    this.loading.set(true);

    this.authService
      .getMe()
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (user) => {
          this.profileForm.setValue({
            telefono: String(user.telefono ?? ''),
            fechaNacimiento: this.normalizeDateForInput(user.fechaNacimiento),
            formaPagoPreferida: this.normalizeMetodoPago(user.formaPagoPreferida),
          });
          this.profileForm.markAsPristine();
        },
        error: () => {
          const fallback = this.authService.getUser();
          this.profileForm.setValue({
            telefono: String(fallback?.telefono ?? ''),
            fechaNacimiento: this.normalizeDateForInput(fallback?.fechaNacimiento),
            formaPagoPreferida: this.normalizeMetodoPago(fallback?.formaPagoPreferida),
          });
          this.profileForm.markAsPristine();
        },
      });
  }

  private normalizeDateForInput(value: unknown): string {
    if (typeof value !== 'string' || value.trim().length === 0) return '';
    const raw = value.trim();
    return raw.includes('T') ? raw.split('T')[0] : raw;
  }

  private normalizeMetodoPago(value: unknown): '' | MetodoPago {
    if (typeof value !== 'string') return '';
    const found = this.metodosPago.find((item) => item.value === value);
    return found ? found.value : '';
  }

  private resolveApiError(error: unknown): string | null {
    if (!error || typeof error !== 'object') return null;
    const source = error as {
      message?: unknown;
      error?: {
        detail?: unknown;
        description?: unknown;
        'hydra:description'?: unknown;
        title?: unknown;
        message?: unknown;
      };
    };

    const candidates = [
      source.error?.detail,
      source.error?.description,
      source.error?.['hydra:description'],
      source.error?.title,
      source.error?.message,
      source.message,
    ];

    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim().length > 0) {
        return candidate.trim();
      }
    }

    return null;
  }
}
