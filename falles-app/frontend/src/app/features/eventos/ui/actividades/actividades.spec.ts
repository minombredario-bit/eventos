import {
  activityNoun,
  buildActivityCountLabel,
  shouldLoadLegacyInscripcionesFallback,
  shouldUseLegacyMenusFallback,
} from './actividades';
import { ParticipanteSeleccionApi } from '../../data/eventos.api';

describe('activityNoun', () => {
  it('usa singular cuando el total es 1', () => {
    expect(activityNoun(1)).toBe('actividad');
  });

  it('usa plural cuando el total es distinto de 1', () => {
    expect(activityNoun(0)).toBe('actividades');
    expect(activityNoun(2)).toBe('actividades');
  });
});

describe('buildActivityCountLabel', () => {
  it('construye etiqueta singular/plural correctamente', () => {
    expect(buildActivityCountLabel(1)).toBe('1 actividad');
    expect(buildActivityCountLabel(3)).toBe('3 actividades');
  });
});

describe('shouldUseLegacyMenusFallback', () => {
  it('devuelve true cuando el contrato no trae ni menus ni actividades embebidas', () => {
    expect(shouldUseLegacyMenusFallback({})).toBeTrue();
  });

  it('devuelve false cuando el contrato trae actividades embebidas', () => {
    expect(shouldUseLegacyMenusFallback({ actividades: [{ id: 'a-1' }] })).toBeFalse();
  });

  it('devuelve false cuando el campo legacy menus viene embebido aunque esté vacío', () => {
    expect(shouldUseLegacyMenusFallback({ menus: [] })).toBeFalse();
  });

  it('devuelve false cuando el campo legacy menus viene embebido con elementos', () => {
    expect(shouldUseLegacyMenusFallback({ menus: [{ id: 'm-1' }] })).toBeFalse();
  });
});

describe('shouldLoadLegacyInscripcionesFallback', () => {
  it('activa fallback cuando no hay participantes en seleccion_participantes', () => {
    expect(shouldLoadLegacyInscripcionesFallback([])).toBeTrue();
  });

  it('activa fallback cuando los participantes no traen inscripcionRelacion', () => {
    expect(shouldLoadLegacyInscripcionesFallback([
      { id: 'u-1', origen: 'familiar' } as ParticipanteSeleccionApi,
    ])).toBeTrue();
  });

  it('evita fallback cuando hay participantes con inscripcionRelacion', () => {
    expect(shouldLoadLegacyInscripcionesFallback([
      {
        id: 'u-1',
        origen: 'familiar',
        inscripcionRelacion: {
          id: 'ins-1',
          codigo: 'COD-1',
          estadoPago: 'pendiente',
          lineas: [],
        },
      } as ParticipanteSeleccionApi,
    ])).toBeFalse();
  });
});
