import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, firstValueFrom, map } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { CtaButton } from '../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../shared/components/member-row/member-row';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { FamilyMember, MealSlot, MenuOption, ParticipantOrigin } from '../models/ui';
import { EventosMapper } from '../services/eventos-mapper';
import { EventoDetalleApi, EventosApi } from '../services/eventos-api';

interface MenuChangePayload {
  memberId: string;
  menuId: string | null;
  slot: string | null;
}

interface SelectionSummaryRow {
  memberId: string;
  memberOrigin: ParticipantOrigin;
  menuId: string;
  memberName: string;
  slot: MealSlot;
  menuLabel: string;
  price: number;
}

interface ParticipantReference {
  id: string;
  origin: ParticipantOrigin;
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
  private readonly destroyRef = inject(DestroyRef);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);
  private readonly eventosMapper = inject(EventosMapper);

  private readonly eventId = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id') ?? '')),
    { initialValue: '' }
  );

  private readonly preselectedParticipantIds = toSignal(
    this.route.queryParamMap.pipe(map((params) => this.parseParticipantsParam(params.get('participants')))),
    { initialValue: [] as ParticipantReference[] }
  );

  protected readonly loading = signal(true);
  protected readonly loadingPeople = signal(true);
  protected readonly submitting = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly submitError = signal<string | null>(null);

  protected readonly event = signal<EventoDetalleApi | null>(null);
  protected readonly familyMembers = signal<FamilyMember[]>([]);
  protected readonly noFalleros = signal<FamilyMember[]>([]);
  protected readonly options = signal<MenuOption[]>([]);
  protected readonly removedMemberIds = signal<string[]>([]);

  protected readonly members = computed(() => {
    return [...this.familyMembers(), ...this.noFalleros()];
  });

  protected readonly membersInScope = computed(() => {
    const members = this.members();
    const preselectedIds = this.preselectedParticipantIds();
    const removedSet = new Set(this.removedMemberIds());

    if (!preselectedIds.length) {
      return members.filter((member) => !removedSet.has(this.participantKey(member.id, member.origin)));
    }

    const selectedSet = new Set(preselectedIds.map((participant) => this.participantKey(participant.id, participant.origin)));

    return members.filter((member) => {
      const memberKey = this.participantKey(member.id, member.origin);
      return selectedSet.has(memberKey) && !removedSet.has(memberKey);
    });
  });

  protected readonly removedMembers = computed(() => {
    const removedSet = new Set(this.removedMemberIds());
    return this.members().filter((member) => removedSet.has(this.participantKey(member.id, member.origin)));
  });

  protected readonly hasPreselection = computed(() => this.preselectedParticipantIds().length > 0);

  protected readonly participantScopeMessage = computed(() => {
    const visible = this.membersInScope().length;
    const removed = this.removedMemberIds().length;

    if (!this.hasPreselection()) {
      return removed
        ? `Mostrando ${visible} participantes. Quitaste ${removed} de esta inscripción.`
        : 'No había selección previa en detalle. Mostramos todos tus familiares.';
    }

    if (visible === 0) {
      return 'No encontramos familiares seleccionados válidos para este evento. Volvé al detalle para seleccionar participantes.';
    }

    if (visible === 1) {
      return removed
        ? 'Mostrando 1 familiar seleccionado desde detalle. También quitaste participantes en esta pantalla.'
        : 'Mostrando 1 familiar seleccionado desde detalle.';
    }

    return removed
      ? `Mostrando ${visible} familiares seleccionados desde detalle. Quitaste ${removed} en esta pantalla.`
      : `Mostrando ${visible} familiares seleccionados desde detalle.`;
  });

  protected readonly slots: MealSlot[] = ['almuerzo', 'comida', 'merienda', 'cena'];

  protected readonly selectedMenus = signal<Record<string, Partial<Record<MealSlot, string | null>>>>(
    {}
  );

  constructor() {
    this.route.paramMap
      .pipe(
        map((params) => params.get('id') ?? ''),
        filter((id): id is string => Boolean(id)),
        distinctUntilChanged(),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe((id) => {
        void this.loadEvent(id);
        void this.loadNoFalleros(id);
      });

    void this.loadMembers();
  }

  protected readonly totalPrice = computed(() => {
    return this.selectionSummary().reduce((total, row) => total + row.price, 0);
  });

  protected readonly selectionSummary = computed<SelectionSummaryRow[]>(() => {
    const summary: SelectionSummaryRow[] = [];

    for (const member of this.membersInScope()) {
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
          memberOrigin: member.origin,
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
    if (!this.membersInScope().length || !this.event()?.inscripcionAbierta) {
      return false;
    }

    return this.selectionSummary().length > 0;
  });

  protected readonly allMenusAreFree = computed(() => {
    const options = this.options();
    if (!options.length) {
      return false;
    }

    return options.every((option) => !option.isPaid || option.price <= 0);
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
    return this.membersInScope().some((member) => this.getMenusForSlotAndMember(slot, member).length > 0);
  }

  protected slotLabel(slot: MealSlot): string {
    return slot.charAt(0).toUpperCase() + slot.slice(1);
  }

  protected clearMemberSlot(memberId: string, slot: MealSlot): void {
    this.updateMenu({ memberId, slot, menuId: null });
  }

  protected removeMemberFromMenus(member: FamilyMember): void {
    const memberKey = this.participantKey(member.id, member.origin);

    this.removedMemberIds.update((current) => {
      if (current.includes(memberKey)) {
        return current;
      }

      return [...current, memberKey];
    });

    this.selectedMenus.update((state) => ({
      ...state,
      [member.id]: {},
    }));
  }

  protected restoreMemberToMenus(member: FamilyMember): void {
    const memberKey = this.participantKey(member.id, member.origin);
    this.removedMemberIds.update((current) => current.filter((id) => id !== memberKey));
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
        persona: this.buildPersonaReference(row.memberId, row.memberOrigin),
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
    if (!this.event()?.inscripcionAbierta) {
      return 'Inscripción cerrada';
    }

    if (!this.membersInScope().length) {
      return 'No hay participantes para registrar';
    }

    if (this.selectionSummary().length === 0) {
      return 'Elegí al menos un menú para registrar';
    }

    if (this.submitting()) {
      return 'Confirmando inscripción...';
    }

    if (this.allMenusAreFree()) {
      return 'Aceptar y confirmar inscripción';
    }

    return `Confirmar menús · ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(this.totalPrice())}`;
  });

  private async loadEvent(id: string): Promise<void> {
    this.loading.set(true);
    this.errorMessage.set(null);

    try {
      const evento = await firstValueFrom(this.eventosApi.getEvento(id));
      this.event.set(evento);
      this.removedMemberIds.set([]);
      this.options.set(
        evento.menus
          .filter((menu) => menu.activo !== false)
          .map((menu) => this.eventosMapper.toMenuOption(menu)),
      );
      this.reconcileSelectedMenus();
    } catch {
      this.errorMessage.set('No pudimos cargar el evento. Volvé a intentar en unos segundos.');
      this.event.set(null);
      this.removedMemberIds.set([]);
      this.options.set([]);
      this.reconcileSelectedMenus();
    } finally {
      this.loading.set(false);
    }
  }

  private async loadMembers(): Promise<void> {
    this.loadingPeople.set(true);

    try {
      const personas = await firstValueFrom(this.eventosApi.getPersonasMias());
      this.familyMembers.set(personas.map((persona) => this.eventosMapper.toFamilyMember(persona)));
      this.reconcileSelectedMenus();
    } catch {
      this.errorMessage.set('No pudimos cargar tus familiares para la inscripción.');
      this.familyMembers.set([]);
      this.reconcileSelectedMenus();
    } finally {
      this.loadingPeople.set(false);
    }
  }

  private async loadNoFalleros(eventId: string): Promise<void> {
    try {
      const noFalleros = await firstValueFrom(this.eventosApi.getNoFallerosByEvento(eventId));
      this.noFalleros.set(noFalleros.map((persona) => this.eventosMapper.toFamilyMember(persona)));
      this.reconcileSelectedMenus();
    } catch {
      this.noFalleros.set([]);
      this.reconcileSelectedMenus();
    }
  }

  private reconcileSelectedMenus(): void {
    const members = this.membersInScope();

    this.selectedMenus.update((state) => {
      const next: Record<string, Partial<Record<MealSlot, string | null>>> = {};

      for (const member of members) {
        const previous = state[member.id] ?? {};
        const perSlotSelection: Partial<Record<MealSlot, string | null>> = {};

        for (const slot of this.getAvailableSlotsForMember(member)) {
          const slotOptions = this.getMenusForSlotAndMember(slot, member);
          const previousSelection = previous[slot] ?? null;
          const previousIsValid = previousSelection === null
            || slotOptions.some((option) => option.id === previousSelection);

          perSlotSelection[slot] = previousIsValid ? previousSelection : null;
        }

        next[member.id] = perSlotSelection;
      }

      return next;
    });
  }

  private parseParticipantsParam(rawValue: string | null): ParticipantReference[] {
    if (!rawValue) {
      return [];
    }

    const parsed = rawValue
      .split(',')
      .map((token) => token.trim())
      .filter((token) => token.length > 0)
      .map<ParticipantReference>((token) => {
        const [originRaw = '', ...idParts] = token.split(':');

        if (idParts.length === 0) {
          return {
            id: originRaw,
            origin: 'familiar' as const,
          };
        }

        const origin: ParticipantOrigin = originRaw === 'no_fallero' ? 'no_fallero' : 'familiar';

        return {
          id: idParts.join(':').trim(),
          origin,
        };
      })
      .filter((participant) => participant.id.length > 0);

    const seen = new Set<string>();
    return parsed.filter((participant) => {
      const key = this.participantKey(participant.id, participant.origin);
      if (seen.has(key)) {
        return false;
      }

      seen.add(key);
      return true;
    });
  }

  private participantKey(id: string, origin: ParticipantOrigin): string {
    return `${origin === 'no_fallero' ? 'no_fallero' : 'familiar'}:${id}`;
  }

  private buildPersonaReference(id: string, origin: ParticipantOrigin): string {
    if (origin === 'no_fallero') {
      return `/api/no_falleros/${id}`;
    }

    return `/api/persona_familiares/${id}`;
  }

}
