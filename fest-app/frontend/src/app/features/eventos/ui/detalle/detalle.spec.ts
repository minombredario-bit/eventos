import { convertToParamMap, ActivatedRoute, Router } from '@angular/router';
import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { of, throwError } from 'rxjs';
import { ParticipanteSeleccion } from '../../domain/eventos.models';
import { Detalle, toSelectedMemberKeys, uniqueParticipantsByKey } from './detalle';
import { FamilyMember } from '../../domain/eventos.models';
import { AuthService } from '../../../../core/auth/auth';
import { EventosApi } from '../../data/eventos.api';
import { EventosMapper } from '../../data/eventos.mapper';
import { EventosStore } from '../../store/eventos.store';

describe('toSelectedMemberKeys', () => {
  it('mapea participantes a keys con origen y evita duplicados', () => {
    const participantes = [
      { id: '10', origen: 'familiar' },
      { id: '22', origen: 'invitado' },
      { id: '10', origen: 'familiar' },
    ] as ParticipanteSeleccion[];

    expect(toSelectedMemberKeys(participantes)).toEqual(['familiar:10', 'invitado:22']);
  });

  it('descarta ids vacíos y normaliza origen desconocido a familiar', () => {
    const participantes = [
      { id: '   ', origen: 'familiar' },
      { id: 'abc', origen: 'otro_origen' as ParticipanteSeleccion['origen'] },
      { id: 'n1', origen: 'invitado' },
    ] as ParticipanteSeleccion[];

    expect(toSelectedMemberKeys(participantes)).toEqual(['familiar:abc', 'invitado:n1']);
  });

  it('normaliza IDs en formato IRI para mantener selección estable', () => {
    const participantes = [
      { id: '/api/usuarios/uuid-123', origen: 'familiar' },
      { id: '/api/invitados/nf-9', origen: 'invitado' },
    ] as ParticipanteSeleccion[];

    expect(toSelectedMemberKeys(participantes)).toEqual(['familiar:uuid-123', 'invitado:nf-9']);
  });
});

describe('uniqueParticipantsByKey', () => {
  it('deduplica familiar repetido entre fuentes y preserva el primero', () => {
    const participants: FamilyMember[] = [
      {
        id: 'u-1',
        name: 'Titular Uno',
        role: 'Titular',
        personType: 'adulto',
        origin: 'familiar',
        avatarInitial: 'T',
      },
      {
        id: '/api/usuarios/u-2',
        name: 'María García',
        role: 'Cónyuge',
        personType: 'adulto',
        origin: 'familiar',
        avatarInitial: 'M',
      },
      {
        id: 'u-2',
        name: 'Usuario u-2',
        role: 'conyuge',
        personType: 'adulto',
        origin: 'familiar',
        avatarInitial: 'U',
      },
    ];

    expect(uniqueParticipantsByKey(participants)).toEqual([
      participants[0],
      {
        ...participants[1],
        id: 'u-2',
        origin: 'familiar',
      },
    ]);
  });
});

