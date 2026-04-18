import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import {
  ActivityOption,
  FamilyMember,
  MealSlot,
  PaymentBadgeStatus,
} from '../../../eventos/domain/eventos.models';
import { ActivityChangePayload } from '../../../eventos/domain/actividades.models';

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
  readonly slot = input<MealSlot | null>(null);
  readonly activityOptions = input<ActivityOption[]>([]);
  readonly actividadOptions = input<ActivityOption[]>([]);
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

  protected selectorOptions(): ActivityOption[] {
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
