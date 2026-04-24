import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import {
  ActivityOption,
  FamilyMember,
  MealSlot,
  PaymentBadgeStatus,
} from '../../../eventos/domain/eventos.models';
import { ActivityChangePayload } from '../../../eventos/domain/actividades.models';
import {getActivityPrice} from '../../../../core/utils/activity-price.utils';

@Component({
  selector: 'app-member-row',
  standalone: true,
  imports: [CurrencyPipe],
  templateUrl: './member-row.html',
  styleUrl: './member-row.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MemberRow {
  // member shape is FamilyMember in most callers; use runtime checks for other shapes when needed
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
  readonly activityChanged = output<ActivityChangePayload>();

  protected onActionClick(): void {
    this.actionPressed.emit(this.member().id);
  }

  protected onActividadChange(event: Event): void {
    const target = event.target as HTMLSelectElement;

    const origin = (this.member() as any).origin ?? (this.member() as any).origen ?? 'familiar';

    const payload: ActivityChangePayload = {
      memberId: this.member().id,
      memberOrigin: origin as any,
      actividadId: target.value || null,
      slot: this.slot(),
    };

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

  /**
   * Return a human label to show in the role/tag area.
   * Prefer `role` when present, otherwise fallback to `parentesco` (guests/familiares).
   */
  protected relationLabel(): string | null {
    const m = this.member() as any;
    const role = m?.role && String(m.role).trim() ? String(m.role).trim() : null;
    if (role) return role;
    if (m?.parentesco) return String(m.parentesco);
    if (m?.parentescoLabel) return String(m.parentescoLabel);
    return null;
  }

  protected readonly getActivityPrice = getActivityPrice;
}
