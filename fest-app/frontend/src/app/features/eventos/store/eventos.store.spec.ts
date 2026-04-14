import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { EventosApi, EventoResumenApi } from '../data/eventos.api';
import { EventosMapper } from '../data/eventos.mapper';
import { EventosStore } from './eventos.store';

function toDateKey(date: Date): string {
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${year}-${month}-${day}`;
}

describe('EventosStore carga incremental mensual', () => {
  let store: EventosStore;
  let apiSpy: jasmine.SpyObj<EventosApi>;

  beforeEach(() => {
    apiSpy = jasmine.createSpyObj<EventosApi>('EventosApi', ['getEventosByDateRange']);

    TestBed.configureTestingModule({
      providers: [
        EventosStore,
        EventosMapper,
        { provide: EventosApi, useValue: apiSpy },
        {
          provide: AuthService,
          useValue: { currentUserId: 'user-1' },
        },
      ],
    });

    store = TestBed.inject(EventosStore);
  });

  it('carga ventana inicial y solo pide meses nuevos al navegar', () => {
    apiSpy.getEventosByDateRange.and.callFake((startDate?: string, endDate?: string) => {
      // initial combined request: 2026-03-01 .. 2026-05-31
      if (startDate === '2026-03-01' && endDate === '2026-05-31') {
        return of([
          { id: 'evt-mar', titulo: 'Evento Marzo', fechaEvento: '2026-03-12', estado: 'publicado' },
          { id: 'evt-abr', titulo: 'Evento Abril', fechaEvento: '2026-04-08', estado: 'publicado' },
          { id: 'evt-may', titulo: 'Evento Mayo', fechaEvento: '2026-05-14', estado: 'publicado' },
        ]);
      }

      // adjacent month request: 2026-06-01 .. 2026-06-30
      if (startDate === '2026-06-01' && endDate === '2026-06-30') {
        return of([
          { id: 'evt-jun', titulo: 'Evento Junio', fechaEvento: '2026-06-03', estado: 'publicado' },
        ]);
      }

      return of([]);
    });

    store.loadInitialCalendarWindow(new Date(2026, 3, 15)).subscribe();

    expect(apiSpy.getEventosByDateRange.calls.count()).toBe(1);
    expect(store.events().map((event) => event.id)).toEqual(['evt-mar', 'evt-abr', 'evt-may']);

    store.loadAdjacentMonth(new Date(2026, 4, 1), 1).subscribe();

    expect(apiSpy.getEventosByDateRange.calls.count()).toBe(2);
    expect(store.events().map((event) => event.id)).toEqual(['evt-mar', 'evt-abr', 'evt-may', 'evt-jun']);

    // No repite request cuando el mes ya fue cargado previamente.
    store.loadAdjacentMonth(new Date(2026, 4, 1), 1).subscribe();

    expect(apiSpy.getEventosByDateRange.calls.count()).toBe(2);
  });

  it('carga proximos en una sola llamada para mes actual y siguiente', () => {
    const referenceDate = new Date();
    const plus1Day = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), referenceDate.getDate() + 1);
    const plus15Days = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), referenceDate.getDate() + 15);
    const startOfCurrentMonth = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), 1);
    // now the upcoming window ends one month further (inclusive): month + 3
    const endOfNextMonth = new Date(referenceDate.getFullYear(), referenceDate.getMonth() + 3, 0);

    apiSpy.getEventosByDateRange.and.returnValue(of([
      { id: 'evt-prox-1', titulo: 'Evento Proximo 1', fechaEvento: toDateKey(plus1Day), estado: 'publicado' },
      { id: 'evt-prox-2', titulo: 'Evento Proximo 2', fechaEvento: toDateKey(plus15Days), estado: 'publicado' },
    ]));

    store.loadUpcomingCurrentAndNextMonth(referenceDate).subscribe();

    const call = apiSpy.getEventosByDateRange.calls.mostRecent();
    // start should be today (referenceDate), end is end of next month
    expect(call.args[0]).toBe(toDateKey(referenceDate));
    expect(call.args[1]).toBe(toDateKey(endOfNextMonth));
    expect(apiSpy.getEventosByDateRange.calls.count()).toBe(1);
    expect(store.upcomingEvents().map((event) => event.id)).toEqual(['evt-prox-1', 'evt-prox-2']);
  });
});

