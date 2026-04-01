import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, forkJoin, map, of, switchMap, tap } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { CtaButton } from '../../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../../shared/components/member-row/member-row';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { FamilyMember, MealSlot, MenuOption, ParticipantOrigin } from '../../domain/eventos.models';
import { EventosMapper } from '../../data/eventos.mapper';
import { EventoDetalleApi, EventosApi, InscripcionApi, ParticipanteSeleccionApi } from '../../data/eventos.api';
import { EventosStore } from '../../store/eventos.store';

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
  name?: string;
  enrollment?: FamilyMember['enrollment'];
}

interface ExistingInscriptionLineView {
  id: string;
  menuLabel: string;
  price: number;
  stateLabel: string;
}

interface ExistingInscriptionRowView {
  key: string;
  memberName: string;
  lines: ExistingInscriptionLineView[];
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
  private readonly eventosStore = inject(EventosStore);

  private readonly eventId$ = this.route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    filter((id): id is string => Boolean(id)),
    distinctUntilChanged(),
  );

  protected readonly eventId = toSignal(this.eventId$, { initialValue: '' });

  // ── Estado ────────────────────────────────────────────────────────────
  protected readonly loading        = signal(true);
  protected readonly loadingPeople  = signal(true);
  protected readonly loadingInscriptions = signal(true);
  protected readonly submitting     = signal(false);
  protected readonly errorMessage   = signal<string | null>(null);
  protected readonly submitError    = signal<string | null>(null);
  protected readonly existingFlowError = signal<string | null>(null);
  protected readonly processingLineId = signal<string | null>(null);

  protected readonly event          = signal<EventoDetalleApi | null>(null);
  protected readonly preselectedParticipants = signal<ParticipantReference[]>([]);
  protected readonly familyMembers  = signal<FamilyMember[]>([]);
  protected readonly invitados       = signal<FamilyMember[]>([]);
  protected readonly options        = signal<MenuOption[]>([]);
  protected readonly removedMemberIds = signal<string[]>([]);
  protected readonly selectedMenus  = signal<Record<string, Partial<Record<MealSlot, string | null>>>>({});

  protected readonly slots: MealSlot[] = ['almuerzo', 'comida', 'merienda', 'cena'];
  protected readonly myInscriptions = signal<InscripcionApi[]>([]);

  // ── Derivados ─────────────────────────────────────────────────────────
  protected readonly members = computed(() => [
    ...this.familyMembers(),
    ...this.relatedMembersFromSelection(),
    ...this.invitados(),
  ]);

  protected readonly relatedMembersFromSelection = computed<FamilyMember[]>(() => {
    const existing = new Set(
      [...this.familyMembers(), ...this.invitados()].map((m) => this.participantKey(m.id, m.origin)),
    );

    return this.preselectedParticipants()
      .filter((p) => p.origin === 'familiar')
      .filter((p) => !existing.has(this.participantKey(p.id, p.origin)))
      .map((p) => this.toRelatedMember(p));
  });

  protected readonly membersInScope = computed(() => {
    const preselectedIds = this.preselectedParticipants();
    const removedSet = new Set(this.removedMemberIds());

    if (!preselectedIds.length) {
      return this.members().filter((m) => !removedSet.has(this.participantKey(m.id, m.origin)));
    }

    const selectedSet = new Set(
      preselectedIds.map((p) => this.participantKey(p.id, p.origin)),
    );

    return this.members().filter((m) => {
      const key = this.participantKey(m.id, m.origin);
      return selectedSet.has(key) && !removedSet.has(key);
    });
  });

  protected readonly removedMembers = computed(() => {
    const removedSet = new Set(this.removedMemberIds());
    return this.members().filter((m) => removedSet.has(this.participantKey(m.id, m.origin)));
  });

  protected readonly hasPreselection = computed(() => this.preselectedParticipants().length > 0);
  protected readonly selectedInscription = computed(() =>
    this.myInscriptions().find((inscripcion) => inscripcion.evento.id === this.eventId()) ?? null,
  );
  protected readonly isSelectionFlow = computed(() => this.hasPreselection());
  protected readonly hasExistingInscription = computed(() => this.selectedInscription() !== null);
  protected readonly canModifyExistingInscription = computed(() =>
    this.selectedInscription()?.evento.inscripcionAbierta === true,
  );

  protected readonly existingInscriptionRows = computed<ExistingInscriptionRowView[]>(() => {
    const inscription = this.selectedInscription();
    if (!inscription) return [];

    const rows = new Map<string, ExistingInscriptionRowView>();

    for (const linea of inscription.lineas.filter((linea) => linea.estadoLinea !== 'cancelada')) {
      const memberName = linea.nombrePersonaSnapshot.trim() || 'Participante';
      const row = rows.get(memberName) ?? {
        key: memberName,
        memberName,
        lines: [],
      };

      row.lines.push({
        id: linea.id,
        menuLabel: linea.nombreMenuSnapshot,
        price: linea.precioUnitario,
        stateLabel: this.formatLineStateLabel(linea.estadoLinea),
      });

      rows.set(memberName, row);
    }

    return [...rows.values()];
  });

  protected readonly existingInscriptionSummary = computed(() => {
    const inscription = this.selectedInscription();
    if (!inscription) return '';

    const userCount = this.existingInscriptionRows().length;
    const menuCount = this.existingInscriptionRows().reduce((total, row) => total + row.lines.length, 0);
    const modeText = this.canModifyExistingInscription()
      ? 'Podés quitar líneas para modificarla.'
      : 'Solo lectura.';

    if (menuCount === 0) {
      return `Esta inscripción no tiene líneas activas. ${modeText}`;
    }

    return `Esta inscripción reúne ${userCount} usuario${userCount === 1 ? '' : 's'} y ${menuCount} menú${menuCount === 1 ? '' : 's'}. ${modeText}`;
  });

  protected readonly participantScopeMessage = computed(() => {
    const visible = this.membersInScope().length;
    const removed = this.removedMemberIds().length;

    if (!this.hasPreselection()) {
      if (this.hasExistingInscription()) {
        return this.canModifyExistingInscription()
          ? 'Entraste sin preselección desde detalle. Mostramos la inscripción actual del evento para revisar usuarios y quitar líneas.'
          : 'Entraste sin preselección desde detalle. Mostramos la inscripción actual del evento en modo solo lectura.';
      }

      return removed
        ? `Mostrando ${visible} participantes. Quitaste ${removed} de esta inscripción.`
        : 'No había selección previa en detalle. Elegí un evento inscrito para revisar usuarios y menús.';
    }

    if (visible === 0) return 'No encontramos familiares seleccionados válidos. Volvé al detalle.';
    if (visible === 1) return removed ? 'Mostrando 1 familiar seleccionado. También quitaste participantes.' : 'Mostrando 1 familiar seleccionado desde detalle.';
    return removed
      ? `Mostrando ${visible} familiares seleccionados. Quitaste ${removed} en esta pantalla.`
      : `Mostrando ${visible} familiares seleccionados desde detalle.`;
  });

  protected readonly selectionSummary = computed<SelectionSummaryRow[]>(() => {
    const summary: SelectionSummaryRow[] = [];
    for (const member of this.membersInScope()) {
      for (const slot of this.getAvailableSlotsForMember(member)) {
        const selectedId = this.selectedMenus()[member.id]?.[slot];
        if (!selectedId) continue;
        const option = this.options().find((m) => m.id === selectedId);
        if (!option) continue;
        summary.push({
          memberId: member.id, memberOrigin: member.origin, menuId: option.id,
          memberName: member.name, slot, menuLabel: option.label, price: option.price,
        });
      }
    }
    return summary;
  });

  protected readonly totalPrice = computed(() =>
    this.selectionSummary().reduce((total, row) => total + row.price, 0),
  );

  protected readonly readyToConfirm = computed(() =>
    Boolean(this.membersInScope().length && this.event()?.inscripcionAbierta && this.selectionSummary().length > 0),
  );

  protected readonly allMenusAreFree = computed(() => {
    const opts = this.options();
    return opts.length > 0 && opts.every((o) => !o.isPaid || o.price <= 0);
  });

  protected readonly confirmButtonLabel = computed(() => {
    if (!this.event()?.inscripcionAbierta) return 'Inscripción cerrada';
    if (!this.membersInScope().length) return 'No hay participantes para registrar';
    if (this.selectionSummary().length === 0) return 'Elegí al menos un menú para registrar';
    if (this.submitting()) return 'Confirmando inscripción...';
    if (this.allMenusAreFree()) return 'Aceptar y confirmar inscripción';
    return `Confirmar menús · ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(this.totalPrice())}`;
  });

  constructor() {
    this.eventId$
      .pipe(
        tap((id) => this.loadEvent(id)),
        tap((id) => this.loadInvitados(id)),
        tap((id) => this.loadSeleccionParticipantes(id)),
        tap(() => this.loadMyInscriptions()),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe();

    this.loadPersonasMias();
  }

  // ── Handlers de UI ────────────────────────────────────────────────────

  protected updateMenu(payload: MenuChangePayload): void {
    if (!payload.slot) return;
    const slot = payload.slot as MealSlot;
    this.selectedMenus.update((state) => ({
      ...state,
      [payload.memberId]: { ...(state[payload.memberId] ?? {}), [slot]: payload.menuId },
    }));
  }

  protected getSelectedMenu(memberId: string, slot: MealSlot): string | null {
    return this.selectedMenus()[memberId]?.[slot] ?? null;
  }

  protected getMenusForSlotAndMember(slot: MealSlot, member: FamilyMember): MenuOption[] {
    return this.options().filter(
      (m) => m.slot === slot && (m.compatibility === 'ambos' || m.compatibility === member.personType),
    );
  }

  protected hasSlotForAnyMember(slot: MealSlot): boolean {
    return this.membersInScope().some((m) => this.getMenusForSlotAndMember(slot, m).length > 0);
  }

  protected slotLabel(slot: MealSlot): string {
    return slot.charAt(0).toUpperCase() + slot.slice(1);
  }

  protected clearMemberSlot(memberId: string, slot: MealSlot): void {
    this.updateMenu({ memberId, slot, menuId: null });
  }

  protected removeMemberFromMenus(member: FamilyMember): void {
    const key = this.participantKey(member.id, member.origin);
    this.removedMemberIds.update((c) => (c.includes(key) ? c : [...c, key]));
    this.selectedMenus.update((state) => ({ ...state, [member.id]: {} }));
  }

  protected restoreMemberToMenus(member: FamilyMember): void {
    const key = this.participantKey(member.id, member.origin);
    this.removedMemberIds.update((c) => c.filter((id) => id !== key));
  }

  protected goBack(): void {
    void this.router.navigate(['/eventos', this.eventId(), 'detalle']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected openInscriptionEvent(eventId: string): void {
    if (!eventId || eventId === this.eventId()) return;
    void this.router.navigate(['/eventos', eventId, 'menus']);
  }

  protected cancelInscriptionLine(lineId: string): void {
    const inscripcion = this.selectedInscription();
    if (!inscripcion || !this.canModifyExistingInscription() || this.processingLineId()) return;

    this.processingLineId.set(lineId);
    this.existingFlowError.set(null);

    this.eventosApi
      .cancelarLineaInscripcion(inscripcion.id, lineId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.reloadInscription(inscripcion.id);
          this.processingLineId.set(null);
        },
        error: () => {
          this.existingFlowError.set('No se pudo modificar la inscripción. Verificá estado y pagos.');
          this.processingLineId.set(null);
        },
      });
  }

  protected isProcessingLine(lineId: string): boolean {
    return this.processingLineId() === lineId;
  }

  // ── Acciones con Observables ──────────────────────────────────────────

  protected confirmSelection(): void {
    const eventId = this.eventId();
    if (!eventId || !this.readyToConfirm() || this.submitting()) return;

    this.submitError.set(null);
    this.submitting.set(true);

    const payload = this.selectionSummary().map((row) => ({
      persona: this.buildPersonaReference(row.memberId, row.memberOrigin),
      menu: row.menuId,
    }));

    this.eventosApi
      .crearInscripcion(eventId, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (created) => {
          this.submitting.set(false);
          void this.router.navigate(['/eventos', eventId, 'credencial'], {
            queryParams: { inscripcionId: created.id },
          });
        },
        error: () => {
          this.submitError.set('No se pudo confirmar la inscripción. Revisá los datos e intentá nuevamente.');
          this.submitting.set(false);
        },
      });
  }

  // ── Cargas internas con Observables ───────────────────────────────────

  private loadEvent(id: string): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.eventosApi
      .getEvento(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (evento) => {
          this.event.set(evento);
          this.removedMemberIds.set([]);
          this.loadMenusByEvento(id);
          this.reconcileSelectedMenus();
          this.loading.set(false);
        },
        error: () => {
          this.errorMessage.set('No pudimos cargar el evento. Volvé a intentar.');
          this.event.set(null);
          this.removedMemberIds.set([]);
          this.options.set([]);
          this.reconcileSelectedMenus();
          this.loading.set(false);
        },
      });
  }

  private loadPersonasMias(): void {
    this.loadingPeople.set(true);

    this.eventosStore
      .loadPersonasMias()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (members) => {
          this.familyMembers.set(members);
          this.reconcileSelectedMenus();
          this.loadingPeople.set(false);
        },
        error: () => {
          this.errorMessage.set('No pudimos cargar tus familiares para la inscripción.');
          this.familyMembers.set([]);
          this.reconcileSelectedMenus();
          this.loadingPeople.set(false);
        },
      });
  }

  private loadInvitados(eventId: string): void {
    this.eventosStore
      .getInvitadosByEvento(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (invitados) => {
          this.invitados.set(invitados.map((p) => this.eventosMapper.toFamilyMember(p)));
          this.reconcileSelectedMenus();
        },
        error: () => {
          this.invitados.set([]);
          this.reconcileSelectedMenus();
        },
      });
  }

  private loadMenusByEvento(eventId: string): void {
    this.eventosApi
      .getMenusByEvento(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (menus) => {
          this.options.set(
            menus
              .filter((menu) => this.menuBelongsToEvent(menu, eventId))
              .filter((menu) => menu.activo !== false)
              .map((menu) => this.eventosMapper.toMenuOption(menu)),
          );
          this.reconcileSelectedMenus();
        },
        error: () => {
          this.options.set([]);
          this.reconcileSelectedMenus();
        },
      });
  }

  private loadSeleccionParticipantes(eventId: string): void {
    this.preselectedParticipants.set([]);

    this.eventosApi
      .getSeleccionParticipantes(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (participantes) => {
          this.preselectedParticipants.set(this.toParticipantReferences(participantes));
          this.reconcileSelectedMenus();
        },
        error: () => {
          this.preselectedParticipants.set([]);
          this.reconcileSelectedMenus();
        },
      });
  }

  private loadMyInscriptions(): void {
    this.loadingInscriptions.set(true);

    this.eventosApi
      .getInscripcionesMias()
      .pipe(
        switchMap((inscripciones) => {
          if (!inscripciones.length) {
            return of([] as InscripcionApi[]);
          }

          return forkJoin(inscripciones.map((inscripcion) => this.eventosApi.getInscripcion(inscripcion.id)));
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (inscripciones) => {
          this.myInscriptions.set(inscripciones);
          this.loadingInscriptions.set(false);
        },
        error: () => {
          this.myInscriptions.set([]);
          this.loadingInscriptions.set(false);
          this.existingFlowError.set('No pudimos cargar tus inscripciones para gestionar menús.');
        },
      });
  }

  private reloadInscription(inscripcionId: string): void {
    this.eventosApi
      .getInscripcion(inscripcionId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (inscripcion) => {
          this.myInscriptions.update((current) =>
            current.map((item) => item.id === inscripcion.id ? inscripcion : item),
          );
        },
        error: () => {
          this.existingFlowError.set('No pudimos refrescar la inscripción después del cambio.');
        },
      });
  }

  private reconcileSelectedMenus(): void {
    this.selectedMenus.update((state) => {
      const next: Record<string, Partial<Record<MealSlot, string | null>>> = {};
      for (const member of this.membersInScope()) {
        const previous = state[member.id] ?? {};
        const perSlot: Partial<Record<MealSlot, string | null>> = {};
        for (const slot of this.getAvailableSlotsForMember(member)) {
          const opts = this.getMenusForSlotAndMember(slot, member);
          const prev = previous[slot] ?? null;
          perSlot[slot] = prev === null || opts.some((o) => o.id === prev) ? prev : null;
        }
        next[member.id] = perSlot;
      }
      return next;
    });
  }

  private getAvailableSlotsForMember(member: FamilyMember): MealSlot[] {
    return this.slots.filter((slot) => this.getMenusForSlotAndMember(slot, member).length > 0);
  }

  private toParticipantReferences(participantes: ParticipanteSeleccionApi[]): ParticipantReference[] {
    if (!participantes.length) return [];

    const seen = new Set<string>();
    return participantes
      .map<ParticipantReference>((item) => {
        const nombreCompleto = `${item.nombre ?? ''} ${item.apellidos ?? ''}`.trim();

        return {
          id: item.id,
          origin: item.origen === 'invitado' ? 'invitado' : 'familiar',
          name: nombreCompleto || undefined,
          enrollment: this.toEnrollmentFromRelacion(item),
        };
      })
      .filter((p) => {
        const key = this.participantKey(p.id, p.origin);
        if (seen.has(key)) return false;
        seen.add(key);
        return p.id.length > 0;
      });
  }

  private toRelatedMember(participant: ParticipantReference): FamilyMember {
    const fallbackName = participant.id === this.authService.currentUserId
      ? `${this.authService.userSignal()?.nombre ?? ''} ${this.authService.userSignal()?.apellidos ?? ''}`.trim()
      : '';

    const name = participant.name?.trim() || fallbackName || 'Participante';

    return {
      id: participant.id,
      name,
      role: 'Relacionado',
      personType: 'adulto',
      origin: 'familiar',
      avatarInitial: name.charAt(0).toUpperCase() || 'P',
      enrollment: participant.enrollment,
    };
  }

  private toEnrollmentFromRelacion(participant: ParticipanteSeleccionApi): FamilyMember['enrollment'] | undefined {
    const inscripcion = participant.inscripcionRelacion;
    if (!inscripcion) return undefined;

    const firstActiveLine = inscripcion.lineas.find((linea) => linea.estadoLinea !== 'cancelada')
      ?? inscripcion.lineas[0];

    const eventTitle = this.event()?.titulo ?? 'Evento';

    return {
      eventId: this.event()?.id,
      eventTitle,
      eventLabel: firstActiveLine
        ? `${firstActiveLine.franjaComidaSnapshot} · ${firstActiveLine.nombreMenuSnapshot}`
        : `${eventTitle} · ${inscripcion.codigo}`,
      paymentStatus: this.eventosMapper.toPaymentBadgeStatus(inscripcion.estadoPago),
      paymentStatusRaw: inscripcion.estadoPago,
    };
  }

  private menuBelongsToEvent(menu: EventoDetalleApi['menus'][number], eventId: string): boolean {
    const evento = menu.evento;

    if (!evento) return true;
    if (typeof evento === 'string') {
      const normalized = evento.startsWith('/api/eventos/') ? evento.slice('/api/eventos/'.length) : evento;
      return normalized === eventId;
    }

    return evento.id === eventId;
  }

  private participantKey(id: string, origin: ParticipantOrigin): string {
    return `${origin === 'invitado' ? 'invitado' : 'familiar'}:${id}`;
  }

  private buildPersonaReference(id: string, origin: ParticipantOrigin): string {
    return origin === 'invitado' ? `/api/invitados/${id}` : `/api/persona_familiares/${id}`;
  }

  private formatLineStateLabel(state: string): string {
    const labels: Record<string, string> = {
      confirmada: 'Confirmada',
      pendiente: 'Pendiente',
      cancelada: 'Cancelada',
      lista_espera: 'Lista de espera',
    };

    return labels[state] ?? state;
  }
}
