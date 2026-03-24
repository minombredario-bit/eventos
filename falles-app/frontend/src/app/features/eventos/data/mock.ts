import { CredentialData, EventSummary, FamilyMember, MenuOption, NavItem } from '../models/ui';

export const DEFAULT_EVENT_ID = 'mascleta-2026';

export const UPCOMING_EVENTS: EventSummary[] = [
  {
    id: DEFAULT_EVENT_ID,
    title: 'Mascletà de Sant Josep',
    date: '2026-03-19',
    time: '14:00',
    location: 'Casal Faller Central',
    status: 'abierto',
    description: 'Comida de germanor con menú adulto e infantil. Confirmación inmediata.',
  },
  {
    id: 'sopar-plant-2026',
    title: 'Sopar de la Plantà',
    date: '2026-03-17',
    time: '21:30',
    location: 'Carpa Principal',
    status: 'ultimas_plazas',
    description: 'Cena previa a la plantà. Últimas plazas disponibles para acompañantes.',
  },
  {
    id: 'paella-germanor-2026',
    title: 'Paella de Germanor',
    date: '2026-03-24',
    time: '13:45',
    location: 'Patio Exterior',
    status: 'cerrado',
    description: 'Evento completo. Solo disponible en lista de espera.',
  },
];

export const FAMILY_MEMBERS: FamilyMember[] = [
  {
    id: 'member-1',
    name: 'Paula Ros',
    role: 'Titular',
    personType: 'adulto',
    avatarInitial: 'P',
    notes: 'Sin alergias declaradas',
  },
  {
    id: 'member-2',
    name: 'Víctor Ros',
    role: 'Familiar',
    personType: 'infantil',
    avatarInitial: 'V',
    notes: 'Menú infantil preferente',
  },
  {
    id: 'member-3',
    name: 'Clara Ros',
    role: 'Invitada',
    personType: 'adulto',
    avatarInitial: 'C',
    notes: 'Vegetariana',
  },
];

export const MENU_OPTIONS: MenuOption[] = [
  {
    id: 'menu-adulto',
    label: 'Menú adulto',
    description: 'Entrantes + principal + postre',
    slot: 'comida',
    compatibility: 'adulto',
    price: 18,
  },
  {
    id: 'menu-infantil',
    label: 'Menú infantil',
    description: 'Pasta + snack + postre',
    slot: 'comida',
    compatibility: 'infantil',
    price: 10,
  },
  {
    id: 'menu-veg',
    label: 'Menú vegetariano',
    description: 'Opción sin carne y sin pescado',
    slot: 'comida',
    compatibility: 'ambos',
    price: 17,
  },
  {
    id: 'menu-almuerzo-bocata',
    label: 'Almuerzo clásico',
    description: 'Bocadillo + bebida',
    slot: 'almuerzo',
    compatibility: 'ambos',
    price: 6,
  },
  {
    id: 'menu-merienda-infantil',
    label: 'Merienda infantil',
    description: 'Sandwich + fruta',
    slot: 'merienda',
    compatibility: 'infantil',
    price: 5,
  },
  {
    id: 'menu-cena-adulto',
    label: 'Cena de gala',
    description: 'Entrante + principal + postre + bebida',
    slot: 'cena',
    compatibility: 'adulto',
    price: 22,
  },
];

export const CREDENTIAL_DATA: CredentialData = {
  eventTitle: 'Mascletà de Sant Josep',
  eventDate: '2026-03-19T14:00:00',
  holderName: 'Paula Ros',
  eventZone: 'Acceso puerta A',
  qrToken: 'MASCLETA-2026-PAULA-ROS-08F9',
  lines: [
    { id: 'line-1', personName: 'Paula Ros', menuName: 'Menú adulto' },
    { id: 'line-2', personName: 'Víctor Ros', menuName: 'Menú infantil' },
    { id: 'line-3', personName: 'Clara Ros', menuName: 'Menú vegetariano' },
  ],
};

export const BOTTOM_NAV_ITEMS: NavItem[] = [
  { key: 'inicio', label: 'Inicio', icon: '🏠', route: '/eventos/inicio' },
  { key: 'detalle', label: 'Detalle', icon: '📅', route: `/eventos/${DEFAULT_EVENT_ID}/detalle` },
  { key: 'menus', label: 'Menús', icon: '🍽️', route: `/eventos/${DEFAULT_EVENT_ID}/menus` },
  { key: 'credencial', label: 'Credencial', icon: '🎟️', route: `/eventos/${DEFAULT_EVENT_ID}/credencial` },
];
