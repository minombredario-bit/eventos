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
import { combineLatest, distinctUntilChanged, filter, map } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventosApi } from '../../data/eventos.api';
import { Inscripcion } from '../../domain/eventos.models';

const CREDENTIAL_OPEN_WINDOW_MINUTES = 60;

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
  protected readonly errorMessage     = signal<string | null>(null);
  protected readonly inscription      = signal<Inscripcion | null>(null);

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
      eventDate: this.buildEventDateTime(inscription.evento.fechaEvento, inscription.evento.horaInicio),
      holderName,
      eventZone: inscription.evento.lugar ?? 'Acceso principal',
      qrToken: inscription.codigo,
      qrImageUrl: this.buildQrImageUrl(inscription.codigo),
      paymentLabel: this.paymentStatusLabel(inscription.estadoPago),
      paymentAmount: inscription.importeTotal,
      lines: inscription.lineas,
    };
  });

  protected goBack(): void {
    void this.router.navigate(['/eventos', this.eventId(), 'actividades']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected lineActividadLabel(line: Inscripcion['lineas'][number]): string {
    return line.estadoLinea === 'cancelada' ? 'Sin actividad' : line.nombreActividadSnapshot;
  }

  protected lineStatusLabel(lineState: string): string {
    if (lineState === 'cancelada') return 'Línea cancelada · Sin actividad';
    return 'Línea confirmada';
  }

  // ── Cargas internas con Observables ───────────────────────────────────

  private loadInscription(eventId: string, inscriptionId: string | null): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    if (inscriptionId) {
      this.reloadInscription(inscriptionId, eventId);
      return;
    }

    this.eventosApi
      .getMisInscripciones()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (inscripciones) => {
          const selected = inscripciones.find((i) => i.evento.id === eventId);

          if (!selected) {
            this.inscription.set(null);
            this.errorMessage.set('No hay una credencial disponible para este horario.');
            this.loading.set(false);
            return;
          }

          this.reloadInscription(selected.id, eventId);
        },
        error: () => {
          this.inscription.set(null);
          this.errorMessage.set('No pudimos cargar tu credencial en este momento.');
          this.loading.set(false);
        },
      });
  }

  private reloadInscription(inscriptionId: string, expectedEventId: string): void {
    this.eventosApi
      .getInscripcion(inscriptionId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (detail) => {
          if (detail.evento.id !== expectedEventId) {
            this.inscription.set(null);
            this.errorMessage.set('La credencial solicitada no corresponde al evento actual.');
            this.loading.set(false);
            return;
          }

          if (!this.isCredentialWindowOpen(detail.evento.fechaEvento, detail.evento.horaInicio)) {
            this.inscription.set(null);
            this.errorMessage.set('La credencial estará disponible una hora antes del evento.');
            this.loading.set(false);
            return;
          }

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

  private buildEventDateTime(fechaEventoRaw: string, horaInicioRaw?: string | null): string {
    const fechaEvento = String(fechaEventoRaw ?? '').trim();
    const horaInicio = String(horaInicioRaw ?? '').trim();

    if (!fechaEvento) {
      return '';
    }

    // Si no hay hora, devolvemos la fecha tal cual (si ya es ISO completo) o con 00:00.
    if (!horaInicio) {
      return fechaEvento.includes('T') ? fechaEvento : `${fechaEvento}T00:00:00`;
    }

    const datePart = fechaEvento.includes('T') ? fechaEvento.split('T')[0] : fechaEvento;
    const timePart = this.extractTimePart(horaInicio) ?? '00:00:00';

    return `${datePart}T${timePart}`;
  }

  private extractTimePart(value: string): string | null {
    const input = value.trim();
    if (!input) {
      return null;
    }

    // Acepta valores tipo "14:00" o "14:00:00".
    if (/^\d{2}:\d{2}(:\d{2})?$/.test(input)) {
      return input.length === 5 ? `${input}:00` : input;
    }

    // Acepta ISO tipo "1970-01-01T14:00:00+00:00".
    const isoMatch = input.match(/T(\d{2}:\d{2}(?::\d{2})?)/);
    if (isoMatch?.[1]) {
      return isoMatch[1].length === 5 ? `${isoMatch[1]}:00` : isoMatch[1];
    }

    return null;
  }

  private isCredentialWindowOpen(fechaEventoRaw: string, horaInicioRaw?: string | null): boolean {
    const eventDateIso = this.buildEventDateTime(fechaEventoRaw, horaInicioRaw);
    if (!eventDateIso) {
      return false;
    }

    const eventTime = new Date(eventDateIso).getTime();
    if (Number.isNaN(eventTime)) {
      return false;
    }

    const openTime = eventTime - CREDENTIAL_OPEN_WINDOW_MINUTES * 60 * 1000;
    return Date.now() >= openTime;
  }

  private buildQrImageUrl(token: string): string {
    const safeToken = encodeURIComponent(token || 'EMPTY');

    // QR as image for a robust and scanner-friendly credential view.
    return `https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=${safeToken}`;
  }
}
