import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FamilyMember, MenuOption, ParticipantOrigin, PaymentBadgeStatus } from '../../../eventos/domain/eventos.models';

interface ActivityChangePayload {
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
  readonly activityOptions = input<MenuOption[]>([]);
  readonly menuOptions = input<MenuOption[]>([]);
  readonly selectedMenuId = input<string | null>(null);
  readonly emptyMessage = input('No hay actividades disponibles para esta persona');
  readonly showEnrollmentInfo = input(false);
  readonly disabled = input(false);
  readonly secondaryDisabled = input(false);

  readonly actionPressed = output<string>();
  readonly secondaryActionPressed = output<string>();
  readonly menuChanged = output<ActivityChangePayload>();
  readonly activityChanged = output<ActivityChangePayload>();

  protected onActionClick(): void {
    this.actionPressed.emit(this.member().id);
  }

  protected onMenuChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const payload: ActivityChangePayload = {
      memberId: this.member().id,
      memberOrigin: this.member().origin,
      menuId: target.value || null,
      slot: this.slot(),
    };

    this.menuChanged.emit(payload);
    this.activityChanged.emit(payload);
  }

  protected onSecondaryActionClick(): void {
    this.secondaryActionPressed.emit(this.member().id);
  }

  protected selectorOptions(): MenuOption[] {
    const activities = this.activityOptions();
    if (activities.length > 0) {
      return activities;
    }

    return this.menuOptions();
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
