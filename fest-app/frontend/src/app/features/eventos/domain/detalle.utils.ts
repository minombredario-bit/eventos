import {FamilyMember, ParticipanteSeleccion} from './eventos.models';
import {SelectionSaveRequest} from './detalle.models';

export function toSelectedMemberKeys(participantes: ParticipanteSeleccion[]): string[] {
  if (!participantes.length) return [];

  const selected: string[] = [];
  const seen = new Set<string>();

  for (const participante of participantes) {
    const id = normalizeParticipantId(participante.id);
    if (!id) continue;

    if (hasOnlyCancelledParticipantLines(participante)) {
      continue;
    }

    const origin = participante.origen === 'invitado' ? 'invitado' : 'familiar';
    const key = `${origin}:${id}`;

    if (seen.has(key)) continue;
    seen.add(key);
    selected.push(key);
  }

  return selected;
}

function hasOnlyCancelledParticipantLines(participante: ParticipanteSeleccion): boolean {
  const relation = participante.inscripcionRelacion;
  if (!relation || !Array.isArray(relation.lineas) || relation.lineas.length === 0) {
    return false;
  }

  const participantId = normalizeParticipantId(participante.id);

  const hasActiveOwnLine = relation.lineas.some((linea) => {
    if (linea.estadoLinea === 'cancelada') {
      return false;
    }

    if (!linea.usuarioId && !linea.invitadoId) {
      return true;
    }

    if (participante.origen === 'invitado') {
      return normalizeParticipantId(linea.invitadoId) === participantId;
    }

    return normalizeParticipantId(linea.usuarioId) === participantId;
  });

  return !hasActiveOwnLine;
}

export function normalizeParticipantId(rawId: string | null | undefined): string {
  if (!rawId) {
    return '';
  }

  const cleaned = rawId.trim();
  if (!cleaned) {
    return '';
  }

  if (!cleaned.includes('/')) {
    return cleaned;
  }

  return cleaned.split('/').filter(Boolean).at(-1) ?? '';
}

export function sameSelectionRequest(previous: SelectionSaveRequest, current: SelectionSaveRequest): boolean {
  if (previous.eventId !== current.eventId) {
    return false;
  }

  if (previous.selectedKeys.length !== current.selectedKeys.length) {
    return false;
  }

  return previous.selectedKeys.every((key, index) => key === current.selectedKeys[index]);
}

export function uniqueParticipantsByKey(participants: FamilyMember[]): FamilyMember[] {
  if (participants.length <= 1) {
    return participants;
  }

  const unique = new Map<string, FamilyMember>();

  for (const participant of participants) {
    const id = normalizeParticipantId(participant.id);
    if (!id) {
      continue;
    }

    const origin = participant.origin === 'invitado' ? 'invitado' : 'familiar';
    const key = `${origin}:${id}`;

    if (!unique.has(key)) {
      unique.set(key, {
        ...participant,
        id,
        origin,
      });
    }
  }

  return Array.from(unique.values());
}
