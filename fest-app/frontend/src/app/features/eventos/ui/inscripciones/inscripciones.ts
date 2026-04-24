import { CurrencyPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { map } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { formatLocalDate, formatTime, hasValidTime, normalizeDateKey } from '../../../../core/utils/date.utils';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventosApi } from '../../data/eventos.api';
import { Inscripcion } from '../../domain/eventos.models';

@Component({
  selector: 'app-inscripciones',
  standalone: true,
  imports: [MobileHeader, RouterLink, CurrencyPipe],
  templateUrl: './inscripciones.html',
  styleUrl: './inscripciones.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Inscripciones {
  private readonly destroyRef = inject(DestroyRef);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);
  private readonly router = inject(Router);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly inscripciones = signal<Inscripcion[]>([]);

  constructor() {
    this.loadInscripciones();
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected eventRouteId(inscripcion: Inscripcion): string {
    return this.normalizeEventoId(inscripcion.evento.id ?? '');
  }

  protected eventTitle(inscripcion: Inscripcion): string {
    const title = inscripcion.evento.titulo?.trim();
    if (title?.length) {
      return title;
    }

    return 'Evento';
  }

  protected eventDate(inscripcion: Inscripcion): string {
    const date = inscripcion.evento.fechaEvento?.trim();
    if (date?.length) {
      return formatLocalDate(date);
    }

    return 'Fecha por confirmar';
  }

  protected eventTime(inscripcion: Inscripcion): string {
    const time = inscripcion.evento.horaInicio?.trim();
    if (time?.length) {
      return formatTime(time);
    }

    return 'Sin hora';
  }

  protected eventLocation(inscripcion: Inscripcion): string {
    const location = inscripcion.evento.lugar?.trim();
    if (location?.length) {
      return location;
    }

    return 'Lugar por confirmar';
  }

  protected eventDescription(inscripcion: Inscripcion): string {
    const description = inscripcion.evento.descripcion?.trim();
    if (description?.length) {
      return description;
    }

    return 'Descripción no disponible.';
  }

  protected isEventoCerrado(inscripcion: Inscripcion): boolean {
    return inscripcion.evento.inscripcionAbierta === false;
  }

  protected estadoInscripcionEventoLabel(inscripcion: Inscripcion): string {
    return this.isEventoCerrado(inscripcion) ? 'Cerrada (caducada)' : 'Abierta';
  }

  protected cierreEventoHint(inscripcion: Inscripcion): string {
    if (!this.isEventoCerrado(inscripcion)) {
      return 'Puedes gestionar tu inscripción.';
    }

    const fechaLimite = inscripcion.evento.fechaLimiteInscripcion?.trim();
    if (!fechaLimite?.length) {
      return 'Plazo de inscripción vencido.';
    }

    return `Plazo vencido: ${formatLocalDate(fechaLimite)}.`;
  }

  protected lineasLabel(inscripcion: Inscripcion): string {
    const count = inscripcion.lineas.length;
    return count === 1 ? '1 línea' : `${count} líneas`;
  }

  protected estadoInscripcionLabel(estado: string): string {
    const labels: Record<string, string> = {
      pendiente: 'Pendiente',
      confirmada: 'Confirmada',
      cancelada: 'Cancelada',
      lista_espera: 'Lista de espera',
    };
    return labels[estado] ?? 'Estado desconocido';
  }

  protected estadoPagoLabel(estado: string): string {
    const labels: Record<string, string> = {
      pagado: 'Pagado',
      parcial: 'Pago parcial',
      pendiente: 'Pago pendiente',
      no_requiere_pago: 'Sin coste',
      devuelto: 'Devuelto',
      cancelado: 'Cancelado',
    };
    return labels[estado] ?? 'Pago desconocido';
  }

  private loadInscripciones(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.eventosApi
      .getInscripcionesMiasCollection()
      .pipe(
        map((inscripciones) => inscripciones
          .map((inscripcion) => this.normalizeInscripcion(inscripcion))
          .sort((a, b) => this.compareInscripciones(a, b))),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (inscripciones) => {
          this.inscripciones.set(inscripciones);
          this.loading.set(false);
        },
        error: () => {
          this.inscripciones.set([]);
          this.errorMessage.set('No pudimos cargar tus eventos inscritos. Probá nuevamente.');
          this.loading.set(false);
        },
      });
  }

  private compareInscripciones(a: Inscripcion, b: Inscripcion): number {
    const dateA = this.resolveSortDateTime(a);
    const dateB = this.resolveSortDateTime(b);
    if (dateA !== dateB) {
      return dateA.localeCompare(dateB);
    }
    return a.id.localeCompare(b.id);
  }

  private resolveSortDateTime(inscripcion: Inscripcion): string {
    const rawDate = (inscripcion.evento.fechaEvento ?? '').trim();
    const normalizedDate = rawDate.length ? normalizeDateKey(rawDate) : '9999-12-31';

    const rawTime = (inscripcion.evento.horaInicio ?? '').trim();
    const normalizedTime = hasValidTime(rawTime) ? rawTime.slice(0, 5) : '23:59';

    return `${normalizedDate}T${normalizedTime}:00`;
  }

  private normalizeInscripcion(inscripcion: Inscripcion): Inscripcion {
    const normalizedEventoId = this.normalizeEventoId(inscripcion.evento.id || '');
    return {
      ...inscripcion,
      evento: {
        ...inscripcion.evento,
        id: normalizedEventoId,
      },
    };
  }

  private normalizeEventoId(eventoId: string): string {
    const clean = eventoId.trim();
    return clean.startsWith('/api/eventos/')
      ? clean.slice('/api/eventos/'.length)
      : clean;
  }
}
