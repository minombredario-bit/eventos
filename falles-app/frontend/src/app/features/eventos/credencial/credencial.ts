import { ChangeDetectionStrategy, Component, computed, effect, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { firstValueFrom, map } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { EventosApi, InscripcionApi } from '../services/eventos-api';

const QR_SIZE = 21;

@Component({
  selector: 'app-credencial',
  standalone: true,
  imports: [MobileHeader, DatePipe],
  templateUrl: './credencial.html',
  styleUrl: './credencial.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Credencial {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);

  private readonly eventId = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id') ?? '')),
    { initialValue: '' }
  );

  private readonly inscriptionIdFromQuery = toSignal(
    this.route.queryParamMap.pipe(map((params) => params.get('inscripcionId'))),
    { initialValue: null }
  );

  protected readonly loading = signal(true);
  protected readonly processingLineId = signal<string | null>(null);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly actionError = signal<string | null>(null);
  protected readonly inscription = signal<InscripcionApi | null>(null);

  private readonly loadInscriptionEffect = effect(() => {
    const eventId = this.eventId();
    const inscriptionId = this.inscriptionIdFromQuery();

    if (!eventId) {
      return;
    }

    void this.loadInscription(eventId, inscriptionId);
  });

  protected readonly credential = computed(() => {
    const inscription = this.inscription();
    if (!inscription) {
      return null;
    }

    const user = this.authService.getUser();
    const holderName = typeof user?.nombre === 'string' && typeof user?.apellidos === 'string'
      ? `${user.nombre} ${user.apellidos}`
      : 'Titular';

    return {
      eventTitle: inscription.evento.titulo,
      eventDate: `${inscription.evento.fechaEvento}T${inscription.evento.horaInicio ?? '00:00'}:00`,
      holderName,
      eventZone: inscription.evento.lugar ?? 'Acceso principal',
      qrToken: inscription.codigo,
      lines: inscription.lineas,
    };
  });

  protected readonly canManageLines = computed(() => {
    const inscription = this.inscription();
    if (!inscription) {
      return false;
    }

    const inscripcionAbierta = inscription.evento.inscripcionAbierta === true;
    const unpaid = inscription.importePagado === 0;

    return inscripcionAbierta && unpaid;
  });

  protected readonly qrRows = computed(() => {
    const credential = this.credential();
    if (!credential) {
      return this.buildPseudoQr('EMPTY');
    }

    return this.buildPseudoQr(credential.qrToken);
  });

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
    if (lineState === 'cancelada') {
      return 'Línea cancelada · Sin menú';
    }

    if (!this.canManageLines()) {
      return 'No se puede eliminar · inscripción cerrada o con pagos registrados';
    }

    return 'Estado no editable';
  }

  protected async cancelLine(lineId: string): Promise<void> {
    const inscription = this.inscription();
    if (!inscription || this.processingLineId()) {
      return;
    }

    this.actionError.set(null);
    this.processingLineId.set(lineId);

    try {
      await firstValueFrom(this.eventosApi.cancelarLineaInscripcion(inscription.id, lineId));
      await this.reloadInscription(inscription.id);
    } catch {
      this.actionError.set('No se pudo cancelar la línea. Verificá si la inscripción sigue abierta y sin pagos.');
    } finally {
      this.processingLineId.set(null);
    }
  }

  protected isProcessingLine(lineId: string): boolean {
    return this.processingLineId() === lineId;
  }

  private async loadInscription(eventId: string, inscriptionId: string | null): Promise<void> {
    this.loading.set(true);
    this.errorMessage.set(null);

    try {
      if (inscriptionId) {
        await this.reloadInscription(inscriptionId);
        return;
      }

      const inscripciones = await firstValueFrom(this.eventosApi.getInscripcionesMias());
      const selected = inscripciones.find((item) => item.evento.id === eventId);

      if (!selected) {
        this.inscription.set(null);
        this.errorMessage.set('Todavía no tenés una inscripción activa para este evento.');
        return;
      }

      await this.reloadInscription(selected.id);
    } catch {
      this.inscription.set(null);
      this.errorMessage.set('No pudimos cargar tu credencial en este momento.');
    } finally {
      this.loading.set(false);
    }
  }

  private async reloadInscription(inscriptionId: string): Promise<void> {
    const detail = await firstValueFrom(this.eventosApi.getInscripcion(inscriptionId));
    this.inscription.set(detail);
  }

  private buildPseudoQr(token: string): boolean[][] {
    return Array.from({ length: QR_SIZE }, (_, row) => {
      return Array.from({ length: QR_SIZE }, (_, col) => {
        const charCode = token.charCodeAt((row * QR_SIZE + col) % token.length);
        return (charCode + row + col) % 3 === 0;
      });
    });
  }
}
