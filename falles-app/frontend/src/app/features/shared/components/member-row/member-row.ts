import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FamilyMember, MenuOption } from '../../../eventos/models/ui';

interface MenuChangePayload {
  memberId: string;
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
  readonly showSelect = input(false);
  readonly selectorLabel = input('Menú');
  readonly slot = input<string | null>(null);
  readonly menuOptions = input<MenuOption[]>([]);
  readonly selectedMenuId = input<string | null>(null);
  readonly emptyMessage = input('No hay menús disponibles para esta persona');
  readonly disabled = input(false);

  readonly actionPressed = output<string>();
  readonly menuChanged = output<MenuChangePayload>();

  protected onActionClick(): void {
    this.actionPressed.emit(this.member().id);
  }

  protected onMenuChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    this.menuChanged.emit({
      memberId: this.member().id,
      menuId: target.value || null,
      slot: this.slot(),
    });
  }
}
