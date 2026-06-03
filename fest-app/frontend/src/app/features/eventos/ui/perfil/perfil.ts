import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import {finalize, Observable} from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule, DatePipe } from '@angular/common';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { CtaButton } from '../../../shared/components/cta-button/cta-button';
import { ConfirmModal } from '../../../shared/components/confirm-modal/confirm-modal';
import { AuthService } from '../../../../core/auth/auth';
import { EventosStore } from '../../store/eventos.store';
import { EventosApi } from '../../data/eventos.api';
import { METODOS_PAGO_OPTIONS, MetodoPago, RelacionUsuario } from '../../domain/eventos.models';
import { TranslatePipe } from '@ngx-translate/core';
import { BiometricService, PasskeyCredentialInfo } from '../../../../core/services/biometric.service';
import { PushNotificationService } from '../../../../core/services/push-notification.service';

interface Feedback {
  text: string;
  type: 'success' | 'error';
}

@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, MobileHeader, CtaButton, TranslatePipe, ConfirmModal, DatePipe],
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
  protected readonly biometricService = inject(BiometricService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  // ── Tab Navigation ────────────────────────────────────────────────────
  protected readonly activeTab = signal<'identidad' | 'seguridad' | 'familia' | 'pago'>('identidad');

  protected setTab(tab: 'identidad' | 'seguridad' | 'familia' | 'pago'): void {
    this.activeTab.set(tab);
  }

  // ── Biometric state ─────────────��──────────────────────────────────────
  protected readonly biometricCredentials = signal<PasskeyCredentialInfo[]>([]);
  protected readonly loadingBiometric = signal(false);
  protected readonly registeringBiometric = signal(false);
  protected readonly deletingBiometricId = signal<string | null>(null);
  protected readonly biometricMessage = signal<Feedback | null>(null);

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

   protected readonly loading = signal(true);
  protected readonly savingProfile = signal(false);
  protected readonly savingPassword = signal(false);
  protected readonly profileMessage = signal<Feedback | null>(null);
  protected readonly passwordMessage = signal<Feedback | null>(null);
  protected readonly showPasswordCard = signal(false);

  protected readonly metodosPago = METODOS_PAGO_OPTIONS;

  protected readonly profileForm = this.fb.nonNullable.group({
    nombre: [''],
    apellidos: [''],
    direccion: [''],
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

   // ── Relaciones ────────────────────────────────────────────────────────
   protected readonly relaciones = signal<RelacionUsuario[]>([]);
   protected readonly loadingRelaciones = signal(false);
   protected readonly errorRelaciones = signal<string | null>(null);
   protected readonly deletingRelacionId = signal<string | null>(null);
   protected readonly deleteRelacionMessage = signal<Feedback | null>(null);
   protected readonly editingChildId = signal<string | null>(null);
    // Estado para el modal de confirmación al borrar una relación
    protected readonly showDeleteRelacionModal = signal(false);
    protected readonly relacionToDelete = signal<RelacionUsuario | null>(null);

  protected canSaveProfile(): boolean {
    return !this.savingProfile();
  }

  protected canSavePassword(): boolean {
    return this.passwordForm.valid && !this.savingPassword();
  }

   constructor() {
     this.loadProfile();
     this.loadRelaciones();
     this.loadBiometricCredentials();
     this.loadFamilyMembers();
     this.loadPushNotificationStatus();
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

     const {
       nombre,
       apellidos,
       direccion,
       telefono,
       fechaNacimiento,
       formaPagoPreferida,
     } = this.profileForm.getRawValue();

     const payload = {
       nombre: nombre.trim() || undefined,
       apellidos: apellidos.trim() || undefined,
       direccion: direccion.trim() || undefined,
       telefono: telefono.trim() || undefined,
       fechaNacimiento: fechaNacimiento || undefined,
       formaPagoPreferida: formaPagoPreferida || undefined,
     };

     const childId = this.editingChildId();

     const saveRequest$: Observable<any> = childId
       ? this.eventosApi.updateUsuario(childId, payload)
       : this.authService.updateMe(payload);

     saveRequest$
       .pipe(
         finalize(() => this.savingProfile.set(false)),
         takeUntilDestroyed(this.destroyRef),
       )
       .subscribe({
         next: (updatedUser) => {
           this.profileForm.markAsPristine();

           if (childId) {
             this.editingChildId.set(null);

             this.profileMessage.set({
               text: 'Datos del hijo/a actualizados correctamente.',
               type: 'success',
             });

             this.loadRelaciones();

             this.authService.getMe()
               .pipe(takeUntilDestroyed(this.destroyRef))
               .subscribe({
                 next: (user) => this.patchProfileForm(user),
                 error: () => {
                   const fallback = this.authService.getUser();
                   this.patchProfileForm(fallback);
                 },
               });

             return;
           }

           // Actualizar el formulario con los datos del servidor
           if (updatedUser) {
             this.patchProfileForm(updatedUser);
           }

           this.profileMessage.set({
             text: 'Perfil actualizado correctamente.',
             type: 'success',
           });
         },
         error: (error: any) => {
           this.profileMessage.set({
             text: this.resolveApiError(error) ?? 'No se pudo actualizar el perfil.',
             type: 'error',
           });
         },
       });
   }

   protected savePaymentMethod(): void {
     if (!this.canSaveProfile()) return;

     this.profileMessage.set(null);
     this.savingProfile.set(true);

     const { formaPagoPreferida } = this.profileForm.getRawValue();

     const payload = {
       formaPagoPreferida: formaPagoPreferida || undefined,
     };

     this.authService.updateMe(payload)
       .pipe(
         finalize(() => this.savingProfile.set(false)),
         takeUntilDestroyed(this.destroyRef),
       )
        .subscribe({
          next: (updatedUser) => {
            this.profileForm.get('formaPagoPreferida')?.markAsPristine();

            // Actualizar el campo en el formulario
            if (updatedUser) {
              this.profileForm.patchValue({
                formaPagoPreferida: this.normalizeMetodoPago(updatedUser.formaPagoPreferida),
              });
            }

            this.profileMessage.set({
              text: 'Forma de pago actualizada correctamente.',
              type: 'success',
            });
          },
          error: (error: any) => {
            this.profileMessage.set({
              text: this.resolveApiError(error) ?? 'No se pudo actualizar la forma de pago.',
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
       this.unsubscribeMessage.set({ text: 'Selecciona al menos un miembro para continuar.', type: 'error' });
       return;
     }

     this.unsubscribeMessage.set(null);
     this.unsubmitting.set(true);

     this.eventosApi.requestUserUnsubscribe({ memberIds, reason: this.unsubscribeReason() }).pipe(
       finalize(() => this.unsubmitting.set(false)),
       takeUntilDestroyed(this.destroyRef),
     ).subscribe({
       next: () => {
         // Cerrar el formulario primero
         this.showUnsubscribeForm.set(false);
         // Mostrar el mensaje de éxito
         this.unsubscribeMessage.set({ text: '✅ Solicitud enviada correctamente. Recibirás noticias por correo.', type: 'success' });
         // Limpiar datos después de un tiempo
         setTimeout(() => {
           this.unsubscribeSelected.set(new Set());
           this.unsubscribeReason.set('');
         }, 300);
         // Limpiar el mensaje después de 8 segundos para que sea visible más tiempo
         setTimeout(() => this.unsubscribeMessage.set(null), 8000);
       },
       error: (err) => {
         this.unsubscribeMessage.set({
           text: this.resolveApiError(err) ?? 'No se pudo enviar la solicitud.',
           type: 'error',
         });
       },
     });
   }

  // ── Relaciones ────────────────────────────────────────────────────────

  private loadRelaciones(): void {
    const currentUserId = this.authService.currentUserId?.trim();
    if (!currentUserId) {
      this.relaciones.set([]);
      return;
    }

    this.loadingRelaciones.set(true);
    this.errorRelaciones.set(null);

    this.eventosApi.getRelacionesByUsuario(currentUserId).pipe(
      finalize(() => this.loadingRelaciones.set(false)),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe({
      next: (relaciones) => {
        this.relaciones.set(relaciones);
      },
      error: () => {
        this.errorRelaciones.set('No pudimos cargar tus relaciones.');
        this.relaciones.set([]);
      },
    });
  }

  protected getRelacionadoNombre(relacion: RelacionUsuario): string {
    const currentUserId = this.authService.currentUserId?.trim();
    const esOrigen = relacion.usuarioOrigen.id === currentUserId;
    const usuario = esOrigen ? relacion.usuarioDestino : relacion.usuarioOrigen;

    const nombre = usuario.nombre?.trim() ?? '';
    const apellidos = usuario.apellidos?.trim() ?? '';
    const nombreCompleto = usuario.nombreCompleto?.trim() ?? '';

    if (nombreCompleto) return nombreCompleto;
    if (nombre && apellidos) return `${nombre} ${apellidos}`;
    if (nombre) return nombre;
    return 'Usuario desconocido';
  }

  protected deleteRelacion(relacion: RelacionUsuario): void {
    // Abrir el modal de confirmación en lugar de usar window.confirm
    this.relacionToDelete.set(relacion);
    this.showDeleteRelacionModal.set(true);
  }

  protected onConfirmDeleteRelacion(confirmed: boolean): void {
    const relacion = this.relacionToDelete();
    // cerrar modal
    this.showDeleteRelacionModal.set(false);
    this.relacionToDelete.set(null);

    if (!confirmed || !relacion) {
      return;
    }

    this.deletingRelacionId.set(relacion.id);
    this.deleteRelacionMessage.set(null);

    this.eventosApi.deleteRelacion(relacion.id).pipe(
      finalize(() => this.deletingRelacionId.set(null)),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe({
      next: () => {
        this.relaciones.set(this.relaciones().filter((r) => r.id !== relacion.id));
        this.deleteRelacionMessage.set({ text: 'Relación eliminada correctamente.', type: 'success' });
      },
      error: (error) => {
        this.deleteRelacionMessage.set({
          text: this.resolveApiError(error) ?? 'No se pudo eliminar la relación.',
          type: 'error',
        });
      },
    });
  }

  protected cancelDeleteRelacion(): void {
    this.showDeleteRelacionModal.set(false);
    this.relacionToDelete.set(null);
  }

  protected getRelacionado(relacion: RelacionUsuario) {
    const currentUserId = this.authService.currentUserId?.trim();
    const esOrigen = relacion.usuarioOrigen.id === currentUserId;

    return esOrigen ? relacion.usuarioDestino : relacion.usuarioOrigen;
  }

  protected isRelacionInfantil(relacion: RelacionUsuario): boolean {
    return this.getRelacionado(relacion).tipoPersona === 'infantil';
  }

  protected editRelacionIfInfantil(relacion: RelacionUsuario): void {
    const usuario = this.getRelacionado(relacion);
    const usuarioId = usuario.id;

    if (usuario.tipoPersona !== 'infantil' || !usuarioId) {
      return;
    }

    this.authService.getUsuario(usuarioId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
      next: (usuarioCompleto) => {
        this.editingChildId.set(usuarioId);
        this.activeTab.set('identidad');

        this.profileForm.patchValue({
          nombre: usuarioCompleto.nombre?.trim() ?? '',
          apellidos: usuarioCompleto.apellidos?.trim() ?? '',
          telefono: usuarioCompleto.telefono ?? '',
          direccion: usuarioCompleto.direccion ?? '',
          fechaNacimiento: this.normalizeDateForInput(usuarioCompleto.fechaNacimiento),
          formaPagoPreferida: this.normalizeMetodoPago(usuarioCompleto.formaPagoPreferida),
        });

        document.querySelector('.form-card')?.scrollIntoView({
          behavior: 'smooth',
        });
      },
      error: (error) => {
        this.profileMessage.set({
          text: this.resolveApiError(error) ?? 'No se han podido cargar los datos del usuario infantil.',
          type: 'error',
        });
      },
    });
  }

  protected cancelEditChild(): void {
    if (!this.editingChildId()) return;

    this.editingChildId.set(null);

    this.loadRelaciones();

    this.authService.getMe().pipe(
      takeUntilDestroyed(this.destroyRef),
    ).subscribe({
      next: (user) => this.patchProfileForm(user),
      error: () => {
        const fallback = this.authService.getUser();
        this.patchProfileForm(fallback);
      },
    });
  }

   private loadProfile(): void {
     const fallback = this.authService.getUser();
     this.patchProfileForm(fallback);
     this.loading.set(false);

     this.authService
       .getMe()
       .pipe(
         takeUntilDestroyed(this.destroyRef),
       )
       .subscribe({
         next: (user) => {
           this.patchProfileForm(user);
         },
         error: () => {
           // Mantener el fallback
         },
       });
   }

  private patchProfileForm(user: any): void {
    this.profileForm.setValue({
      nombre: String(user?.nombre ?? ''),
      apellidos: String(user?.apellidos ?? ''),
      direccion: String(user?.direccion ?? ''),
      telefono: String(user?.telefono ?? ''),
      fechaNacimiento: this.normalizeDateForInput(user?.fechaNacimiento),
      formaPagoPreferida: this.normalizeMetodoPago(user?.formaPagoPreferida),
    });
    this.profileForm.markAsPristine();
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

  // ── Biometric methods ──────────────────────────────────────────────────

   private loadBiometricCredentials(): void {
     if (!this.biometricService.isSupported()) return;

     this.loadingBiometric.set(true);
     this.biometricService.listCredentials()
       .pipe(
         finalize(() => this.loadingBiometric.set(false)),
         takeUntilDestroyed(this.destroyRef),
       )
       .subscribe({
         next: (creds) => this.biometricCredentials.set(creds),
         error: () => this.biometricCredentials.set([]),
       });
   }

   private loadFamilyMembers(): void {
     this.eventosStore.loadPersonasMias()
       .pipe(takeUntilDestroyed(this.destroyRef))
       .subscribe();
   }

  protected registerPasskey(): void {
    if (this.registeringBiometric()) return;

    this.registeringBiometric.set(true);
    this.biometricMessage.set(null);

    this.biometricService.registerPasskey()
      .then((cred) => {
        this.biometricCredentials.update((list) => [cred, ...list]);
        this.biometricMessage.set({
          text: `✅ Dispositivo "${cred.deviceName}" registrado. Ya puedes usar la huella para entrar.`,
          type: 'success',
        });
      })
      .catch((err: unknown) => {
        const msg = err instanceof Error ? err.message : 'No se pudo registrar el dispositivo.';
        if (!msg.toLowerCase().includes('cancel')) {
          this.biometricMessage.set({ text: msg, type: 'error' });
        }
      })
      .finally(() => this.registeringBiometric.set(false));
  }

  protected deletePasskey(id: string): void {
    this.deletingBiometricId.set(id);
    this.biometricMessage.set(null);

    this.biometricService.deleteCredential(id)
      .pipe(
        finalize(() => this.deletingBiometricId.set(null)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.biometricCredentials.update((list) => list.filter((c) => c.id !== id));
          this.biometricMessage.set({ text: 'Credencial eliminada correctamente.', type: 'success' });
        },
        error: () => {
          this.biometricMessage.set({ text: 'No se pudo eliminar la credencial.', type: 'error' });
        },
      });
  }

  // ── Push Notifications ─────────────────────────────────────────────────

  protected readonly pushNotificationsEnabled = signal(false);
  protected readonly togglingNotifications = signal(false);
  protected readonly testingNotification = signal(false);
  protected readonly notificationMessage = signal<Feedback | null>(null);

  public readonly pushNotificationService = inject(PushNotificationService);


  private loadPushNotificationStatus(): void {
    if (!this.pushNotificationService.isSupported()) {
      return;
    }

    this.pushNotificationService.checkIfEnabled()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (enabled) => {
          this.pushNotificationsEnabled.set(enabled);
        },
        error: () => {
          this.pushNotificationsEnabled.set(false);
        },
      });
  }

  protected togglePushNotifications(): void {
    console.log('🔔 togglePushNotifications called');
    console.log('togglingNotifications:', this.togglingNotifications());
    console.log('isSupported:', this.pushNotificationService.isSupported());
    if (this.togglingNotifications()) return;

    this.togglingNotifications.set(true);
    this.notificationMessage.set(null);

    const shouldEnable = !this.pushNotificationsEnabled();
    console.log('shouldEnable:', shouldEnable);
    if (shouldEnable) {
      console.log('🔔 Calling requestPermissionAndEnable()');
      this.pushNotificationService.requestPermissionAndEnable()
        .pipe(
          finalize(() => {
            console.log('🔔 finalize: resetting togglingNotifications');
            this.togglingNotifications.set(false);
          }),
          takeUntilDestroyed(this.destroyRef)
        )
      .subscribe({
          next: (success) => {
            console.log('✅ requestPermissionAndEnable result:', success);
            if (success) {
              this.pushNotificationsEnabled.set(true);
              this.notificationMessage.set({
                text: '✅ Notificaciones push habilitadas correctamente.',
                type: 'success',
              });
            } else {
              this.notificationMessage.set({
                text: 'Permiso de notificaciones rechazado. Por favor, revisa tu configuración del navegador.',
                type: 'error',
              });
            }
          },
          error: (err) => {
            console.error('❌ requestPermissionAndEnable error:', err);
            this.notificationMessage.set({
              text: 'Error al habilitar las notificaciones.',
              type: 'error',
            });
          },
          complete: () => {
            console.log('🔔 Observable completed');
          }
        });
    } else {
      this.pushNotificationService.disablePushNotifications()
        .pipe(finalize(() => this.togglingNotifications.set(false)), takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: (success) => {
            if (success) {
              this.pushNotificationsEnabled.set(false);
              this.notificationMessage.set({
                text: '✅ Notificaciones push deshabilitadas.',
                type: 'success',
              });
            } else {
              this.notificationMessage.set({
                text: 'No se pudo deshabilitar las notificaciones.',
                type: 'error',
              });
            }
          },
          error: () => {
            this.notificationMessage.set({
              text: 'Error al deshabilitar las notificaciones.',
              type: 'error',
            });
          },
        });
    }
  }

  protected sendTestNotification(): void {
    if (this.testingNotification()) return;

    this.testingNotification.set(true);
    this.notificationMessage.set(null);

    this.pushNotificationService.sendTestNotification()
      .pipe(
        finalize(() => this.testingNotification.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.notificationMessage.set({
            text: '�� Notificación de prueba enviada.',
            type: 'success',
          });
        },
        error: () => {
          this.notificationMessage.set({
            text: 'No se pudo enviar la notificación de prueba.',
            type: 'error',
          });
        },
      });
  }
}
