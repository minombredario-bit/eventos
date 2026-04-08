import { shouldLoadLegacyInscripcionesFallback, shouldUseLegacyMenusFallback } from './menus';
import { ParticipanteSeleccionApi } from '../../data/eventos.api';

describe('shouldUseLegacyMenusFallback', () => {
  it('devuelve true cuando el contrato nuevo no trae menus', () => {
    expect(shouldUseLegacyMenusFallback({})).toBeTrue();
  });

  it('devuelve false cuando menus viene embebido aunque esté vacío', () => {
    expect(shouldUseLegacyMenusFallback({ menus: [] })).toBeFalse();
  });

  it('devuelve false cuando menus viene embebido con elementos', () => {
    expect(shouldUseLegacyMenusFallback({ menus: [{ id: 'm-1' }] })).toBeFalse();
  });
});

describe('shouldLoadLegacyInscripcionesFallback', () => {
  it('activa fallback cuando no hay participantes en seleccion_participantes', () => {
    expect(shouldLoadLegacyInscripcionesFallback([])).toBeTrue();
  });

  it('evita fallback cuando hay participantes en seleccion_participantes', () => {
    expect(shouldLoadLegacyInscripcionesFallback([
      { id: 'u-1', origen: 'familiar' } as ParticipanteSeleccionApi,
    ])).toBeFalse();
  });
});
