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
import { distinctUntilChanged, filter, map, tap } from 'rxjs';

import { AuthService } from '../../../../core/auth/auth';
import { CtaButton } from '../../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../../shared/components/member-row/member-row';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';

import { EventosMapper } from '../../data/eventos.mapper';
import { EventosApi } from '../../data/eventos.api';

import {
  EventoDetalle,
  Inscripcion,
  ParticipanteSeleccion,
  ActivityOption,
  FamilyMember,
  MealSlot,
  ParticipantOrigin,
} from '../../domain/eventos.models';

import {
  ActivityChangePayload,
  ExistingInscriptionLineView,
  ExistingInscriptionRowView,
  ExistingInscriptionTotalsView,
  ParticipantReference,
  ParticipantRelationLine,
  ParticipantRelationSummary,
  SelectionSummaryRow,
  SlotSelectionSummary,
} from '../../domain/actividades.models';

import {
  buildActivityCountLabel,
  shouldLoadLegacyInscripcionesFallback,
  shouldUseLegacyActividadesFallback,
} from '../../domain/actividades.utils';
import {getActivityPrice} from '../../../../core/utils/activity-price.utils';

@Component({
  selector: 'app-actividades',
  standalone: true,
  imports: [MobileHeader, MemberRow, CurrencyPipe],
  templateUrl: './actividades.html',
  styleUrl: './actividades.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Actividades {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);
  private readonly eventosMapper = inject(EventosMapper);

  private readonly eventId$ = this.route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    filter((id): id is string => Boolean(id)),
    distinctUntilChanged(),
  );

  protected readonly eventId = toSignal(this.eventId$, { initialValue: '' });

  // ── Estado ────────────────────────────────────────────────────────────
  protected readonly loading        = signal(true);
  protected readonly loadingPeople  = signal(true);
  protected readonly loadingInscriptions = signal(false);
  protected readonly submitting     = signal(false);
  protected readonly errorMessage   = signal<string | null>(null);
  protected readonly submitError    = signal<string | null>(null);
  protected readonly existingFlowError = signal<string | null>(null);
  protected readonly processingLineId = signal<string | null>(null);

  protected readonly event          = signal<EventoDetalle | null>(null);
  protected readonly preselectedParticipants = signal<ParticipantReference[]>([]);
  protected readonly options        = signal<ActivityOption[]>([]);
  protected readonly removedMemberIds = signal<string[]>([]);
  protected readonly selectedActivities  = signal<Record<string, Partial<Record<MealSlot, string | null>>>>({});
  private readonly hydratedInscripcionId = signal<string | null>(null);
  private readonly hydratedPreselectionKey = signal<string | null>(null);

  protected readonly slots: MealSlot[] = ['almuerzo', 'comida', 'merienda', 'cena'];
  protected readonly myInscriptions = signal<Inscripcion[]>([]);

  // ── Derivados ─────────────────────────────────────────────────────────
  protected readonly members = computed<FamilyMember[]>(() => {
    const unique = new Map<string, FamilyMember>();

    for (const participant of this.preselectedParticipants()) {
      const member = this.toMemberFromSelection(participant);
      unique.set(this.participantKey(member.id, member.origin), member);
    }

    return [...unique.values()];
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
  protected readonly canModifyExistingInscription = computed(() =>
    this.event()?.inscripcionAbierta === true && (this.hasEnrichedSelectionData() || Boolean(this.selectedInscription())),
  );

  protected readonly hasPaidInscription = computed(() => {
    const fromSelection = this.preselectedParticipants()
      .some((participant) => participant.relationSummary?.estadoPago === 'pagado');
    const fromFallback = this.selectedInscription()?.estadoPago === 'pagado';
    return fromSelection || fromFallback;
  });

  protected readonly canConfirmWithoutSelections = computed(() =>
    this.canModifyExistingInscription()
    && this.existingInscriptionRows().some((row) => row.lines.length > 0)
    && !this.hasPaidInscription(),
  );

  protected readonly hasEnrichedSelectionData = computed(() =>
    this.preselectedParticipants().some((participant) => Boolean(participant.relationSummary)),
  );

  // Indica si el evento actual permite invitados y además tiene actividades activas
  protected readonly canUseGuestParticipants = computed(() =>
    Boolean(this.event()?.permiteInvitados && this.hasActiveActividadesFromEvent(this.event())),
  );

  protected readonly existingInscriptionRows = computed<ExistingInscriptionRowView[]>(() => {
    const rowsFromSelection = this.buildRowsFromSelectionContract();
    if (rowsFromSelection.length > 0) {
      return rowsFromSelection;
    }

    const inscription = this.selectedInscription();
    if (!inscription) return [];

    const rows = new Map<string, ExistingInscriptionRowView>();

    for (const linea of inscription.lineas.filter((linea) => linea.estadoLinea !== 'cancelada')) {
      // Si el evento no permite invitados, omitimos líneas de invitados
      const participantId = linea.usuarioId ?? linea.invitadoId;
      if (!participantId) continue;
      const origin: ParticipantOrigin = linea.usuarioId ? 'familiar' : 'invitado';
      if (origin === 'invitado' && !this.canUseGuestParticipants()) continue;

      const memberName = linea.nombrePersonaSnapshot.trim() || 'Participante';
      const memberRowKey = `${memberName}::${linea.tipoPersonaSnapshot ?? 'sin_tipo'}`;
      const row = rows.get(memberRowKey) ?? {
        key: memberRowKey,
        memberName,
        memberTypeLabel: this.formatPersonTypeLabel(linea.tipoPersonaSnapshot),
        lines: [],
      };

      row.lines.push({
        id: linea.id,
        inscripcionId: inscription.id,
        actividadLabel: linea.nombreActividadSnapshot,
        slot: this.normalizeMealSlot(linea.franjaComidaSnapshot),
        price: linea.precioUnitario,
        stateLabel: this.formatLineStatusByPayment(linea.estadoLinea, Boolean(linea.pagada)),
        pagada: Boolean(linea.pagada),
      });

      rows.set(memberRowKey, row);
    }

    return [...rows.values()];
  });

  protected readonly existingInscriptionTotals = computed<ExistingInscriptionTotalsView | null>(() => {
    const summaries = this.preselectedParticipants()
      .map((participant) => participant.relationSummary)
      .filter((summary): summary is ParticipantRelationSummary => Boolean(summary));

    if (!summaries.length) {
      return null;
    }

    return summaries.reduce<ExistingInscriptionTotalsView>((acc, summary) => ({
      totalLineas: acc.totalLineas + summary.totalLineas,
      totalPagado: acc.totalPagado + summary.totalPagado,
    }), {
      totalLineas: 0,
      totalPagado: 0,
    });
  });

  protected readonly existingInscriptionSummary = computed(() => {
    const hasAnyInscriptionData = this.hasEnrichedSelectionData() || Boolean(this.selectedInscription());
    if (!hasAnyInscriptionData) return '';

    const userCount = this.existingInscriptionRows().length;
    const actividadCount = this.existingInscriptionRows().reduce((total, row) => total + row.lines.length, 0);
    const modeText = this.canModifyExistingInscription()
      ? 'Puedes quitar líneas para modificarla.'
      : 'Solo lectura.';

    const totals = this.existingInscriptionTotals();
    const totalsText = totals
      ? ` Total líneas: ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(totals.totalLineas)} · Pagado: ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(totals.totalPagado)}.`
      : '';

    if (actividadCount === 0) {
      return `Esta inscripción no tiene líneas activas. ${modeText}${totalsText}`;
    }

    return `Esta inscripción reúne ${userCount} usuario${userCount === 1 ? '' : 's'} y ${buildActivityCountLabel(actividadCount)}. ${modeText}${totalsText}`;
  });

  protected readonly selectionSummary = computed<SelectionSummaryRow[]>(() => {
    const summary: SelectionSummaryRow[] = [];

    for (const member of this.membersInScope()) {
      for (const slot of this.getAvailableSlotsForMember(member)) {
        const selectedId =
          this.selectedActivities()[
            this.participantKey(member.id, member.origin)
            ]?.[slot];

        if (!selectedId) {
          continue;
        }

        const option = this.options().find((m) => m.id === selectedId);

        if (!option) {
          continue;
        }

        summary.push({
          memberId: member.id,
          memberOrigin: member.origin,
          actividadId: option.id,
          memberName: member.name,
          slot,
          actividadLabel: option.label,
          price: getActivityPrice(member, option),
        });
      }
    }

    return summary;
  });

  protected readonly totalPrice = computed(() =>
    this.selectionSummary().reduce((total, row) => total + row.price, 0),
  );

  protected readonly totalAlreadyPaid = computed(() => {
    const fromInscription = Number(this.selectedInscription()?.importePagado ?? 0);
    if (fromInscription > 0) {
      return fromInscription;
    }

    // Evita duplicar importes cuando varios participantes comparten la misma inscripción.
    const uniqueByInscription = new Map<string, number>();
    for (const participant of this.preselectedParticipants()) {
      const summary = participant.relationSummary;
      if (!summary?.id) continue;
      uniqueByInscription.set(summary.id, Number(summary.totalPagado ?? 0));
    }

    return [...uniqueByInscription.values()].reduce((acc, value) => acc + value, 0);
  });

  protected readonly totalPending = computed(() =>
    Math.max(0, this.totalPrice() - this.totalAlreadyPaid()),
  );

  protected readonly slotSummary = computed<SlotSelectionSummary[]>(() => {
    const grouped = new Map<MealSlot, SlotSelectionSummary>();

    for (const row of this.selectionSummary()) {
      const current = grouped.get(row.slot) ?? {
        slot: row.slot,
        total: 0,
        selections: 0,
      };

      current.total += row.price;
      current.selections += 1;
      grouped.set(row.slot, current);
    }

    return this.slots
      .map((slot) => grouped.get(slot))
      .filter((slot): slot is SlotSelectionSummary => Boolean(slot));
  });

  protected readonly availableSlots = computed(() =>
    this.slots.filter((slot) => this.hasSlotForAnyMember(slot)),
  );

  protected readonly readyToConfirm = computed(() =>
    Boolean(
      this.resolveExistingInscriptionId() && !this.submitting(),
    ),
  );

  protected readonly allActivitiesAreFree = computed(() => {
    // Usamos las opciones sin resolver precio por persona como heurística rápida;
    // si todas las actividades base son gratis, el evento es gratuito.
    const opts = this.options();
    return opts.length > 0 && opts.every((o) => !o.isPaid);
  });

  protected readonly confirmButtonLabel = computed(() => {
    if (this.submitting()) return 'Guardando actividades...';
    if (!this.resolveExistingInscriptionId()) return 'Selecciona una actividad para continuar';
    return 'Ir al pago';
  });

  protected readonly showPayCta = computed(() => {
    const activeLines = this.existingInscriptionRows().flatMap((row) => row.lines);
    if (!activeLines.length) {
      return true;
    }

    return activeLines.some((line) => !line.pagada);
  });

  protected readonly allActiveLinesPaid = computed(() => {
    const activeLines = this.existingInscriptionRows().flatMap((row) => row.lines);
    return activeLines.length > 0 && activeLines.every((line) => line.pagada);
  });

  constructor() {
    this.eventId$
      .pipe(
        tap((id) => this.loadEvent(id)),
        tap((id) => this.loadSeleccionParticipantes(id)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe();
  }

  // ── Handlers de UI ────────────────────────────────────────────────────

  protected updateActividad(payload: ActivityChangePayload): void {

    if (!payload.slot) return;
    const slot = payload.slot;
    const previousActividadId = this.getSelectedActivityByParticipant(payload.memberId, payload.memberOrigin, slot);
    const nextActividadId = payload.actividadId;
    if (previousActividadId === nextActividadId) return;

    this.setSelectedActivityByParticipant(payload.memberId, payload.memberOrigin, slot, nextActividadId);

    const eventId = this.eventId();
    if (!eventId) return;

    const existingBySlot = this.buildExistingSelectionBySlotMap(this.buildExistingSelectionMap());
    const slotKey = this.buildSelectionSlotKey(payload.memberId, payload.memberOrigin, slot);
    const existingLine = existingBySlot.get(slotKey);

    if (!nextActividadId) {
      if (!existingLine) {
        return;
      }

      this.submitting.set(true);
      this.submitError.set(null);
      this.eventosApi
        .cancelarLineaInscripcion(existingLine.inscripcionId, existingLine.lineId)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: () => {
            this.submitting.set(false);
            this.refreshSelectionAfterMutation(eventId);
          },
          error: (error) => {
            this.submitting.set(false);
            this.setSelectedActivityByParticipant(payload.memberId, payload.memberOrigin, slot, previousActividadId);
            this.submitError.set(
              this.resolveApiErrorMessage(error)
              ?? 'No se pudo actualizar la línea de la actividad.',
            );
          },
        });
      return;
    }

    if (existingLine) {
      this.submitting.set(true);
      this.submitError.set(null);
      this.eventosApi
        .actualizarActividadLineaInscripcion(existingLine.lineId, nextActividadId)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: () => {
            this.submitting.set(false);
            this.refreshSelectionAfterMutation(eventId);
          },
          error: (error) => {
            this.submitting.set(false);
            this.setSelectedActivityByParticipant(payload.memberId, payload.memberOrigin, slot, previousActividadId);
            this.submitError.set(
              this.resolveApiErrorMessage(error)
              ?? 'No se pudo actualizar la línea de la actividad.',
            );
          },
        });
      return;
    }

    this.submitting.set(true);
    this.submitError.set(null);
    this.eventosApi
      .crearInscripcion(eventId, {
        usuario: this.buildPersonaReference(payload.memberId, payload.memberOrigin),
        actividad: nextActividadId,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.submitting.set(false);
          this.refreshSelectionAfterMutation(eventId);
        },
        error: (error) => {
          this.submitting.set(false);
          this.setSelectedActivityByParticipant(payload.memberId, payload.memberOrigin, slot, previousActividadId);
          this.submitError.set(
            this.resolveApiErrorMessage(error)
            ?? 'No se pudo registrar la línea de la actividad.',
          );
        },
      });
  }

  protected getSelectedActivity(member: FamilyMember, slot: MealSlot): string | null {
    return this.selectedActivities()[this.participantKey(member.id, member.origin)]?.[slot] ?? null;
  }

  private getSelectedActivityByParticipant(memberId: string, origin: ParticipantOrigin, slot: MealSlot): string | null {
    return this.selectedActivities()[this.participantKey(memberId, origin)]?.[slot] ?? null;
  }

  private setSelectedActivityByParticipant(
    memberId: string,
    origin: ParticipantOrigin,
    slot: MealSlot,
    actividadId: string | null,
  ): void {
    const memberKey = this.participantKey(memberId, origin);
    this.selectedActivities.update((state) => ({
      ...state,
      [memberKey]: { ...(state[memberKey] ?? {}), [slot]: actividadId },
    }));
  }

  protected getActivitiesForSlotAndMember(slot: MealSlot, member: FamilyMember): ActivityOption[] {
    const slotOptions = this.options().filter((o) => o.slot === slot);
    if (!slotOptions.length) return [];

    // Filtrar por compatibilidad: incluir 'ambos' y el tipo específico del miembro
    const compatible = slotOptions.filter(
      (o) => o.compatibility === 'ambos' || o.compatibility === member.personType,
    );

    if (!compatible.length) return [];

    // Resolver precio según tipoPersona y origen
    return compatible.map((opt) => ({
      ...opt,
      price: getActivityPrice(member, opt),
    }));
  }

  protected isSelectorLocked(member: FamilyMember, slot: MealSlot): boolean {
    // Si ya hay una línea pagada para esta persona y franja, no se puede cambiar la actividad.
    const fromSelection = this.preselectedParticipants().find(
      (participant) => participant.id === member.id && participant.origin === member.origin,
    );

    const hasPaidLineInSlot = (fromSelection?.relationLines ?? [])
      .some((line) => line.estadoLinea !== 'cancelada' && line.slot === slot && line.pagada);
    if (hasPaidLineInSlot) {
      return true;
    }

    const fallback = this.selectedInscription();
    if (!fallback) {
      return false;
    }

    return fallback.lineas.some((linea) => {
      if (linea.estadoLinea === 'cancelada') return false;
      if (!linea.pagada) return false;

      const lineSlot = this.normalizeMealSlot(linea.franjaComidaSnapshot);
      if (lineSlot !== slot) return false;

      const participantId = linea.usuarioId ?? linea.invitadoId;
      if (!participantId) return false;

      const origin: ParticipantOrigin = linea.usuarioId ? 'familiar' : 'invitado';
      return participantId === member.id && origin === member.origin;
    });
  }

  protected hasSlotForAnyMember(slot: MealSlot): boolean {
    return this.membersInScope().some((m) => this.getActivitiesForSlotAndMember(slot, m).length > 0);
  }

  protected getCompatibleMembersForSlot(slot: MealSlot): FamilyMember[] {
    return this.membersInScope().filter((member) => this.getActivitiesForSlotAndMember(slot, member).length > 0);
  }

  protected slotLabel(slot: MealSlot): string {
    return slot.charAt(0).toUpperCase() + slot.slice(1);
  }

  protected slotTitle(slot: MealSlot): string {
    const label = this.slotLabel(slot);
    return this.isInfantOnlySlot(slot) ? `${label} infantil` : label;
  }

  protected removeMemberFromActivities(member: FamilyMember): void {
    const key = this.participantKey(member.id, member.origin);
    this.removedMemberIds.update((c) => (c.includes(key) ? c : [...c, key]));
    this.selectedActivities.update((state) => ({ ...state, [key]: {} }));
  }

  protected restoreMemberToActivities(member: FamilyMember): void {
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
    void this.router.navigate(['/eventos', eventId, 'actividades']);
  }

  protected cancelInscriptionLine(lineId: string, inscripcionId: string): void {
    if (!inscripcionId || !this.canModifyExistingInscription() || this.processingLineId()) return;

    this.processingLineId.set(lineId);
    this.existingFlowError.set(null);

    this.eventosApi
      .cancelarLineaInscripcion(inscripcionId, lineId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.loadSeleccionParticipantes(this.eventId());
          this.processingLineId.set(null);
        },
        error: (error) => {
          this.existingFlowError.set(
            this.resolveApiErrorMessage(error)
            ?? 'No se pudo modificar la inscripción. Verificá estado y pagos.',
          );
          this.processingLineId.set(null);
        },
      });
  }

  protected isProcessingLine(lineId: string): boolean {
    return this.processingLineId() === lineId;
  }

  protected canRemoveExistingLine(linea: ExistingInscriptionLineView): boolean {
    return !linea.pagada;
  }

  protected activityCountLabel(count: number): string {
    return buildActivityCountLabel(count);
  }

  protected trackMember(member: FamilyMember): string {
    return this.participantKey(member.id, member.origin);
  }

  // ── Acciones con Observables ──────────────────────────────────────────

  protected confirmSelection(): void {
    const eventId = this.eventId();
    if (!eventId || !this.readyToConfirm() || this.submitting()) return;

    const existingInscriptionId = this.resolveExistingInscriptionId();
    if (!existingInscriptionId) {
      this.submitError.set('Selecciona y guarda al menos una actividad antes de ir al pago.');
      return;
    }

    void this.router.navigate(['/eventos', eventId, 'pago'], {
      state: { inscripcionId: existingInscriptionId, hasNewInscriptions: false },
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
          if (this.shouldFallbackToActividadesEndpoint(evento, id)) {
            this.loadActividadesByEvento(id);
          }
          this.reconcileSelectedActivities();
          this.loading.set(false);
        },
        error: () => {
          this.errorMessage.set('No pudimos cargar el evento. Vuelve a intentar.');
          this.event.set(null);
          this.removedMemberIds.set([]);
          this.options.set([]);
          this.reconcileSelectedActivities();
          this.loading.set(false);
        },
      });
  }

  private loadActividadesByEvento(eventId: string): void {
    this.eventosApi
      .getActividadesByEvento(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (actividades) => {
          this.options.set(this.toActivityOptions(actividades, eventId));
          this.reconcileSelectedActivities();
        },
        error: () => {
          this.options.set([]);
          this.reconcileSelectedActivities();
        },
      });
  }

  private loadSeleccionParticipantes(eventId: string): void {
    this.loadingPeople.set(true);
    this.preselectedParticipants.set([]);
    this.hydratedPreselectionKey.set(null);

    this.eventosApi
      .getSeleccionParticipantesFull(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (result) => {
          const participantes = result.participantes;
          this.preselectedParticipants.set(this.toParticipantReferences(participantes));
          this.loadingPeople.set(false);

          if (Array.isArray(result.inscripciones) && result.inscripciones.length > 0) {
            this.hydratedInscripcionId.set(null);
            this.myInscriptions.set(result.inscripciones);
            this.loadingInscriptions.set(false);
            this.hydrateSelectionsFromExistingInscription();
          } else if (shouldLoadLegacyInscripcionesFallback(participantes)) {
            // Backend didn't include inscripciones snapshot: fall back to legacy collection fetch
            this.loadMyInscriptions();
          } else {
            this.myInscriptions.set([]);
            this.loadingInscriptions.set(false);
          }

          this.reconcileSelectedActivities();
        },
        error: () => {
          this.preselectedParticipants.set([]);
          this.loadingPeople.set(false);
          this.loadMyInscriptions();
          this.reconcileSelectedActivities();
        },
      });
  }

  private loadMyInscriptions(): void {
    this.loadingInscriptions.set(true);

    // this.eventosApi
    //   .getInscripcionesMiasCollection()
    //   .pipe(takeUntilDestroyed(this.destroyRef))
    //   .subscribe({
    //     next: (inscripciones) => {
    //       this.hydratedInscripcionId.set(null);
    //       this.myInscriptions.set(inscripciones);
    //       this.loadingInscriptions.set(false);
    //       this.hydrateSelectionsFromExistingInscription();
    //     },
    //     error: () => {
    //       this.myInscriptions.set([]);
    //       this.loadingInscriptions.set(false);
    //       this.existingFlowError.set('No pudimos cargar tus inscripciones para gestionar actividades.');
    //     },
    //   });
  }

  private refreshSelectionAfterMutation(eventId: string): void {
    this.eventosApi
      .getSeleccionParticipantesFull(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (result) => {
          this.hydratedPreselectionKey.set(null);
          this.preselectedParticipants.set(this.toParticipantReferences(result.participantes));
          // If backend returned inscripciones snapshot, update myInscriptions
          if (Array.isArray(result.inscripciones) && result.inscripciones.length > 0) {
            this.hydratedInscripcionId.set(null);
            this.myInscriptions.set(result.inscripciones);
          }
          this.reconcileSelectedActivities();
        },
      });
  }

  private reconcileSelectedActivities(): void {
    this.selectedActivities.update((state) => {
      const next: Record<string, Partial<Record<MealSlot, string | null>>> = {};
      for (const member of this.membersInScope()) {
        const key = this.participantKey(member.id, member.origin);
        const previous = state[key] ?? {};
        const perSlot: Partial<Record<MealSlot, string | null>> = {};
        for (const slot of this.getAvailableSlotsForMember(member)) {
          const opts = this.getActivitiesForSlotAndMember(slot, member);
          const prev = previous[slot] ?? null;
          perSlot[slot] = prev === null || opts.some((o) => o.id === prev) ? prev : null;
        }
        next[key] = perSlot;
      }
      return next;
    });

    this.hydrateSelectionsFromPreselectedRelations();
    this.hydrateSelectionsFromExistingInscription();
  }

  private hydrateSelectionsFromPreselectedRelations(): void {
    if (!this.isSelectionFlow()) {
      return;
    }

    const references = this.preselectedParticipants();
    if (!references.length) {
      return;
    }

    const hydrationKey = references
      .map((participant) => {
        const lines = (participant.relationLines ?? [])
          .map((line) => `${line.lineId}:${line.actividadId}:${line.slot}:${line.estadoLinea}`)
          .join(',');

        return `${participant.origin}:${participant.id}|${lines}`;
      })
      .join('||');

    if (this.hydratedPreselectionKey() === hydrationKey) {
      return;
    }

    const optionsBySlot = new Map<MealSlot, Set<string>>();
    for (const option of this.options()) {
      const set = optionsBySlot.get(option.slot) ?? new Set<string>();
      set.add(option.id);
      optionsBySlot.set(option.slot, set);
    }

    const validMembers = new Set(this.membersInScope().map((member) => this.participantKey(member.id, member.origin)));
    let hasHydratedAtLeastOneLine = false;

    this.selectedActivities.update((state) => {
      const next = { ...state };

      for (const participant of references) {
        const key = this.participantKey(participant.id, participant.origin);
        if (!validMembers.has(key)) {
          continue;
        }

        const lines = participant.relationLines ?? [];
        for (const line of lines) {
          if (line.estadoLinea === 'cancelada') {
            continue;
          }

          const availableInSlot = optionsBySlot.get(line.slot);
          if (!availableInSlot?.has(line.actividadId)) {
            continue;
          }

          const currentByMember = next[key] ?? {};
          if (currentByMember[line.slot]) {
            continue;
          }

          next[key] = { ...currentByMember, [line.slot]: line.actividadId };
          hasHydratedAtLeastOneLine = true;
        }
      }

      return next;
    });

    if (hasHydratedAtLeastOneLine) {
      this.hydratedPreselectionKey.set(hydrationKey);
    }
  }

  private hydrateSelectionsFromExistingInscription(): void {
    const inscripcion = this.selectedInscription();
    if (!inscripcion) {
      return;
    }

    if (this.hydratedInscripcionId() === inscripcion.id) {
      return;
    }

    const membersInScope = this.membersInScope();
    const optionsBySlot = new Map<MealSlot, Set<string>>();
    for (const option of this.options()) {
      const set = optionsBySlot.get(option.slot) ?? new Set<string>();
      set.add(option.id);
      optionsBySlot.set(option.slot, set);
    }

    if (!membersInScope.length || optionsBySlot.size === 0) {
      return;
    }

    const validMembers = new Set(membersInScope.map((member) => this.participantKey(member.id, member.origin)));
    let hasHydratedAtLeastOneLine = false;

    this.selectedActivities.update((state) => {
      const next = { ...state };

      for (const linea of inscripcion.lineas) {
        if (linea.estadoLinea === 'cancelada') {
          continue;
        }

        const slot = this.normalizeMealSlot(linea.franjaComidaSnapshot);
        const actividadId = linea.actividadId?.trim();
        if (!slot || !actividadId) {
          continue;
        }

        const availableInSlot = optionsBySlot.get(slot);
        if (!availableInSlot?.has(actividadId)) {
          continue;
        }

        const participantId = linea.usuarioId ?? linea.invitadoId;
        if (!participantId) {
          continue;
        }

        const origin: ParticipantOrigin = linea.usuarioId ? 'familiar' : 'invitado';
        const key = this.participantKey(participantId, origin);
        if (!validMembers.has(key)) {
          continue;
        }

        const currentByMember = next[key] ?? {};
        if (currentByMember[slot]) {
          continue;
        }

        next[key] = { ...currentByMember, [slot]: actividadId };
        hasHydratedAtLeastOneLine = true;
      }

      return next;
    });

    if (hasHydratedAtLeastOneLine) {
      this.hydratedInscripcionId.set(inscripcion.id);
    }
  }

  private shouldFallbackToActividadesEndpoint(evento: EventoDetalle, eventId: string): boolean {
    if (shouldUseLegacyActividadesFallback(evento)) {
      return true;
    }

    this.options.set(this.toActivityOptions(evento.actividades, eventId));
    return false;
  }

  private toActivityOptions(actividades: EventoDetalle['actividades'], eventId: string): ActivityOption[] {
    return (actividades ?? [])
      .filter((actividad) => this.actividadBelongsToEvent(actividad, eventId))
      .filter((actividad) => actividad.activo !== false)
      .map((actividad) => this.eventosMapper.toActivityOption(actividad));
  }

  private getAvailableSlotsForMember(member: FamilyMember): MealSlot[] {
    return this.slots.filter((slot) => this.getActivitiesForSlotAndMember(slot, member).length > 0);
  }

  private isInfantOnlySlot(slot: MealSlot): boolean {
    const slotActivities = this.options().filter((actividad) => actividad.slot === slot);
    return slotActivities.length > 0 && slotActivities.every((actividad) => actividad.compatibility === 'infantil');
  }

  private toParticipantReferences(participantes: ParticipanteSeleccion[]): ParticipantReference[] {
    if (!participantes.length) return [];

    const seen = new Set<string>();
    return participantes
      .map<ParticipantReference>((item) => {
        const nombreCompleto = `${item.nombre ?? ''} ${item.apellidos ?? ''}`.trim();

        const actividadesSeleccionadas = (item as any).actividadesSeleccionadas as any[] | undefined;
        const lineasRelacion = item.inscripcionRelacion?.lineas ?? [];

        // Construir source de líneas:
        // Si hay actividadesSeleccionadas, cruzar las inscritas (inscrito===true)
        // con los datos reales de línea de inscripcionRelacion.lineas.
        // Esto evita incluir actividades no inscritas y garantiza tener lineId real.
        let relationLinesSource: any[];

        if (Array.isArray(actividadesSeleccionadas) && actividadesSeleccionadas.length > 0) {
          const inscritasIds = new Set(
            actividadesSeleccionadas
              .filter((act: any) => Boolean(act.inscrito))
              .map((act: any) => String(act.id ?? '').trim()),
          );

          if (inscritasIds.size > 0 && lineasRelacion.length > 0) {
            // Preferir datos reales de la línea del servidor
            relationLinesSource = lineasRelacion.filter((linea: any) =>
              inscritasIds.has(String(linea.actividadId ?? '').trim()),
            );
          } else if (inscritasIds.size > 0) {
            // Fallback: no hay lineas en inscripcionRelacion, construir sintético
            // solo para las actividades realmente inscritas
            relationLinesSource = actividadesSeleccionadas
              .filter((act: any) => Boolean(act.inscrito))
              .map((act: any) => ({
                id: act.lineId ?? act.id ?? '',        // lineId si el back lo manda
                actividadId: act.id ?? '',
                nombreActividadSnapshot: act.nombre ?? '',
                franjaComidaSnapshot: act.franjaComida ?? null,
                estadoLinea: 'pendiente',
                pagada: Boolean(act.pagada ?? false),
                precioUnitario: act.precioUnitario ?? act.price ?? 0,
                usuarioId: item.origen === 'familiar' ? item.id : null,
                invitadoId: item.origen === 'invitado' ? item.id : null,
              }));
          } else {
            // Ninguna actividad inscrita → sin líneas
            relationLinesSource = [];
          }
        } else {
          relationLinesSource = lineasRelacion;
        }

        const relationSummary = item.inscripcionRelacion
          ? {
            id: String(item.inscripcionRelacion.id ?? '').trim(),
            codigo: String(item.inscripcionRelacion.codigo ?? '').trim(),
            estadoPago: String(item.inscripcionRelacion.estadoPago ?? '').trim() || 'pendiente',
            totalLineas: Number(item.inscripcionRelacion.totalLineas ?? 0),
            totalPagado: Number(item.inscripcionRelacion.totalPagado ?? 0),
          }
          : undefined;

        const relationLines = (relationLinesSource ?? [])
          .filter((linea: any) => {
            const usuarioLinea = String((linea as { usuarioId?: string }).usuarioId ?? '').trim();
            const invitadoLinea = String((linea as { invitadoId?: string }).invitadoId ?? '').trim();

            if (!usuarioLinea && !invitadoLinea) return true;

            return item.origen === 'invitado'
              ? invitadoLinea === item.id
              : usuarioLinea === item.id;
          })
          .map((linea: any) => ({
            lineId: String(linea.id ?? linea.lineId ?? '').trim(),
            inscripcionId: String(item.inscripcionRelacion?.id ?? linea.inscripcionId ?? '').trim(),
            actividadId: String(linea.actividadId ?? '').trim(),
            actividadLabel: String(linea.nombreActividadSnapshot ?? linea.nombre ?? '').trim(),
            slot: this.normalizeMealSlot(linea.franjaComidaSnapshot ?? linea.franjaComida ?? undefined),
            estadoLinea: String(linea.estadoLinea ?? '').trim(),
            pagada: Boolean((linea as { pagada?: unknown }).pagada),
            price: Number(linea.precioUnitario ?? linea.price ?? 0),
          }))
          .filter((linea): linea is ParticipantRelationLine =>
            Boolean(linea.lineId && linea.actividadId && linea.slot),
          );

        return {
          id: item.id,
          origin: item.origen === 'invitado' ? 'invitado' : 'familiar',
          name: nombreCompleto || undefined,
          personType: item.tipoPersona === 'infantil' ? 'infantil' : 'adulto',
          enrollment: this.toEnrollmentFromRelacion(item),
          relationSummary,
          relationLines,
        };
      })
      .filter((p) => {
        // Si el evento no permite invitados, filtramos participantes de tipo 'invitado'
        if (p.origin === 'invitado' && !this.canUseGuestParticipants()) return false;

        const key = this.participantKey(p.id, p.origin);
        if (seen.has(key)) return false;
        seen.add(key);
        return p.id.length > 0;
      });
  }

  private toMemberFromSelection(participant: ParticipantReference): FamilyMember {
    const fallbackName = participant.id === this.authService.currentUserId
      ? `${this.authService.userSignal()?.nombre ?? ''} ${this.authService.userSignal()?.apellidos ?? ''}`.trim()
      : '';

    const name = participant.name?.trim() || fallbackName || 'Participante';

    return {
      id: participant.id,
      name,
      role: participant.origin === 'invitado'
        ? 'Invitado'
        : (participant.id === this.authService.currentUserId ? '' : 'Relacionado'),
      personType: participant.personType,
      origin: participant.origin,
      avatarInitial: name.charAt(0).toUpperCase() || 'P',
      enrollment: participant.enrollment,
    };
  }

  private toEnrollmentFromRelacion(participant: ParticipanteSeleccion): FamilyMember['enrollment'] | undefined {
    const inscripcion = participant.inscripcionRelacion;
    if (!inscripcion) return undefined;

    const firstActiveLine = inscripcion.lineas.find((linea) => linea.estadoLinea !== 'cancelada')
      ?? inscripcion.lineas[0];

    const eventTitle = this.event()?.titulo ?? 'Evento';

    return {
      eventId: this.event()?.id,
      eventTitle,
      eventLabel: firstActiveLine
        ? `${firstActiveLine.franjaComidaSnapshot} · ${firstActiveLine.nombreActividadSnapshot}`
        : `${eventTitle} · ${inscripcion.codigo}`,
      paymentStatus: this.eventosMapper.toPaymentBadgeStatus(this.resolveParticipantPaymentStatusFromLines(participant)),
      paymentStatusRaw: this.resolveParticipantPaymentStatusFromLines(participant),
    };
  }

  private resolveParticipantPaymentStatusFromLines(participant: ParticipanteSeleccion): string {
    const relation = participant.inscripcionRelacion;
    if (!relation || !Array.isArray(relation.lineas) || relation.lineas.length === 0) {
      return 'pendiente';
    }

    const activeLines = relation.lineas.filter((linea) => {
      if ((linea.estadoLinea ?? '').trim().toLowerCase() === 'cancelada') {
        return false;
      }

      const usuarioLinea = String((linea as { usuarioId?: string }).usuarioId ?? '').trim();
      const invitadoLinea = String((linea as { invitadoId?: string }).invitadoId ?? '').trim();

      if (!usuarioLinea && !invitadoLinea) {
        return true;
      }

      return participant.origen === 'invitado'
        ? invitadoLinea === participant.id
        : usuarioLinea === participant.id;
    });

    if (!activeLines.length) {
      return 'pendiente';
    }

    const allFree = activeLines.every((linea) => Number(linea.precioUnitario ?? 0) <= 0);
    if (allFree) {
      return 'no_requiere';
    }

    const paidCount = activeLines.filter((linea) => Boolean((linea as { pagada?: unknown }).pagada)).length;
    if (paidCount === 0) {
      return 'pendiente';
    }

    if (paidCount === activeLines.length) {
      return 'pagado';
    }

    return 'parcial';
  }

  private buildRowsFromSelectionContract(): ExistingInscriptionRowView[] {
    const rows: ExistingInscriptionRowView[] = [];

    for (const participant of this.preselectedParticipants()) {
      const relationSummary = participant.relationSummary;
      if (!relationSummary) {
        continue;
      }

      const member = this.toMemberFromSelection(participant);

      const lines = (participant.relationLines ?? [])
        .filter((line) => line.estadoLinea !== 'cancelada')
        .map((line) => {
          const activityOption = this.options().find((option) => option.id === line.actividadId);
          return {
            id: line.lineId,
            inscripcionId: line.inscripcionId,
            actividadLabel: activityOption?.label ?? (line.actividadLabel || line.actividadId),
            slot: line.slot,
            price: line.price > 0 ? line.price : (activityOption?.price ?? 0),
            stateLabel: this.formatLineStatusByPayment(line.estadoLinea, line.pagada),
            pagada: line.pagada,
          } satisfies ExistingInscriptionLineView;
        });

      rows.push({
        key: `${relationSummary.id}:${this.participantKey(participant.id, participant.origin)}`,
        memberName: member.name,
        memberTypeLabel: this.formatPersonTypeLabel(member.personType),
        lines,
      });
    }

    return rows;
  }

  private actividadBelongsToEvent(actividad: NonNullable<EventoDetalle['actividades']>[number], eventId: string): boolean {
    const evento = actividad.evento;

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
    return origin === 'invitado' ? `/api/invitados/${id}` : `/api/usuarios/${id}`;
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

  private formatLineStatusByPayment(state: string, pagada: boolean): string {
    if (pagada) {
      return 'Pagada';
    }

    if (state === 'cancelada') {
      return 'Cancelada';
    }

    if (state === 'lista_espera') {
      return 'Lista de espera';
    }

    return 'Pendiente';
  }

  private normalizeMealSlot(value: string | undefined): MealSlot | null {
    if (value === 'almuerzo' || value === 'comida' || value === 'merienda' || value === 'cena') {
      return value;
    }

    return null;
  }

  private formatPersonTypeLabel(value: string | undefined): string | null {
    if (value === 'adulto') {
      return 'Adulto';
    }

    if (value === 'infantil') {
      return 'Infantil';
    }

    return null;
  }

  private hasActiveActividadesFromEvent(event: EventoDetalle | null): boolean {
    if (!event || !Array.isArray(event.actividades)) {
      return false;
    }

    return event.actividades.some((actividad) => actividad.activo !== false);
  }

  private resolveExistingInscriptionId(): string | null {
    const fromCollection = this.selectedInscription()?.id?.trim();
    if (fromCollection) {
      return fromCollection;
    }

    for (const participant of this.preselectedParticipants()) {
      const id = participant.relationSummary?.id?.trim();
      if (id) {
        return id;
      }
    }

    return null;
  }

  private buildExistingSelectionMap(): Map<string, { inscripcionId: string; lineId: string; actividadId: string }> {
    const keys = new Map<string, { inscripcionId: string; lineId: string; actividadId: string }>();

    for (const participant of this.preselectedParticipants()) {
      for (const line of participant.relationLines ?? []) {
        if (line.estadoLinea === 'cancelada') continue;
        // Ignore client-only/synthetic lines that don't belong to a persisted inscripcion.
        // Those lines can carry actividad ids for client tracking and must not be
        // considered "existing" server-side lines to be deleted/updated.
        if (!line.inscripcionId) continue;
        keys.set(
          this.buildSelectionKey(participant.id, participant.origin, line.slot, line.actividadId),
          { inscripcionId: line.inscripcionId, lineId: line.lineId, actividadId: line.actividadId },
        );
      }
    }

    if (keys.size > 0) {
      return keys;
    }

    const fallback = this.selectedInscription();
    if (!fallback) {
      return keys;
    }

    for (const line of fallback.lineas) {
      if (line.estadoLinea === 'cancelada') continue;

      const slot = this.normalizeMealSlot(line.franjaComidaSnapshot);
      const actividadId = line.actividadId?.trim();
      if (!slot || !actividadId) continue;

      const participantId = line.usuarioId ?? line.invitadoId;
      if (!participantId) continue;

      const origin: ParticipantOrigin = line.usuarioId ? 'familiar' : 'invitado';
      // Si el evento no permite invitados, no consideramos líneas de invitados
      if (origin === 'invitado' && !this.canUseGuestParticipants()) continue;

      keys.set(
        this.buildSelectionKey(participantId, origin, slot, actividadId),
        { inscripcionId: fallback.id, lineId: line.id, actividadId },
      );
    }

    return keys;
  }

  private buildExistingSelectionBySlotMap(
    existing: Map<string, { inscripcionId: string; lineId: string; actividadId: string }>,
  ): Map<string, { inscripcionId: string; lineId: string; actividadId: string }> {
    const bySlot = new Map<string, { inscripcionId: string; lineId: string; actividadId: string }>();

    for (const [selectionKey, line] of existing.entries()) {
      const [participantKey, slot] = selectionKey.split('|');
      if (!participantKey || !slot) continue;
      bySlot.set(`${participantKey}|${slot}`, line);
    }

    return bySlot;
  }

  private buildSelectionSlotKey(
    memberId: string,
    memberOrigin: ParticipantOrigin,
    slot: MealSlot,
  ): string {
    return `${this.participantKey(memberId, memberOrigin)}|${slot}`;
  }

  private resolveApiErrorMessage(error: unknown): string | null {
    const fallbackMessage = 'Error inesperado';

    if (!error || typeof error !== 'object') {
      return null;
    }

    const source = error as {
      message?: unknown;
      error?: {
        detail?: unknown;
        description?: unknown;
        'hydra:description'?: unknown;
        title?: unknown;
        message?: unknown;
      };
    };

    const apiError = source.error;
    const candidates = [
      apiError?.detail,
      apiError?.description,
      apiError?.['hydra:description'],
      apiError?.title,
      apiError?.message,
      source.message,
    ];

    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim().length > 0 && candidate !== fallbackMessage) {
        return candidate.trim();
      }
    }

    return null;
  }

  private buildSelectionKey(
    memberId: string,
    memberOrigin: ParticipantOrigin,
    slot: MealSlot,
    actividadId: string,
  ): string {
    return `${this.participantKey(memberId, memberOrigin)}|${slot}|${actividadId}`;
  }
}


