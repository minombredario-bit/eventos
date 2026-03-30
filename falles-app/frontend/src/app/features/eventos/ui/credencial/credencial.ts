import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { CurrencyPipe, DatePipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { combineLatest, distinctUntilChanged, filter, map, switchMap } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventosApi, InscripcionApi } from '../../data/eventos.api';

const QR_SIZE = 21;

@Component({
  selector: 'app-credencial',
  standalone: true,
  imports: [MobileHeader, DatePipe, CurrencyPipe],
  templateUrl: './credencial.html',
  styleUrl: './credencial.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Credencial {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);

  protected readonly eventId          = signal('');
  protected readonly loading          = signal(true);
  protected readonly processingLineId = signal<string | null>(null);
  protected readonly errorMessage     = signal<string | null>(null);
  protected readonly actionError      = signal<string | null>(null);
  protected readonly inscription      = signal<InscripcionApi | null>(null);

  constructor() {
    combineLatest([
      this.route.paramMap.pipe(map((p) => p.get('id') ?? '')),
      this.route.queryParamMap.pipe(map((p) => p.get('inscripcionId'))),
    ])
      .pipe(
        filter(([eventId]) => Boolean(eventId)),
        distinctUntilChanged(
          ([prevE, prevI], [nextE, nextI]) => prevE === nextE && prevI === nextI,
        ),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(([eventId, inscriptionId]) => {
        this.eventId.set(eventId);
        this.loadInscription(eventId, inscriptionId);
      });
  }

  protected readonly credential = computed(() => {
    const inscription = this.inscription();
    if (!inscription) return null;

    const user = this.authService.getUser();
    const holderName =
      typeof user?.nombre === 'string' && typeof user?.apellidos === 'string'
        ? `${user.nombre} ${user.apellidos}`
        : 'Titular';

    return {
      eventTitle: inscription.evento.titulo,
      eventDate: `${inscription.evento.fechaEvento}T${inscription.evento.horaInicio ?? '00:00'}:00`,
      holderName,
      eventZone: inscription.evento.lugar ?? 'Acceso principal',
      qrToken: inscription.codigo,
      paymentLabel: this.paymentStatusLabel(inscription.estadoPago),
      paymentAmount: inscription.importeTotal,
      lines: inscription.lineas,
    };
  });

  protected readonly canManageLines = computed(() => {
    const inscription = this.inscription();
    if (!inscription) return false;
    return inscription.evento.inscripcionAbierta === true && inscription.importePagado === 0;
  });

  protected readonly qrRows = computed(() =>
    this.buildPseudoQr(this.credential()?.qrToken ?? 'EMPTY'),
  );

  protected goBack(): void {
    void this.router.navigate(['/eventos', this.eventId(), 'menus']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected canCancelLine(lineState: string): boolean {
    return this.canManageLines() && lineState !== 'cancelada';
  }

  protected lineMenuLabel(line: InscripcionApi['lineas'][number]): string {
    return line.estadoLinea === 'cancelada' ? 'Sin menú' : line.nombreMenuSnapshot;
  }

  protected lineStatusLabel(lineState: string): string {
    if (lineState === 'cancelada') return 'Línea cancelada · Sin menú';
    if (!this.canManageLines()) return 'No se puede eliminar · inscripción cerrada o con pagos registrados';
    return 'Estado no editable';
  }

  // ── Acciones con Observables ──────────────────────────────────────────

  protected cancelLine(lineId: string): void {
    const inscription = this.inscription();
    if (!inscription || this.processingLineId()) return;

    this.actionError.set(null);
    this.processingLineId.set(lineId);

    this.eventosApi
      .cancelarLineaInscripcion(inscription.id, lineId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.reloadInscription(inscription.id);
          this.processingLineId.set(null);
        },
        error: () => {
          this.actionError.set('No se pudo cancelar la línea. Verificá si la inscripción sigue abierta y sin pagos.');
          this.processingLineId.set(null);
        },
      });
  }

  protected isProcessingLine(lineId: string): boolean {
    return this.processingLineId() === lineId;
  }

  // ── Cargas internas con Observables ───────────────────────────────────

  private loadInscription(eventId: string, inscriptionId: string | null): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    if (inscriptionId) {
      this.reloadInscription(inscriptionId);
      return;
    }

    this.eventosApi
      .getInscripcionesMias()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (inscripciones) => {
          const selected = inscripciones.find((i) => i.evento.id === eventId);
          if (!selected) {
            this.inscription.set(null);
            this.errorMessage.set('Todavía no tenés una inscripción activa para este evento.');
            this.loading.set(false);
            return;
          }
          this.reloadInscription(selected.id);
        },
        error: () => {
          this.inscription.set(null);
          this.errorMessage.set('No pudimos cargar tu credencial en este momento.');
          this.loading.set(false);
        },
      });
  }

  private reloadInscription(inscriptionId: string): void {
    this.eventosApi
      .getInscripcion(inscriptionId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (detail) => {
          this.inscription.set(detail);
          this.loading.set(false);
        },
        error: () => {
          this.inscription.set(null);
          this.errorMessage.set('No pudimos cargar tu credencial en este momento.');
          this.loading.set(false);
        },
      });
  }

  private paymentStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      pagado: 'Pago confirmado',
      pendiente: 'Pago pendiente',
      parcial: 'Pago parcial',
      no_requiere_pago: 'Sin coste',
    };
    return labels[status] ?? 'Estado de pago';
  }

  private buildPseudoQr(token: string): boolean[][] {
    return Array.from({ length: QR_SIZE }, (_, row) =>
      Array.from({ length: QR_SIZE }, (_, col) => {
        const charCode = token.charCodeAt((row * QR_SIZE + col) % token.length);
        return (charCode + row + col) % 3 === 0;
      }),
    );
  }
}
