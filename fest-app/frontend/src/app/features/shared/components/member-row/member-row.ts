import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FamilyMember, ActividadOption, ParticipantOrigin, PaymentBadgeStatus } from '../../../eventos/domain/eventos.models';

interface ActivityChangePayload {
  memberId: string;
  memberOrigin: ParticipantOrigin;
  actividadId: string | null;
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
  readonly activityOptions = input<ActividadOption[]>([]);
  readonly actividadOptions = input<ActividadOption[]>([]);
  readonly selectedActividadId = input<string | null>(null);
  readonly emptyMessage = input('No hay actividades disponibles para esta persona');
  readonly showEnrollmentInfo = input(false);
  readonly disabled = input(false);
  readonly secondaryDisabled = input(false);

  readonly actionPressed = output<string>();
  readonly secondaryActionPressed = output<string>();
  readonly actividadChanged = output<ActivityChangePayload>();
  readonly activityChanged = output<ActivityChangePayload>();

  protected onActionClick(): void {
    this.actionPressed.emit(this.member().id);
  }

  protected onActividadChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const payload: ActivityChangePayload = {
      memberId: this.member().id,
      memberOrigin: this.member().origin,
      actividadId: target.value || null,
      slot: this.slot(),
    };

    this.actividadChanged.emit(payload);
    this.activityChanged.emit(payload);
  }

  protected onSecondaryActionClick(): void {
    this.secondaryActionPressed.emit(this.member().id);
  }

  protected selectorOptions(): ActividadOption[] {
    const activities = this.activityOptions();
    if (activities.length > 0) {
      return activities;
    }

    return this.actividadOptions();
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
