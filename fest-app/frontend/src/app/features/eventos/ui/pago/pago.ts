import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize, of, switchMap, catchError } from 'rxjs';

import { AuthService } from '../../../../core/auth/auth';
import { CtaButton } from '../../../shared/components/cta-button/cta-button';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventosApi } from '../../data/eventos.api';
import { Inscripcion, METODOS_PAGO_OPTIONS, MetodoPago } from '../../domain/eventos.models';

@Component({
  selector: 'app-pago',
  standalone: true,
  imports: [MobileHeader, CurrencyPipe, CtaButton],
  templateUrl: './pago.html',
  styleUrl: './pago.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Pago {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);

  private readonly eventoId = this.route.snapshot.paramMap.get('id') ?? '';

  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly inscription = signal<Inscripcion | null>(null);
  protected readonly selectedMetodoPago = signal<MetodoPago>('bizum');
  protected readonly hasNewInscriptions = signal(false);

  protected readonly metodosPago = METODOS_PAGO_OPTIONS;

  protected readonly totalAmount = computed(() =>
    Number(this.inscription()?.importeTotal ?? 0),
  );

  protected readonly totalPaidAmount = computed(() =>
    Number(this.inscription()?.importePagado ?? 0),
  );

  protected readonly totalPendingAmount = computed(() =>
    Math.max(0, this.totalAmount() - this.totalPaidAmount()),
  );

  protected readonly needsPayment = computed(() =>
    this.totalPendingAmount() > 0,
  );

  protected readonly isCashSelected = computed(() =>
    this.selectedMetodoPago() === 'efectivo',
  );

  protected readonly payNowDisabled = computed(() =>
    this.saving() || !this.needsPayment() || this.isCashSelected(),
  );

  protected readonly payNowLabel = computed(() => {
    if (this.saving()) return 'Guardando...';
    if (this.hasNewInscriptions()) return 'Confirmar y pagar';
    return 'Pagar ahora';
  });

  constructor() {
    this.loadPreferredPaymentMethod();
    this.loadState();
    this.loadInscription();
  }

  protected goBack(): void {
    void this.router.navigate(['/eventos', this.eventoId, 'actividades']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected onMetodoPagoChange(value: string): void {
    if (!this.isMetodoPago(value)) return;

    this.selectedMetodoPago.set(value);
  }

  protected pagarAhora(): void {
    if (this.payNowDisabled()) return;

    this.persistMetodoPagoAndContinue(['/eventos', this.eventoId, 'credencial']);
  }

  protected pagarMasTarde(): void {
    if (this.saving()) return;

    this.persistMetodoPagoAndContinue(['/eventos', 'inscripciones']);
  }

  private loadPreferredPaymentMethod(): void {
    const preferred = this.authService.userSignal()?.['formaPagoPreferida'];

    if (this.isMetodoPago(preferred)) {
      this.selectedMetodoPago.set(preferred);
    }
  }

  private loadState(): void {
    this.hasNewInscriptions.set(history.state?.hasNewInscriptions === true);
  }

  private loadInscription(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    const inscriptionId = this.getInscriptionIdFromState();

    const request$ = inscriptionId
      ? this.eventosApi.getInscripcion(inscriptionId).pipe(
        catchError(() => this.eventosApi.getInscripcionActualPorEvento(this.eventoId)),
      )
      : this.eventosApi.getInscripcionActualPorEvento(this.eventoId);

    request$
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (inscription) => this.handleInscriptionLoaded(inscription),
        error: () => this.handleLoadError(),
      });
  }

  private handleInscriptionLoaded(inscription: Inscripcion | null): void {
    if (!inscription) {
      this.inscription.set(null);
      this.errorMessage.set('No encontramos una inscripción para este evento.');
      return;
    }

    this.inscription.set(inscription);

    if (this.isMetodoPago(inscription.formaPagoPreferida)) {
      this.selectedMetodoPago.set(inscription.formaPagoPreferida);
    }
  }

  private handleLoadError(): void {
    this.inscription.set(null);
    this.errorMessage.set('No pudimos cargar el pago del evento.');
  }

  private persistMetodoPagoAndContinue(targetCommands: unknown[]): void {
    this.saving.set(true);
    this.errorMessage.set(null);

    this.eventosApi
      .actualizarFormaPagoPreferida(this.selectedMetodoPago())
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          void this.router.navigate(targetCommands);
        },
        error: () => {
          this.errorMessage.set('No se pudo guardar la forma de pago. Inténtalo de nuevo.');
        },
      });
  }

  private getInscriptionIdFromState(): string | null {
    const value = history.state?.inscripcionId;

    return typeof value === 'string' && value.trim().length > 0
      ? value.trim()
      : null;
  }

  private isMetodoPago(value: unknown): value is MetodoPago {
    return typeof value === 'string'
      && this.metodosPago.some((metodo) => metodo.value === value);
  }
}
