import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, forkJoin, map, switchMap } from 'rxjs';
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
  protected readonly isSearching = computed(() => this.searchTerm().trim().length > 0);

  protected readonly summary = computed(() => {
    const total = this.apuntados().length;
    if (total === 0) return 'Todavía no hay participantes apuntados para este evento.';
    if (total === 1) return 'Hay 1 participante apuntado para este evento.';
    return `Hay ${total} participantes apuntados para este evento.`;
  });

  constructor() {
    this.loadData();
  }

  protected onSearch(value: string): void {
    this.searchTerm.set(value);
    this.loadApuntados(value);
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

          return forkJoin({
            event: this.eventosApi.getEvento(eventId),
            apuntados: this.eventosApi.getApuntadosByEvento(eventId),
          });
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: ({ event, apuntados }) => {
          this.eventTitle.set(event.titulo);
          this.eventDate.set(formatLocalDate(event.fechaEvento));
          this.apuntados.set(apuntados);
          this.loading.set(false);
        },
        error: () => {
          this.eventTitle.set('Evento');
          this.eventDate.set(null);
          this.apuntados.set([]);
          this.errorMessage.set('No pudimos cargar el listado de apuntados. Probá de nuevo.');
          this.loading.set(false);
        },
      });
  }

  private loadApuntados(search: string): void {
    const eventId = this.eventId();
    if (!eventId) {
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    this.eventosApi
      .getApuntadosByEvento(eventId, search)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (apuntados) => {
          this.apuntados.set(apuntados);
          this.loading.set(false);
        },
        error: () => {
          this.apuntados.set([]);
          this.errorMessage.set('No pudimos filtrar el listado de apuntados. Probá de nuevo.');
          this.loading.set(false);
        },
      });
  }
}
