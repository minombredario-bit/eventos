import {TipoPersona} from '../../features/admin/domain/admin.models';
import {ParticipantOrigin} from '../../features/eventos/domain/eventos.models';

export interface ActivityPriceMemberLike {
  personType: TipoPersona;
  origin: ParticipantOrigin;
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
