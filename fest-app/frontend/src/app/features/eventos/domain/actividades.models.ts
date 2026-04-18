import { FamilyMember, MealSlot, ParticipantOrigin } from './eventos.models';

export interface ActivityChangePayload {
  memberId: string;
  memberOrigin: ParticipantOrigin;
  actividadId: string | null;
  slot: MealSlot | null;
}

export interface SelectionSummaryRow {
  memberId: string;
  memberOrigin: ParticipantOrigin;
  actividadId: string;
  memberName: string;
  slot: MealSlot;
  actividadLabel: string;
  price: number;
}

export interface SlotSelectionSummary {
  slot: MealSlot;
  total: number;
  selections: number;
}

export interface ParticipantRelationLine {
  lineId: string;
  inscripcionId: string;
  actividadId: string;
  actividadLabel: string;
  slot: MealSlot;
  estadoLinea: string;
  pagada: boolean;
  price: number;
}

export interface ParticipantRelationSummary {
  id: string;
  codigo: string;
  estadoPago: string;
  totalLineas: number;
  totalPagado: number;
}

export interface ParticipantReference {
  id: string;
  origin: ParticipantOrigin;
  name?: string;
  personType: FamilyMember['personType'];
  enrollment?: FamilyMember['enrollment'];
  relationSummary?: ParticipantRelationSummary;
  relationLines?: ParticipantRelationLine[];
}

export interface ExistingInscriptionLineView {
  id: string;
  inscripcionId: string;
  actividadLabel: string;
  slot: MealSlot | null;
  price: number;
  stateLabel: string;
  pagada: boolean;
}

export interface ExistingInscriptionRowView {
  key: string;
  memberName: string;
  memberTypeLabel: string | null;
  lines: ExistingInscriptionLineView[];
}

export interface ExistingInscriptionTotalsView {
  totalLineas: number;
  totalPagado: number;
}
