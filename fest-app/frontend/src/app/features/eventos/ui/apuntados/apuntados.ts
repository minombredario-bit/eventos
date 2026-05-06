import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import {distinctUntilChanged, filter, finalize, map, switchMap} from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { formatLocalDate } from '../../../../core/utils/date.utils';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventosApi } from '../../data/eventos.api';
import {ApuntadosPage, EventoApuntado} from '../../domain/eventos.models';
import { Usuario} from '../../../admin/domain/admin.models';

@Component({
  selector: 'app-apuntados',
  standalone: true,
  imports: [MobileHeader],
  templateUrl: './apuntados.html',
  styleUrl: './apuntados.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Apuntados {
  private static readonly PAGE_SIZE = 10;
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
  protected readonly transitioning = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly searchTerm = signal('');

  protected readonly apuntadosPage = signal<ApuntadosPage>({
    evento: {
      id: '',
      titulo: '',
      fechaEvento: '',
      horaInicio: null,
    },
    items: [],
    totalPages: 0,
    totalItems: 0,
    page: 1,
    itemsPerPage: Apuntados.PAGE_SIZE,
    hasNext: false,
    hasPrevious: false,
  });

  protected readonly apuntados = computed<Usuario[]>(() => this.apuntadosPage().items);
  protected readonly totalItems = computed<number>(() => this.apuntadosPage().totalItems);
  protected readonly currentPage = computed<number>(() => this.apuntadosPage().page);
  protected readonly totalPages = computed<number>(() => this.apuntadosPage().totalPages);
  protected readonly hasNextPage = computed<boolean>(() => this.apuntadosPage().hasNext);
  protected readonly hasPreviousPage = computed<boolean>(() => this.apuntadosPage().hasPrevious);
  protected readonly eventTitle = computed<string>(() => this.apuntadosPage().evento.titulo);
  protected readonly eventDate = computed<string>(() => this.apuntadosPage().evento.fechaEvento);

  constructor() {
    this.loadApuntados(1, true);
  }

  protected setSearchTerm(value: string): void {
    this.searchTerm.set(value);
    this.loadApuntados( 1);
  }

  protected loadNextPage(): void {
    if (!this.hasNextPage()) {
      return;
    }

    this.loadApuntados(this.currentPage() + 1);
  }

  protected loadPreviousPage(): void {
    if (!this.hasPreviousPage()) {
      return;
    }

    this.loadApuntados(this.currentPage() - 1);
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

  private loadApuntados(page = 1, isInitial = false): void {
    if (isInitial) {
      this.loading.set(true);
    } else {
      this.transitioning.set(true);  // ← suave, no borra la tabla
    }

    this.errorMessage.set(null);

    const eventId = this.eventId();
    if (!eventId) {
      return;
    }

    this.eventosApi
      .getApuntadosByEvento(eventId, {
        search: this.searchTerm(),
        page,
        itemsPerPage: Apuntados.PAGE_SIZE,
      })
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (apuntadosPage) => this.apuntadosPage.set(apuntadosPage),
        error: (error: { error?: { error?: string } }) => {
          this.apuntadosPage.set({
            evento: {
              id: '',
              titulo: '',
              fechaEvento: '',
              horaInicio: null,
            },
            items: [],
            totalItems: 0,
            totalPages: 0,
            page: 1,
            itemsPerPage: Apuntados.PAGE_SIZE,
            hasNext: false,
            hasPrevious: false,
          });
          this.errorMessage.set(error?.error?.error ?? 'No pudimos filtrar el listado de apuntados. Probá de nuevo.');
        },
      });
  }
}
