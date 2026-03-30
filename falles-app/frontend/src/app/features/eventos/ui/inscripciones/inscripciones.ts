import { CurrencyPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { forkJoin, map, of, switchMap } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { formatLocalDate, formatTime, normalizeDateKey } from '../../../../core/utils/date.utils';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventosApi, InscripcionApi } from '../../data/eventos.api';

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
  protected readonly inscripciones = signal<InscripcionApi[]>([]);

  constructor() {
    this.loadInscripciones();
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected eventDate(inscripcion: InscripcionApi): string {
    return formatLocalDate(inscripcion.evento.fechaEvento);
  }

  protected eventTime(inscripcion: InscripcionApi): string {
    return formatTime(inscripcion.evento.horaInicio);
  }

  protected lineasLabel(inscripcion: InscripcionApi): string {
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
      .getInscripcionesMias()
      .pipe(
        switchMap((inscripciones) => {
          if (!inscripciones.length) {
            return of([] as InscripcionApi[]);
          }

          return forkJoin(inscripciones.map((inscripcion) => this.eventosApi.getInscripcion(inscripcion.id)));
        }),
        map((inscripciones) => [...inscripciones].sort((a, b) => this.compareInscripciones(a, b))),
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

  private compareInscripciones(a: InscripcionApi, b: InscripcionApi): number {
    const dateA = `${normalizeDateKey(a.evento.fechaEvento)}T${a.evento.horaInicio ?? '00:00'}:00`;
    const dateB = `${normalizeDateKey(b.evento.fechaEvento)}T${b.evento.horaInicio ?? '00:00'}:00`;
    return new Date(dateA).getTime() - new Date(dateB).getTime();
  }
}
