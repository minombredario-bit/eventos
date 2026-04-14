import { ActivatedRoute, convertToParamMap, Router } from '@angular/router';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { Detalle } from './detalle';
import { AuthService } from '../../../../core/auth/auth';
import { EventosApi, ParticipanteSeleccionApi } from '../../data/eventos.api';
import { EventosMapper } from '../../data/eventos.mapper';
import { EventosStore } from '../../store/eventos.store';
import { FamilyMember } from '../../domain/eventos.models';

describe('Detalle locking rules', () => {
  it('no permite eliminar inscripción desde detalle para participante ya inscrito', () => {
    const fixture = createDetalleFixture();
    const component = fixture.componentInstance as unknown as {
      participantActionLabel: (member: FamilyMember) => string;
      isParticipantActionDisabled: (member: FamilyMember) => boolean;
    };

    const enrolledMember: FamilyMember = {
      id: 'u-1',
      name: 'Usuario Inscrito',
      role: 'Familiar',
      personType: 'adulto',
      origin: 'familiar',
      avatarInitial: 'U',
      enrollment: {
        eventId: 'evt-1',
        eventTitle: 'Evento de prueba',
        eventLabel: 'Evento de prueba',
        paymentStatus: 'pendiente',
        paymentStatusRaw: 'pendiente',
      },
    };

    expect(component.participantActionLabel(enrolledMember)).toBe('Eliminar');
    expect(component.isParticipantActionDisabled(enrolledMember)).toBeFalse();
  });

  it('bloquea acciones cuando la línea del participante ya está pagada', () => {
    const fixture = createDetalleFixture();
    const component = fixture.componentInstance as unknown as {
      inscritos: { set: (value: ParticipanteSeleccionApi[]) => void };
      participantActionLabel: (member: FamilyMember) => string;
      isParticipantActionDisabled: (member: FamilyMember) => boolean;
    };

    component.inscritos.set([
      {
        id: 'u-1',
        origen: 'familiar',
        inscripcionRelacion: {
          id: 'ins-1',
          codigo: 'ENT-TEST',
          estadoPago: 'pendiente',
          lineas: [
            {
              id: 'line-1',
              actividadId: 'actividad-1',
              usuarioId: 'u-1',
              invitadoId: undefined,
              nombreActividadSnapshot: 'Actividad general',
              franjaComidaSnapshot: 'comida',
              estadoLinea: 'confirmada',
              precioUnitario: 12,
              pagada: true,
            },
          ],
        },
      } as ParticipanteSeleccionApi,
    ]);

    const member: FamilyMember = {
      id: 'u-1',
      name: 'Usuario con línea pagada',
      role: 'Familiar',
      personType: 'adulto',
      origin: 'familiar',
      avatarInitial: 'U',
    };

    expect(component.participantActionLabel(member)).toBe('');
    expect(component.isParticipantActionDisabled(member)).toBeTrue();
  });

  it('calcula estado de pago por líneas y muestra parcial con mezcla pagada/no pagada', () => {
    const fixture = createDetalleFixture();
    const component = fixture.componentInstance as unknown as {
      inscritos: { set: (value: ParticipanteSeleccionApi[]) => void };
      inscritosRows: () => Array<FamilyMember & { enrollment?: { paymentStatusRaw?: string } }>;
    };

    component.inscritos.set([
      {
        id: 'u-1',
        origen: 'familiar',
        nombre: 'Usuario',
        apellidos: 'Test',
        inscripcionRelacion: {
          id: 'ins-1',
          codigo: 'ENT-TEST',
          estadoPago: 'pagado',
          lineas: [
            {
              id: 'line-1',
              actividadId: 'actividad-1',
              usuarioId: 'u-1',
              nombreActividadSnapshot: 'Actividad general',
              franjaComidaSnapshot: 'comida',
              estadoLinea: 'confirmada',
              precioUnitario: 12,
              pagada: true,
            },
            {
              id: 'line-2',
              actividadId: 'actividad-2',
              usuarioId: 'u-1',
              nombreActividadSnapshot: 'Actividad noche',
              franjaComidaSnapshot: 'cena',
              estadoLinea: 'confirmada',
              precioUnitario: 10,
              pagada: false,
            },
          ],
        },
      } as ParticipanteSeleccionApi,
    ]);

    const row = component.inscritosRows()[0];
    expect(row.enrollment?.paymentStatusRaw).toBe('parcial');
  });
});

function createDetalleFixture() {
  const eventosApiMock = {
    getEvento: jasmine.createSpy('getEvento').and.returnValue(of({
      id: 'evt-1',
      titulo: 'Evento de prueba',
      fechaEvento: '2026-04-01',
      horaInicio: '14:00',
      lugar: 'Casal',
      descripcion: 'Demo',
      inscripcionAbierta: true,
      actividades: [{ id: 'a-1', activo: true }],
      permiteInvitados: true,
    })),
    getInscripcionesMiasCollection: jasmine.createSpy('getInscripcionesMiasCollection').and.returnValue(of([])),
    guardarSeleccionParticipantes: jasmine.createSpy('guardarSeleccionParticipantes').and.returnValue(of([])),
    cancelarLineaInscripcion: jasmine.createSpy('cancelarLineaInscripcion').and.returnValue(of({})),
  };

  const eventosMapperMock = {
    toPaymentBadgeStatus: jasmine.createSpy('toPaymentBadgeStatus').and.returnValue('pendiente'),
    toFamilyMember: jasmine.createSpy('toFamilyMember').and.callFake((item: { id: string; nombreCompleto?: string }) => ({
      id: item.id,
      name: item.nombreCompleto ?? 'Invitado',
      role: 'Invitado/a',
      personType: 'adulto',
      origin: 'invitado',
      avatarInitial: 'I',
    })),
  };

  const eventosStoreMock = {
    loadPersonasMias: jasmine.createSpy('loadPersonasMias').and.returnValue(of([])),
    getInvitadosByEvento: jasmine.createSpy('getInvitadosByEvento').and.returnValue(of([])),
    getSeleccionParticipantes: jasmine.createSpy('getSeleccionParticipantes').and.returnValue(of([])),
    altaInvitadoEnEvento: jasmine.createSpy('altaInvitadoEnEvento').and.returnValue(of(void 0)),
    bajaInvitadoEnEvento: jasmine.createSpy('bajaInvitadoEnEvento').and.returnValue(of(void 0)),
  };

  const authServiceMock = {
    userSignal: () => ({ id: 'u-1', nombre: 'Titular', apellidos: 'Test', email: 'titular@example.com' }),
    currentUserId: 'u-1',
    logout: jasmine.createSpy('logout'),
  };

  TestBed.configureTestingModule({
    imports: [Detalle],
    providers: [
      {
        provide: ActivatedRoute,
        useValue: {
          paramMap: of(convertToParamMap({ id: 'evt-1' })),
        },
      },
      {
        provide: Router,
        useValue: {
          navigate: jasmine.createSpy('navigate').and.returnValue(Promise.resolve(true)),
          navigateByUrl: jasmine.createSpy('navigateByUrl').and.returnValue(Promise.resolve(true)),
        },
      },
      { provide: AuthService, useValue: authServiceMock },
      { provide: EventosApi, useValue: eventosApiMock },
      { provide: EventosMapper, useValue: eventosMapperMock },
      { provide: EventosStore, useValue: eventosStoreMock },
    ],
  });

  const fixture = TestBed.createComponent(Detalle);
  fixture.detectChanges();

  return fixture;
}