describe('Detalle autosave y navegación', () => {
  it('add/remove dispara guardado automático de selección', () => {
    const guardarSeleccionParticipantes = jasmine
      .createSpy('guardarSeleccionParticipantes')
      .and.callFake((_: string, participantes: ParticipanteSeleccion[]) => of(participantes));

    const fixture = createDetalleFixture({ guardarSeleccionParticipantes });
    const component = fixture.componentInstance as unknown as {
      members: { set: (value: FamilyMember[]) => void };
      toggleMemberSelection: (member: FamilyMember) => void;
    };

    const member: FamilyMember = {
      id: '10',
      name: 'Familiar Test',
      role: 'Hijo/a',
      personType: 'adulto',
      origin: 'familiar',
      avatarInitial: 'F',
    };

    component.members.set([member]);
    component.toggleMemberSelection(member);
    component.toggleMemberSelection(member);

    expect(guardarSeleccionParticipantes).toHaveBeenCalledTimes(2);
    expect(guardarSeleccionParticipantes.calls.argsFor(0)).toEqual([
      'evt-1',
      [{ id: '10', origen: 'familiar' }],
    ]);
    expect(guardarSeleccionParticipantes.calls.argsFor(1)).toEqual(['evt-1', []]);
  });

  it('Solicitar actividades solo navega y no guarda', () => {
    const guardarSeleccionParticipantes = jasmine
      .createSpy('guardarSeleccionParticipantes')
      .and.returnValue(of([]));
    const navigate = jasmine.createSpy('navigate').and.returnValue(Promise.resolve(true));

    const fixture = createDetalleFixture({ guardarSeleccionParticipantes, navigate });
    const component = fixture.componentInstance as unknown as {
      selectedMemberIds: { set: (value: string[]) => void };
      openActivities: () => void;
    };

    component.selectedMemberIds.set(['familiar:10']);
    component.openActivities();

    expect(guardarSeleccionParticipantes).not.toHaveBeenCalled();
    expect(navigate).toHaveBeenCalledWith(['/eventos', 'evt-1', 'actividades']);
  });

  it('tras alta de invitado lo selecciona para inscripción y no queda en gestión de invitados', () => {
    const guardarSeleccionParticipantes = jasmine
      .createSpy('guardarSeleccionParticipantes')
      .and.callFake((_: string, participantes: ParticipanteSeleccion[]) => of(participantes));
    const altaInvitadoEnEvento = jasmine
      .createSpy('altaInvitadoEnEvento')
      .and.returnValue(of({
        id: 'nf-77',
        nombre: 'Ana',
        apellidos: 'Invitada',
        nombreCompleto: 'Ana Invitada',
        parentesco: 'Invitado/a',
        tipoPersona: 'adulto',
        origen: 'invitado',
        esInvitado: true,
      }));

    const fixture = createDetalleFixture({ guardarSeleccionParticipantes, altaInvitadoEnEvento });
    const component = fixture.componentInstance as unknown as {
      invitadoForm: {
        setValue: (value: {
          nombre: string;
          apellidos: string;
          tipoPersona: 'adulto' | 'infantil';
          parentesco: string;
          observaciones: string;
        }) => void;
      };
      submitInvitado: () => void;
      participants: () => FamilyMember[];
      selectedMemberIds: () => string[];
    };

    component.invitadoForm.setValue({
      nombre: 'Ana',
      apellidos: 'Invitada',
      tipoPersona: 'adulto',
      parentesco: 'Invitado/a',
      observaciones: '',
    });

    component.submitInvitado();

    expect(guardarSeleccionParticipantes).toHaveBeenCalledWith('evt-1', [
      { id: 'nf-77', origen: 'invitado' },
    ]);
    expect(component.selectedMemberIds()).toEqual(['invitado:nf-77']);
    expect(component.participants().some((member) => member.id === 'nf-77' && member.origin === 'invitado')).toBeTrue();
  });

  it('si backend responde 422 no inserta invitado fantasma y muestra el error de validación', () => {
    const guardarSeleccionParticipantes = jasmine
      .createSpy('guardarSeleccionParticipantes')
      .and.callFake((_: string, participantes: ParticipanteSeleccion[]) => of(participantes));
    const altaInvitadoEnEvento = jasmine
      .createSpy('altaInvitadoEnEvento')
      .and.returnValue(throwError(() => new HttpErrorResponse({
        status: 422,
        error: {
          'hydra:description': 'Ya existe un invitado activo con ese nombre completo en tu núcleo familiar para este evento.',
        },
      })));

    const fixture = createDetalleFixture({ guardarSeleccionParticipantes, altaInvitadoEnEvento });
    const component = fixture.componentInstance as unknown as {
      invitadoForm: {
        setValue: (value: {
          nombre: string;
          apellidos: string;
          tipoPersona: 'adulto' | 'infantil';
          parentesco: string;
          observaciones: string;
        }) => void;
      };
      submitInvitado: () => void;
      participants: () => FamilyMember[];
      selectedMemberIds: () => string[];
      invitadosError: () => string | null;
    };

    component.invitadoForm.setValue({
      nombre: 'Ana',
      apellidos: 'Invitada',
      tipoPersona: 'adulto',
      parentesco: 'Invitado/a',
      observaciones: '',
    });

    component.submitInvitado();

    expect(component.participants().some((member) => member.id === 'nf-77' && member.origin === 'invitado')).toBeFalse();
    expect(component.selectedMemberIds()).toEqual([]);
    expect(guardarSeleccionParticipantes).not.toHaveBeenCalled();
    expect(component.invitadosError()).toBe('Ya existe un invitado activo con ese nombre completo en tu núcleo familiar para este evento.');
  });

  it('incluye invitados y familiares en la misma lista principal de participantes', () => {
    const fixture = createDetalleFixture();
    const component = fixture.componentInstance as unknown as {
      members: { set: (value: FamilyMember[]) => void };
      invitados: { set: (value: FamilyMember[]) => void };
      participants: () => FamilyMember[];
    };

    component.members.set([{
      id: 'fam-1',
      name: 'Familiar Uno',
      role: 'Hijo/a',
      personType: 'adulto',
      origin: 'familiar',
      avatarInitial: 'F',
    }]);

    component.invitados.set([{
      id: 'nf-1',
      name: 'Invitado Uno',
      role: 'Invitado',
      personType: 'adulto',
      origin: 'invitado',
      avatarInitial: 'I',
    }]);

    const participants = component.participants();
    expect(participants.some((member) => member.id === 'fam-1' && member.origin === 'familiar')).toBeTrue();
    expect(participants.some((member) => member.id === 'nf-1' && member.origin === 'invitado')).toBeTrue();
  });

  it('usa label Invitado en inscritos invitados', () => {
    const fixture = createDetalleFixture();
    const component = fixture.componentInstance as unknown as {
      inscritos: { set: (value: ParticipanteSeleccion[]) => void };
      inscritosRows: () => FamilyMember[];
    };

    component.inscritos.set([{ id: 'nf-1', origen: 'invitado' } as ParticipanteSeleccion]);

    const rows = component.inscritosRows();
    expect(rows[0]?.role).toBe('Invitado');
    expect(rows[0]?.name).toBe('Invitado nf-1');
  });

  it('expone botón Borrar en la lista principal solo para invitados', () => {
    const fixture = createDetalleFixture();
    const component = fixture.componentInstance as unknown as {
      participantSecondaryActionLabel: (member: FamilyMember) => string;
    };

    expect(component.participantSecondaryActionLabel({
      id: 'nf-1',
      name: 'Invitado',
      role: 'Invitado',
      personType: 'adulto',
      origin: 'invitado',
      avatarInitial: 'I',
    })).toBe('Borrar');

    expect(component.participantSecondaryActionLabel({
      id: 'fam-1',
      name: 'Familiar',
      role: 'Hijo/a',
      personType: 'adulto',
      origin: 'familiar',
      avatarInitial: 'F',
    })).toBe('');
  });

  it('no muestra acciones en el card "A quién he apuntado" para invitados', () => {
    const fixture = createDetalleFixture();
    const component = fixture.componentInstance as unknown as {
      inscritos: { set: (value: ParticipanteSeleccion[]) => void };
    };

    component.inscritos.set([{ id: 'nf-1', origen: 'invitado' } as ParticipanteSeleccion]);
    fixture.detectChanges();

    const inscritosCard = fixture.nativeElement.querySelector('article.panel.family-list');
    expect(inscritosCard?.querySelector('.row-actions')).toBeNull();
  });

  it('borra invitado desde la lista principal y actualiza selección persistida', () => {
    const guardarSeleccionParticipantes = jasmine
      .createSpy('guardarSeleccionParticipantes')
      .and.callFake((_: string, participantes: ParticipanteSeleccion[]) => of(participantes));
    const bajaInvitadoEnEvento = jasmine
      .createSpy('bajaInvitadoEnEvento')
      .and.returnValue(of(void 0));

    const fixture = createDetalleFixture({ guardarSeleccionParticipantes, bajaInvitadoEnEvento });
    const component = fixture.componentInstance as unknown as {
      selectedMemberIds: { set: (value: string[]) => void; (): string[] };
      removeInvitado: (memberId: string) => void;
    };

    component.selectedMemberIds.set(['invitado:nf-88', 'familiar:10']);
    component.removeInvitado('nf-88');

    expect(bajaInvitadoEnEvento).toHaveBeenCalledWith('evt-1', 'nf-88');
    expect(component.selectedMemberIds()).toEqual(['familiar:10']);
    expect(guardarSeleccionParticipantes).toHaveBeenCalledWith('evt-1', [{ id: '10', origen: 'familiar' }]);
  });

  it('muestra el título actualizado de la lista principal', () => {
    const fixture = createDetalleFixture();
    const headings = Array.from(fixture.nativeElement.querySelectorAll('h2')) as HTMLHeadingElement[];
    const headingTexts = headings.map((heading) => heading.textContent?.trim() ?? '');

    expect(headingTexts).toContain('Inscripciones, familiares y amigos');
  });
});

