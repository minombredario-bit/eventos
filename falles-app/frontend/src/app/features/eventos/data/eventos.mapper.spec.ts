import { EventosMapper } from './eventos.mapper';
import { InvitadoApi } from './eventos.api';

describe('EventosMapper', () => {
  it('fuerza el label Invitado para origen invitado', () => {
    const mapper = new EventosMapper();

    const invitado = {
      id: 'nf-1',
      nombre: 'Ana',
      apellidos: 'Pérez',
      parentesco: 'Participante',
      tipoPersona: 'adulto',
      origen: 'invitado',
    } as InvitadoApi;

    const member = mapper.toFamilyMember(invitado);

    expect(member.origin).toBe('invitado');
    expect(member.role).toBe('Invitado');
  });

  it('normaliza payload de invitado sin parentesco con defaults de Invitado', () => {
    const mapper = new EventosMapper();

    const invitado = mapper.mapInvitadoCreate({}, 'evt-1');
    const member = mapper.toFamilyMember(invitado);

    expect(invitado.parentesco).toBe('Invitado');
    expect(invitado.nombre).toBe('Invitado');
    expect(member.role).toBe('Invitado');
    expect(member.name).toBe('Invitado');
  });
});
