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

interface MenuChangePayload {
  memberId: string;
  menuId: string;
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

  protected readonly selectedMenus = signal<Record<string, string>>(
    FAMILY_MEMBERS.reduce(
      (acc, member, index) => {
        acc[member.id] = MENU_OPTIONS[Math.min(index, MENU_OPTIONS.length - 1)].id;
        return acc;
      },
      {} as Record<string, string>
    )
  );

  protected readonly totalPrice = computed(() => {
    return this.members.reduce((acc, member) => {
      const selectedId = this.selectedMenus()[member.id];
      const selectedOption = this.options.find((menu) => menu.id === selectedId);
      return acc + (selectedOption?.price ?? 0);
    }, 0);
  });

  protected readonly selectionSummary = computed(() => {
    return this.members.map((member) => {
      const selectedId = this.selectedMenus()[member.id];
      const selectedOption = this.options.find((menu) => menu.id === selectedId) ?? this.options[0];

      return {
        memberName: member.name,
        menuLabel: selectedOption.label,
        price: selectedOption.price,
      };
    });
  });

  protected readonly readyToConfirm = computed(() => {
    const selection = this.selectedMenus();
    return this.members.every((member) => Boolean(selection[member.id]));
  });

  protected updateMenu(payload: MenuChangePayload): void {
    this.selectedMenus.update((state) => ({
      ...state,
      [payload.memberId]: payload.menuId,
    }));
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
