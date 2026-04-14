import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { EventosApi, AltaInvitadoPayload } from './eventos.api';
import { EventosMapper } from './eventos.mapper';
import { AuthService } from '../../../core/auth/auth';

describe('EventosApi altaInvitadoEnEvento', () => {
  const storageKey = 'asociacion:invitados';
  const payload: AltaInvitadoPayload = {
    nombre: 'Ana',
    apellidos: 'Invitada',
    tipoPersona: 'adulto',
    parentesco: 'Invitado/a',
    observaciones: '',
  };

  let api: EventosApi;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    window.localStorage.removeItem(storageKey);

    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        EventosApi,
        EventosMapper,
        {
          provide: AuthService,
          useValue: {
            currentUserId: 'user-1',
          },
        },
      ],
    });

    api = TestBed.inject(EventosApi);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    window.localStorage.removeItem(storageKey);
  });

  it('propaga el 422 y no crea invitado en fallback local', () => {
    let responseError: unknown;

    api.altaInvitadoEnEvento('evt-1', payload).subscribe({
      next: () => fail('No debería emitir invitado cuando backend responde 422'),
      error: (error) => {
        responseError = error;
      },
    });

    const request = httpMock.expectOne('http://localhost:8080/api/invitados');
    request.flush(
      {
        'hydra:description': 'Ya existe un invitado activo con ese nombre completo en tu núcleo familiar para este evento.',
      },
      { status: 422, statusText: 'Unprocessable Entity' },
    );

    expect((responseError as { status?: number })?.status).toBe(422);
    expect(window.localStorage.getItem(storageKey)).toBeNull();
  });

  it('si falla por red (status 0), usa fallback y guarda el invitado local', () => {
    let invitadoId = '';

    api.altaInvitadoEnEvento('evt-1', payload).subscribe((invitado) => {
      invitadoId = invitado.id;
    });

    const request = httpMock.expectOne('http://localhost:8080/api/invitados');
    request.error(new ProgressEvent('network-error'), { status: 0, statusText: 'Unknown Error' });

    expect(invitadoId.startsWith('nf-')).toBeTrue();
    expect(window.localStorage.getItem(storageKey)).toContain('Ana');
  });
});

describe('EventosApi guardarSeleccionParticipantes', () => {
  let api: EventosApi;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        EventosApi,
        EventosMapper,
        {
          provide: AuthService,
          useValue: {
            currentUserId: 'user-1',
          },
        },
      ],
    });

    api = TestBed.inject(EventosApi);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('envía ID tipado por recurso y mantiene origen para compatibilidad', () => {
    let savedCount = 0;

    api.guardarSeleccionParticipantes('evt-1', [
      { id: 'user-1', origen: 'familiar' },
      { id: 'nf-9', origen: 'invitado' },
    ]).subscribe((saved) => {
      savedCount = saved.length;
    });

    const request = httpMock.expectOne('http://localhost:8080/api/eventos/evt-1/seleccion_participantes');
    expect(request.request.method).toBe('PUT');
    expect(request.request.body.participantes).toEqual([
      jasmine.objectContaining({ id: '/api/usuarios/user-1', origen: 'familiar' }),
      jasmine.objectContaining({ id: '/api/invitados/nf-9', origen: 'invitado' }),
    ]);

    request.flush({
      eventoId: 'evt-1',
      updatedAt: null,
      participantes: [
        { id: 'user-1', origen: 'familiar' },
        { id: 'nf-9', origen: 'invitado' },
      ],
    });

    expect(savedCount).toBe(2);
  });
});
