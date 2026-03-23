import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { map } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { CtaButton } from '../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../shared/components/member-row/member-row';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { FAMILY_MEMBERS, MENU_OPTIONS, UPCOMING_EVENTS } from '../data/mock';
import { FamilyMember, MealSlot, MenuOption } from '../models/ui';

interface MenuChangePayload {
  memberId: string;
  menuId: string;
  slot: string | null;
}

interface SelectionSummaryRow {
  memberName: string;
  slot: MealSlot;
  menuLabel: string;
  price: number;
}

@Component({
  selector: 'app-menus',
  standalone: true,
  imports: [MobileHeader, MemberRow, CtaButton, CurrencyPipe],
  templateUrl: './menus.html',
  styleUrl: './menus.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Menus {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);

  private readonly eventId = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id') ?? UPCOMING_EVENTS[0].id)),
    { initialValue: UPCOMING_EVENTS[0].id }
  );

  protected readonly event = computed(() => {
    return UPCOMING_EVENTS.find((item) => item.id === this.eventId()) ?? UPCOMING_EVENTS[0];
  });

  protected readonly members = FAMILY_MEMBERS;
  protected readonly options = MENU_OPTIONS;
  protected readonly slots: MealSlot[] = ['almuerzo', 'comida', 'merienda', 'cena'];

  protected readonly selectedMenus = signal<Record<string, Partial<Record<MealSlot, string | null>>>>(
    this.buildInitialSelection()
  );

  protected readonly totalPrice = computed(() => {
    return this.selectionSummary().reduce((total, row) => total + row.price, 0);
  });

  protected readonly selectionSummary = computed<SelectionSummaryRow[]>(() => {
    const summary: SelectionSummaryRow[] = [];

    for (const member of this.members) {
      for (const slot of this.getAvailableSlotsForMember(member)) {
        const selectedId = this.selectedMenus()[member.id]?.[slot];
        if (!selectedId) {
          continue;
        }

        const selectedOption = this.options.find((menu) => menu.id === selectedId);
        if (!selectedOption) {
          continue;
        }

        summary.push({
          memberName: member.name,
          slot,
          menuLabel: selectedOption.label,
          price: selectedOption.price,
        });
      }
    }

    return summary;
  });

  protected readonly readyToConfirm = computed(() => {
    return this.members.every((member) => {
      const slots = this.getAvailableSlotsForMember(member);
      return slots.every((slot) => {
        const selectedId = this.selectedMenus()[member.id]?.[slot];
        return Boolean(selectedId);
      });
    });
  });

  protected updateMenu(payload: MenuChangePayload): void {
    if (!payload.slot) {
      return;
    }

    const slot = payload.slot as MealSlot;

    this.selectedMenus.update((state) => ({
      ...state,
      [payload.memberId]: {
        ...(state[payload.memberId] ?? {}),
        [slot]: payload.menuId,
      },
    }));
  }

  protected getSelectedMenu(memberId: string, slot: MealSlot): string | null {
    return this.selectedMenus()[memberId]?.[slot] ?? null;
  }

  protected getMenusForSlotAndMember(slot: MealSlot, member: FamilyMember): MenuOption[] {
    return this.options.filter((menu) => {
      if (menu.slot !== slot) {
        return false;
      }

      return menu.compatibility === 'ambos' || menu.compatibility === member.personType;
    });
  }

  protected hasSlotForAnyMember(slot: MealSlot): boolean {
    return this.members.some((member) => this.getMenusForSlotAndMember(slot, member).length > 0);
  }

  protected slotLabel(slot: MealSlot): string {
    return slot.charAt(0).toUpperCase() + slot.slice(1);
  }

  private buildInitialSelection(): Record<string, Partial<Record<MealSlot, string | null>>> {
    return this.members.reduce((acc, member) => {
      const perSlotSelection: Partial<Record<MealSlot, string | null>> = {};

      for (const slot of this.slots) {
        const options = this.getMenusForSlotAndMember(slot, member);
        perSlotSelection[slot] = options[0]?.id ?? null;
      }

      acc[member.id] = perSlotSelection;
      return acc;
    }, {} as Record<string, Partial<Record<MealSlot, string | null>>>);
  }

  private getAvailableSlotsForMember(member: FamilyMember): MealSlot[] {
    return this.slots.filter((slot) => this.getMenusForSlotAndMember(slot, member).length > 0);
  }

  protected goBack(): void {
    void this.router.navigate(['/eventos', this.event().id, 'detalle']);
  }

  protected confirmSelection(): void {
    void this.router.navigate(['/eventos', this.event().id, 'credencial']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }
}
