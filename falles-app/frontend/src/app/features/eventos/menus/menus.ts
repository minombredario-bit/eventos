import { ChangeDetectionStrategy, Component, computed, effect, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { firstValueFrom, map } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { CtaButton } from '../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../shared/components/member-row/member-row';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { FamilyMember, MealSlot, MenuOption } from '../models/ui';
import { EventoDetalleApi, EventosApi, MenuEventoApi, PersonaFamiliarApi } from '../services/eventos-api';

interface MenuChangePayload {
  memberId: string;
  menuId: string;
  slot: string | null;
}

interface SelectionSummaryRow {
  memberId: string;
  menuId: string;
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
  private readonly eventosApi = inject(EventosApi);

  private readonly eventId = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id') ?? '')),
    { initialValue: '' }
  );

  protected readonly loading = signal(true);
  protected readonly loadingPeople = signal(true);
  protected readonly submitting = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly submitError = signal<string | null>(null);

  protected readonly event = signal<EventoDetalleApi | null>(null);
  protected readonly members = signal<FamilyMember[]>([]);
  protected readonly options = signal<MenuOption[]>([]);

  protected readonly slots: MealSlot[] = ['almuerzo', 'comida', 'merienda', 'cena'];

  protected readonly selectedMenus = signal<Record<string, Partial<Record<MealSlot, string | null>>>>(
    {}
  );

  private readonly loadEventEffect = effect(() => {
    const id = this.eventId();
    if (!id) {
      return;
    }

    void this.loadEvent(id);
  });

  private readonly loadMembersEffect = effect(() => {
    void this.loadMembers();
  });

  private readonly syncSelectionEffect = effect(() => {
    const members = this.members();

    if (!members.length) {
      return;
    }

    this.selectedMenus.update((state) => {
      const next: Record<string, Partial<Record<MealSlot, string | null>>> = {};

      for (const member of members) {
        const previous = state[member.id] ?? {};
        const perSlotSelection: Partial<Record<MealSlot, string | null>> = {};

        for (const slot of this.getAvailableSlotsForMember(member)) {
          const slotOptions = this.getMenusForSlotAndMember(slot, member);
          const hasPrevious = previous[slot] !== null && previous[slot] !== undefined;
          const previousIsValid = slotOptions.some((option) => option.id === previous[slot]);

          if (hasPrevious && previousIsValid) {
            perSlotSelection[slot] = previous[slot] ?? null;
            continue;
          }

          perSlotSelection[slot] = slotOptions[0]?.id ?? null;
        }

        next[member.id] = perSlotSelection;
      }

      return next;
    });
  });

  protected readonly totalPrice = computed(() => {
    return this.selectionSummary().reduce((total, row) => total + row.price, 0);
  });

  protected readonly selectionSummary = computed<SelectionSummaryRow[]>(() => {
    const summary: SelectionSummaryRow[] = [];

    for (const member of this.members()) {
      for (const slot of this.getAvailableSlotsForMember(member)) {
        const selectedId = this.selectedMenus()[member.id]?.[slot];
        if (!selectedId) {
          continue;
        }

        const selectedOption = this.options().find((menu) => menu.id === selectedId);
        if (!selectedOption) {
          continue;
        }

        summary.push({
          memberId: member.id,
          menuId: selectedOption.id,
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
    if (!this.members().length || !this.event()?.inscripcionAbierta) {
      return false;
    }

    return this.members().every((member) => {
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
    return this.options().filter((menu) => {
      if (menu.slot !== slot) {
        return false;
      }

      return menu.compatibility === 'ambos' || menu.compatibility === member.personType;
    });
  }

  protected hasSlotForAnyMember(slot: MealSlot): boolean {
    return this.members().some((member) => this.getMenusForSlotAndMember(slot, member).length > 0);
  }

  protected slotLabel(slot: MealSlot): string {
    return slot.charAt(0).toUpperCase() + slot.slice(1);
  }

  private getAvailableSlotsForMember(member: FamilyMember): MealSlot[] {
    return this.slots.filter((slot) => this.getMenusForSlotAndMember(slot, member).length > 0);
  }

  protected goBack(): void {
    void this.router.navigate(['/eventos', this.eventId(), 'detalle']);
  }

  protected async confirmSelection(): Promise<void> {
    const eventId = this.eventId();
    if (!eventId || !this.readyToConfirm() || this.submitting()) {
      return;
    }

    this.submitError.set(null);
    this.submitting.set(true);

    try {
      const payload = this.selectionSummary().map((row) => ({
        persona: row.memberId,
        menu: row.menuId,
      }));

      const created = await firstValueFrom(this.eventosApi.crearInscripcion(eventId, payload));

      void this.router.navigate(['/eventos', eventId, 'credencial'], {
        queryParams: { inscripcionId: created.id },
      });
    } catch {
      this.submitError.set('No se pudo confirmar la inscripción. Revisá los datos e intentá nuevamente.');
    } finally {
      this.submitting.set(false);
    }
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected readonly confirmButtonLabel = computed(() => {
    if (this.submitting()) {
      return 'Confirmando inscripción...';
    }

    return `Confirmar menús · ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(this.totalPrice())}`;
  });

  private async loadEvent(id: string): Promise<void> {
    this.loading.set(true);
    this.errorMessage.set(null);

    try {
      const evento = await firstValueFrom(this.eventosApi.getEvento(id));
      this.event.set(evento);
      this.options.set(
        evento.menus
          .filter((menu) => menu.activo !== false)
          .map((menu) => this.toMenuOption(menu)),
      );
    } catch {
      this.errorMessage.set('No pudimos cargar el evento. Volvé a intentar en unos segundos.');
      this.event.set(null);
      this.options.set([]);
    } finally {
      this.loading.set(false);
    }
  }

  private async loadMembers(): Promise<void> {
    this.loadingPeople.set(true);

    try {
      const personas = await firstValueFrom(this.eventosApi.getPersonasMias());
      this.members.set(personas.map((persona) => this.toFamilyMember(persona)));
    } catch {
      this.errorMessage.set('No pudimos cargar tus familiares para la inscripción.');
      this.members.set([]);
    } finally {
      this.loadingPeople.set(false);
    }
  }

  private toFamilyMember(persona: PersonaFamiliarApi): FamilyMember {
    return {
      id: persona.id,
      name: persona.nombreCompleto,
      role: persona.parentesco,
      personType: persona.tipoPersona,
      avatarInitial: persona.nombre.charAt(0).toUpperCase(),
      notes: persona.observaciones ?? undefined,
    };
  }

  private toMenuOption(menu: MenuEventoApi): MenuOption {
    return {
      id: menu.id,
      label: menu.nombre,
      description: menu.descripcion ?? '',
      slot: menu.franjaComida,
      compatibility: menu.compatibilidadPersona,
      price: menu.precioBase,
    };
  }
}
