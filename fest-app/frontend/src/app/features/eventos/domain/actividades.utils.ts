import { ParticipanteSeleccion } from './eventos.models';

export function shouldUseLegacyActividadesFallback(
  evento: { actividades?: unknown; menus?: unknown },
): boolean {
  return !Array.isArray(evento.actividades) && !Array.isArray(evento.menus);
}

export function shouldLoadLegacyInscripcionesFallback(
  participantes: ParticipanteSeleccion[],
): boolean {
  return participantes.length === 0 || participantes.every(
    (item) => !item.inscripcionRelacion,
  );
}

export function activityNoun(count: number): string {
  return count === 1 ? 'actividad' : 'actividades';
}

export function buildActivityCountLabel(count: number): string {
  return `${count} ${activityNoun(count)}`;
}