function createDetalleFixture(overrides?: {
  guardarSeleccionParticipantes?: jasmine.Spy;
  navigate?: jasmine.Spy;
  altaInvitadoEnEvento?: jasmine.Spy;
  bajaInvitadoEnEvento?: jasmine.Spy;
}) {
  const getEvento = jasmine.createSpy('getEvento').and.returnValue(of({
    id: 'evt-1',
    titulo: 'Evento de prueba',
    fechaEvento: '2026-04-01',
    horaInicio: '14:00',
    lugar: 'Casal',
    descripcion: 'Demo',
    inscripcionAbierta: true,
    actividades: [],
  }));

  const eventosApiMock = {
    getEvento,
    guardarSeleccionParticipantes: overrides?.guardarSeleccionParticipantes
      ?? jasmine.createSpy('guardarSeleccionParticipantes').and.returnValue(of([])),
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
    altaInvitadoEnEvento: overrides?.altaInvitadoEnEvento
      ?? jasmine.createSpy('altaInvitadoEnEvento').and.returnValue(of(void 0)),
    bajaInvitadoEnEvento: overrides?.bajaInvitadoEnEvento
      ?? jasmine.createSpy('bajaInvitadoEnEvento').and.returnValue(of(void 0)),
  };

  const authServiceMock = {
    userSignal: () => null,
    currentUserId: 'user-1',
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
          navigate: overrides?.navigate ?? jasmine.createSpy('navigate').and.returnValue(Promise.resolve(true)),
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
