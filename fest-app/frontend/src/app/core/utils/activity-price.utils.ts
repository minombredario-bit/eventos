export interface ActivityPriceMemberLike {
  personType: 'adulto' | 'infantil' | string;
  origin: 'invitado' | string;
}

export interface ActivityPriceOptionLike {
  precioAdultoInterno: number;
  precioAdultoExterno: number;
  precioInfantil: number;
  precioInfantilExterno: number;
}

export function getActivityPrice(
  member: ActivityPriceMemberLike,
  option: ActivityPriceOptionLike,
): number {
  const isAdult = member.personType === 'adulto';
  const isGuest = member.origin === 'invitado';

  if (isAdult) {
    return isGuest
      ? option.precioAdultoExterno
      : option.precioAdultoInterno;
  }

  return isGuest
    ? option.precioInfantilExterno
    : option.precioInfantil;
}
