export interface EventSummary {
  id: string;
  title: string;
  date: string;
  time: string;
  location: string;
  status: 'abierto' | 'ultimas_plazas' | 'cerrado';
  description: string;
}

export interface FamilyMember {
  id: string;
  name: string;
  role: string;
  personType: 'adulto' | 'infantil';
  origin: ParticipantOrigin;
  avatarInitial: string;
  notes?: string;
  enrollment?: MemberEnrollment;
}

export type ParticipantOrigin = 'familiar' | 'no_fallero';

export type PaymentBadgeStatus = 'pagado' | 'pendiente' | 'no_requiere' | 'parcial';

export interface MemberEnrollment {
  eventId?: string;
  eventTitle?: string;
  eventLabel: string;
  paymentStatus: PaymentBadgeStatus;
  paymentStatusRaw: string;
}

export type MealSlot = 'almuerzo' | 'comida' | 'merienda' | 'cena';
export type MenuCompatibility = 'adulto' | 'infantil' | 'ambos';

export interface MenuOption {
  id: string;
  label: string;
  description: string;
  slot: MealSlot;
  compatibility: MenuCompatibility;
  isPaid?: boolean;
  price: number;
  disabled?: boolean;
}

export interface NavItem {
  key: string;
  label: string;
  icon: string;
  route: string;
}

export interface CredentialLine {
  id: string;
  personName: string;
  menuName: string;
}

export interface CredentialData {
  eventTitle: string;
  eventDate: string;
  holderName: string;
  eventZone: string;
  qrToken: string;
  lines: CredentialLine[];
}
