import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, map, switchMap } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { formatLocalDate } from '../../../../core/utils/date.utils';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventoApuntadoApi, EventosApi } from '../../data/eventos.api';

@Component({
  selector: 'app-apuntados',
  standalone: true,
  imports: [MobileHeader],
  templateUrl: './apuntados.html',
  styleUrl: './apuntados.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Apuntados {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);

  private readonly eventId$ = this.route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    filter((id): id is string => Boolean(id)),
    distinctUntilChanged(),
  );

  protected readonly eventId = toSignal(this.eventId$, { initialValue: '' });
  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly searchTerm = signal('');
  protected readonly eventTitle = signal('Evento');
  protected readonly eventDate = signal<string | null>(null);
  protected readonly apuntados = signal<EventoApuntadoApi[]>([]);
  protected readonly totalApuntados = signal(0);
  protected readonly currentPage = signal(1);
  protected readonly totalPages = signal(0);
  protected readonly itemsPerPage = signal(0);
  protected readonly isSearching = computed(() => this.searchTerm().trim().length > 0);
  protected readonly canGoToPreviousPage = computed(() => this.currentPage() > 1);
  protected readonly canGoToNextPage = computed(() => this.currentPage() < this.totalPages());

  protected readonly summary = computed(() => {
    const total = this.totalApuntados();
    if (total === 0) return 'Todavía no hay participantes apuntados para este evento.';
    if (total === 1) return 'Hay 1 participante apuntado para este evento.';
    return `Hay ${total} participantes apuntados para este evento.`;
  });

  constructor() {
    this.loadData();
  }

  protected onSearch(value: string): void {
    this.searchTerm.set(value);
    this.loadApuntados(value, 1);
  }

  protected goToPreviousPage(): void {
    if (!this.canGoToPreviousPage() || this.loading()) {
      return;
    }

    this.loadApuntados(this.searchTerm(), this.currentPage() - 1);
  }

  protected goToNextPage(): void {
    if (!this.canGoToNextPage() || this.loading()) {
      return;
    }

    this.loadApuntados(this.searchTerm(), this.currentPage() + 1);
  }

  protected goBack(): void {
    const eventId = this.eventId();
    if (!eventId) {
      void this.router.navigateByUrl('/eventos/inscripciones');
      return;
    }

    void this.router.navigate(['/eventos', eventId, 'detalle']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  private loadData(): void {
    this.eventId$
      .pipe(
        switchMap((eventId) => {
          this.loading.set(true);
          this.errorMessage.set(null);

          return this.eventosApi.getApuntadosByEvento(eventId, { paginate: true, page: 1 });
        }),
        takeUntilDestroyed(this.destroyRef),
      )
        .subscribe({
          next: (response) => {
            this.searchTerm.set('');
            this.applyResponse(response);
            this.loading.set(false);
          },
          error: () => {
            this.eventTitle.set('Evento');
            this.eventDate.set(null);
            this.apuntados.set([]);
            this.totalApuntados.set(0);
            this.currentPage.set(1);
            this.totalPages.set(0);
            this.itemsPerPage.set(0);
            this.errorMessage.set('No pudimos cargar el listado de apuntados. Probá de nuevo.');
            this.loading.set(false);
          },
        });
  }

  private loadApuntados(search: string, page: number): void {
    const eventId = this.eventId();
    if (!eventId) {
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    this.eventosApi
      .getApuntadosByEvento(eventId, { search, paginate: true, page })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.applyResponse(response);
          this.loading.set(false);
        },
        error: () => {
          this.apuntados.set([]);
          this.totalApuntados.set(0);
          this.currentPage.set(1);
          this.totalPages.set(0);
          this.itemsPerPage.set(0);
          this.errorMessage.set('No pudimos filtrar el listado de apuntados. Probá de nuevo.');
          this.loading.set(false);
        },
      });
  }

  private applyResponse(response: {
    evento: { titulo: string; fechaEvento: string };
    apuntados: EventoApuntadoApi[];
    totalItems: number;
    currentPage: number;
    itemsPerPage: number;
  }): void {
    const totalItems = Math.max(0, response.totalItems);
    const itemsPerPage = Math.max(1, response.itemsPerPage || 0);
    const totalPages = totalItems === 0 ? 0 : Math.ceil(totalItems / itemsPerPage);
    const currentPage = totalPages === 0 ? 1 : Math.min(Math.max(1, response.currentPage), totalPages);

    this.eventTitle.set(response.evento.titulo);
    this.eventDate.set(response.evento.fechaEvento ? formatLocalDate(response.evento.fechaEvento) : null);
    this.apuntados.set(response.apuntados);
    this.totalApuntados.set(totalItems);
    this.currentPage.set(currentPage);
    this.totalPages.set(totalPages);
    this.itemsPerPage.set(itemsPerPage);
  }
}
