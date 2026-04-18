import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize, map } from 'rxjs';
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

  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly inscription = signal<Inscripcion | null>(null);
  protected readonly selectedMetodoPago = signal<MetodoPago>('bizum');
  protected readonly hasNewInscriptions = signal(false);

  protected readonly metodosPago = METODOS_PAGO_OPTIONS;

  protected readonly totalAmount = computed(() => {
    const inscription = this.inscription();
    if (!inscription) return 0;
    return Number(inscription.importeTotal ?? 0);
  });

  protected readonly totalPaidAmount = computed(() => {
    const inscription = this.inscription();
    if (!inscription) return 0;
    return Number(inscription.importePagado ?? 0);
  });

  protected readonly totalPendingAmount = computed(() =>
    Math.max(0, this.totalAmount() - this.totalPaidAmount()),
  );

  protected readonly needsPayment = computed(() => {
    return this.totalPendingAmount() > 0;
  });

  protected readonly isCashSelected = computed(() => this.selectedMetodoPago() === 'efectivo');
  protected readonly payNowDisabled = computed(() => this.saving() || this.isCashSelected());
  protected readonly payNowLabel = computed(() => {
    if (this.saving()) return 'Guardando...';
    return this.hasNewInscriptions() ? 'Confirmar y pagar' : 'Pagar';
  });

  constructor() {
    this.loadData();
  }

  protected goBack(): void {
    void this.router.navigate(['/eventos', this.eventId(), 'actividades']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected onMetodoPagoChange(value: string): void {
    const normalized = value as MetodoPago;
    if (this.metodosPago.some((m) => m.value === normalized)) {
      this.selectedMetodoPago.set(normalized);
    }
  }

  protected pagarAhora(): void {
    if (this.payNowDisabled()) return;
    this.persistMetodoPagoAndContinue('/eventos/' + this.eventId() + '/credencial');
  }

  protected pagarMasTarde(): void {
    this.persistMetodoPagoAndContinue('/eventos/inscripciones');
  }

  private loadData(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    const preferred = this.authService.userSignal()?.['formaPagoPreferida'];
    if (typeof preferred === 'string' && this.metodosPago.some((m) => m.value === preferred)) {
      this.selectedMetodoPago.set(preferred as MetodoPago);
    }

    const inscriptionIdFromState = history.state?.inscripcionId as string | undefined;
    this.hasNewInscriptions.set(history.state?.hasNewInscriptions === true);

    if (inscriptionIdFromState && inscriptionIdFromState.trim().length > 0) {
      this.eventosApi
        .getInscripcion(inscriptionIdFromState)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: (inscription) => this.finishLoad(inscription),
          error: () => this.loadInscriptionByEventFallback(),
        });
      return;
    }

    this.loadInscriptionByEventFallback();
  }

  private loadInscriptionByEventFallback(): void {
    this.eventosApi
      .getInscripcionesMiasCollection()
      .pipe(
        map((items) => items.find((item) => item.evento.id === this.eventId()) ?? null),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (inscription) => {
          if (!inscription) {
            this.inscription.set(null);
            this.errorMessage.set('No encontramos una inscripción para este evento.');
            this.loading.set(false);
            return;
          }

          this.finishLoad(inscription);
        },
        error: () => {
          this.inscription.set(null);
          this.errorMessage.set('No pudimos cargar el pago del evento.');
          this.loading.set(false);
        },
      });
  }

  private finishLoad(inscription: Inscripcion): void {
    this.inscription.set(inscription);
    this.loading.set(false);
  }

  private persistMetodoPagoAndContinue(targetUrl: string): void {
    if (this.saving()) return;

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
          void this.router.navigateByUrl(targetUrl);
        },
        error: () => {
          this.errorMessage.set('No se pudo guardar la forma de pago. Intentalo de nuevo.');
        },
      });
  }

  private eventId(): string {
    return this.route.snapshot.paramMap.get('id') ?? '';
  }
}

