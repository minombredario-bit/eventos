import { CommonModule, NgClass } from '@angular/common';
import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import {
  formatDate,
  formatDay,
  formatMonth,
  getCurrentMonthKey, getMonthKey
} from '../../../../core/utils/date.utils';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventosApi } from '../../../eventos/data/eventos.api';
import { EventoAdminListado, EventosPage } from '../../../eventos/domain/eventos.models';

@Component({
  selector: 'app-admin-eventos',
  standalone: true,
  imports: [CommonModule, NgClass, MobileHeader],
  templateUrl: './eventos.html',
  styleUrl: './eventos.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminEventos {
  private static readonly PAGE_SIZE = 5;

  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly loading = signal(true);
  protected readonly actionLoadingId = signal<string | null>(null);
  protected readonly downloadingId = signal<string | null>(null);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly searchTerm = signal('');
  protected readonly monthOnly = signal(false);
  protected readonly eventosPage = signal<EventosPage>({
    items: [],
    totalPages: 0,
    totalItems: 0,
    page: 1,
    itemsPerPage: AdminEventos.PAGE_SIZE,
    hasNext: false,
    hasPrevious: false,
  });

  protected readonly formatDate = formatDate;
  protected readonly formatDay = formatDay;
  protected readonly formatMonth = formatMonth;

  protected readonly paginatedEventos = computed<EventoAdminListado[]>(() => this.eventosPage().items);
  protected readonly totalEventosFiltrados = computed<number>(() => this.eventosPage().totalItems);
  protected readonly currentPage = computed<number>(() => this.eventosPage().page);
  protected readonly totalPages = computed<number>(() =>
    Math.max(1, Math.ceil(this.totalEventosFiltrados() / AdminEventos.PAGE_SIZE))
  );
  protected readonly hasNextPage = computed<boolean>(() => this.eventosPage().hasNext);
  protected readonly hasPreviousPage = computed<boolean>(() => this.eventosPage().hasPrevious);
  protected readonly paginationStart = computed<number>(() => {
    if (this.totalEventosFiltrados() === 0) return 0;
    return (this.currentPage() - 1) * AdminEventos.PAGE_SIZE + 1;
  });
  protected readonly paginationEnd = computed<number>(() =>
    Math.min(this.currentPage() * AdminEventos.PAGE_SIZE, this.totalEventosFiltrados())
  );

  protected readonly monthChipLabel = computed(() => {
    const currentMonthKey = getCurrentMonthKey();
    const count = this.paginatedEventos().filter(
      (e) => getMonthKey(e.fechaEvento) === currentMonthKey
    ).length;
    return `Eventos del mes (${count})`;
  });
  protected readonly monthChipActive = computed(() => this.monthOnly());

  constructor() {
    this.loadEventos(1);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected crearEvento(): void {
    void this.router.navigate(['/admin/eventos/crear']);
  }

  protected setSearchTerm(value: string): void {
    this.searchTerm.set(value);
    this.loadEventos(1);
  }

  protected toggleMonthChip(): void {
    this.monthOnly.update((value) => !value);
    this.loadEventos(1);
  }

  protected clearFilters(): void {
    this.searchTerm.set('');
    this.monthOnly.set(false);
    this.loadEventos(1);
  }

  protected previousPage(): void {
    if (!this.hasPreviousPage()) return;
    this.loadEventos(this.currentPage() - 1);
  }

  protected nextPage(): void {
    if (!this.hasNextPage()) return;
    this.loadEventos(this.currentPage() + 1);
  }

  protected abrirEvento(id: string): void {
    void this.router.navigate(['/admin/eventos', id]);
  }

  protected actionLabel(evento: EventoAdminListado): string {
    if (evento.inscripcionAbierta) {
      return 'Cerrar inscripciones';
    }

    if ((evento.estado ?? '').toLowerCase() === 'borrador') {
      return 'Publicar evento';
    }

    return 'Sin acciones de estado';
  }

  protected canToggleState(evento: EventoAdminListado): boolean {
    const estado = (evento.estado ?? '').toLowerCase();
    return evento.inscripcionAbierta === true || estado === 'borrador';
  }

  protected statusLabel(evento: EventoAdminListado): string {
    if (evento.inscripcionAbierta) {
      return 'Abierto';
    }

    switch ((evento.estado ?? '').toLowerCase()) {
      case 'publicado':
        return 'Publicado';
      case 'cerrado':
        return 'Cerrado';
      case 'finalizado':
        return 'Finalizado';
      case 'cancelado':
        return 'Cancelado';
      default:
        return 'Borrador';
    }
  }

  protected statusTone(evento: EventoAdminListado): string {
    if (evento.inscripcionAbierta) {
      return 'is-open';
    }

    switch ((evento.estado ?? '').toLowerCase()) {
      case 'publicado':
        return 'is-published';
      case 'cerrado':
        return 'is-closed';
      case 'finalizado':
        return 'is-finished';
      case 'cancelado':
        return 'is-cancelled';
      default:
        return 'is-draft';
    }
  }


  protected toggleEstado(evento: EventoAdminListado, event?: Event): void {
    event?.stopPropagation();

    if (this.actionLoadingId() === evento.id) {
      return;
    }

    const estado = (evento.estado ?? '').toLowerCase();

    if (evento.inscripcionAbierta) {
      this.cerrarEvento(evento.id);
      return;
    }

    if (estado === 'borrador') {
      this.publicarEvento(evento.id);
    }
  }

  protected descargarParticipantes(evento: EventoAdminListado, event?: Event): void {
    event?.stopPropagation();

    if (this.downloadingId() === evento.id) {
      return;
    }

    this.downloadingId.set(evento.id);
    this.errorMessage.set(null);

    this.eventosApi
      .descargarReportePdf(evento.id)
      .pipe(finalize(() => this.downloadingId.set(null)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = this.buildFileName(evento.titulo, evento.id);
          link.style.display = 'none';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
        },
        error: () => {
          this.errorMessage.set('No se pudo descargar el listado de participantes.');
        },
      });
  }

  private loadEventos(page = 1): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.eventosApi
      .getEventosAdmin({
        search: this.searchTerm(),
        monthOnly: this.monthOnly(),
        monthKey: this.monthOnly() ? getCurrentMonthKey() : undefined,
        pagination: true,
        page,
        itemsPerPage: AdminEventos.PAGE_SIZE,
      })
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (eventosPage) => this.eventosPage.set(eventosPage),
        error: () => {
          this.eventosPage.set({
            items: [],
            totalItems: 0,
            totalPages: 0,
            page: 1,
            itemsPerPage: AdminEventos.PAGE_SIZE,
            hasNext: false,
            hasPrevious: false,
          });
          this.errorMessage.set('No se pudo cargar el listado de eventos.');
        },
      });
  }

  private publicarEvento(id: string): void {
    this.actionLoadingId.set(id);
    this.errorMessage.set(null);

    this.eventosApi
      .publicarEvento(id)
      .pipe(finalize(() => this.actionLoadingId.set(null)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loadEventos(1),
        error: () => {
          this.errorMessage.set('No se pudo publicar el evento.');
        },
      });
  }

  private cerrarEvento(id: string): void {
    this.actionLoadingId.set(id);
    this.errorMessage.set(null);

    this.eventosApi
      .cerrarEvento(id)
      .pipe(finalize(() => this.actionLoadingId.set(null)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loadEventos(1),
        error: () => {
          this.errorMessage.set('No se pudo cerrar el evento.');
        },
      });
  }

  private buildFileName(titulo: string, id: string): string {
    const normalizedTitle = titulo
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');

    return `participantes-${normalizedTitle || id}.pdf`;
  }
}
