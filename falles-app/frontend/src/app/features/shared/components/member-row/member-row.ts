import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FamilyMember, MenuOption, ParticipantOrigin, PaymentBadgeStatus } from '../../../eventos/domain/eventos.models';

interface MenuChangePayload {
  memberId: string;
  memberOrigin: ParticipantOrigin;
  menuId: string | null;
  slot: string | null;
}

@Component({
  selector: 'app-member-row',
  standalone: true,
  imports: [CurrencyPipe],
  templateUrl: './member-row.html',
  styleUrl: './member-row.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MemberRow {
  readonly member = input.required<FamilyMember>();
  readonly actionLabel = input('');
  readonly secondaryActionLabel = input('');
  readonly actionsLocked = input(false);
  readonly showSelect = input(false);
  readonly selectorLabel = input('Actividad');
  readonly selectorAriaLabel = input('');
  readonly slot = input<string | null>(null);
  readonly menuOptions = input<MenuOption[]>([]);
  readonly selectedMenuId = input<string | null>(null);
  readonly emptyMessage = input('No hay actividades disponibles para esta persona');
  readonly showEnrollmentInfo = input(false);
  readonly disabled = input(false);
  readonly secondaryDisabled = input(false);

  readonly actionPressed = output<string>();
  readonly secondaryActionPressed = output<string>();
  readonly menuChanged = output<MenuChangePayload>();

  protected onActionClick(): void {
    this.actionPressed.emit(this.member().id);
  }

  protected onMenuChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    this.menuChanged.emit({
      memberId: this.member().id,
      memberOrigin: this.member().origin,
      menuId: target.value || null,
      slot: this.slot(),
    });
  }

  protected onSecondaryActionClick(): void {
    this.secondaryActionPressed.emit(this.member().id);
  }

  protected paymentBadgeLabel(status: PaymentBadgeStatus): string {
    const labels: Record<PaymentBadgeStatus, string> = {
      pagado: 'Pagado',
      parcial: 'Parcial',
      no_requiere: 'No requiere',
      pendiente: 'Pendiente',
    };
    return labels[status] ?? 'Pendiente';
  }
}
