import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { catchError, concatMap, distinctUntilChanged, filter, finalize, forkJoin, map, of, Subject, tap } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { CtaButton } from '../../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../../shared/components/member-row/member-row';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { EventSummary, FamilyMember } from '../../domain/eventos.models';
import { EventosMapper } from '../../data/eventos.mapper';
import {
  AltaInvitadoPayload,
  EventoDetalleApi,
  EventosApi,
  InscripcionApi,
  ParticipanteSeleccionApi,
} from '../../data/eventos.api';
import { EventosStore } from '../../store/eventos.store';
import { formatLocalDate, formatTime } from '../../../../core/utils/date.utils';

@Component({
  selector: 'app-detalle',
  standalone: true,
  imports: [MobileHeader, MemberRow, CtaButton, ReactiveFormsModule],
  templateUrl: './detalle.html',
  styleUrl: './detalle.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Detalle {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
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
  protected readonly loading              = signal(true);
  protected readonly loadingPeople        = signal(true);
  protected readonly loadingInvitados      = signal(true);
  protected readonly loadingInscritos     = signal(true);
  protected readonly inscritosLoaded      = signal(false);
  protected readonly submittingInvitado    = signal(false);
  protected readonly deletingInvitadoId    = signal<string | null>(null);
  protected readonly errorMessage         = signal<string | null>(null);
  protected readonly invitadosError        = signal<string | null>(null);
  protected readonly inscritosError       = signal<string | null>(null);

  protected readonly event        = signal<EventoDetalleApi | null>(null);
  protected readonly members      = signal<FamilyMember[]>([]);
  protected readonly invitados     = signal<FamilyMember[]>([]);
  protected readonly inscritos    = signal<ParticipanteSeleccionApi[]>([]);
  protected readonly inscripcionExistente = signal<InscripcionApi | null>(null);
  protected readonly selectedMemberIds = signal<string[]>([]);
  protected readonly savingSelection = signal(false);
  protected readonly participantActionsLocked = computed(() =>
    this.savingSelection() || this.deletingInvitadoId() !== null,
  );

  private readonly selectionSaveRequests$ = new Subject<SelectionSaveRequest>();

  // ── Form ──────────────────────────────────────────────────────────────
  protected readonly invitadoForm = this.fb.nonNullable.group({
    nombre:        ['', [Validators.required, Validators.minLength(2)]],
    apellidos:     ['', [Validators.required, Validators.minLength(2)]],
    tipoPersona:   this.fb.nonNullable.control<'adulto' | 'infantil'>('adulto'),
    parentesco:    this.fb.nonNullable.control(
      { value: 'Invitado/a', disabled: true },
      [Validators.required, Validators.minLength(2)],
    ),
    observaciones: [''],
  });

  // ── Derivados ─────────────────────────────────────────────────────────
  protected readonly usuarioLogado = computed<FamilyMember | null>(() => {
    const user = this.authService.userSignal(); // ← llama al signal, no a getUser()
    if (!user) return null;

    const nombre = user.nombre ?? '';
    const apellidos = user.apellidos ?? '';
    const email = typeof user.email === 'string' ? user.email : '';

    return {
      id: String(user.id ?? ''),
      name: `${nombre} ${apellidos}`.trim() || email,
      role: 'Titular',
      personType: 'adulto' as const,
      origin: 'familiar' as const,
      avatarInitial: (nombre.charAt(0) || email.charAt(0)).toUpperCase(),
    };
  });

  protected readonly invitadosRows = computed<FamilyMember[]>(() => this.invitados());

  protected readonly inscritosRows = computed<FamilyMember[]>(() => {
    if (this.inscritos().length === 0 && !this.inscritosLoaded()) {
      return this.mapInscripcionExistenteRows(this.inscripcionExistente());
    }

    const eventSummary = this.eventSummary();
    const rows: FamilyMember[] = [];

    for (const inscrito of this.inscritos()) {
      if (inscrito.origen === 'invitado' && !this.guestManagementEnabled()) {
        continue;
      }

      const activeLines = this.getActiveParticipantLines(inscrito);
      if (inscrito.inscripcionRelacion && activeLines.length === 0) {
        // Si solo quedan líneas canceladas, el participante ya no debe figurar como inscrito.
        continue;
      }

      const key = this.inscritoKey(inscrito);
      const knownMember = this.participantsLookup().get(key);
      const fullName = [inscrito.nombre?.trim() ?? '', inscrito.apellidos?.trim() ?? '']
        .filter(Boolean)
        .join(' ')
        .trim();
      const fallbackLabel = inscrito.origen === 'invitado' ? 'Invitado' : 'Familiar';
      const name = fullName || knownMember?.name || `${fallbackLabel} ${inscrito.id}`;
      const rawPaymentStatus = this.resolveInscritoPaymentStatus(inscrito, activeLines);
      const menuNames = activeLines
        .map((linea) => linea.nombreMenuSnapshot.trim())
        .filter(Boolean)
        .join(', ');

      rows.push({
        id: String(inscrito.id),
        name,
        role: inscrito.origen === 'invitado' ? 'Invitado' : (knownMember?.role ?? 'Familiar'),
        personType: knownMember?.personType ?? 'adulto',
        origin: inscrito.origen,
        avatarInitial: name.charAt(0).toUpperCase() || '?',
        notes: menuNames ? `Actividades: ${menuNames}` : undefined,
        enrollment: eventSummary
          ? {
            eventId: eventSummary.id,
            eventTitle: eventSummary.title,
            eventLabel: eventSummary.time === 'Sin hora'
              ? `${eventSummary.title} · ${eventSummary.date}`
              : `${eventSummary.title} · ${eventSummary.date} ${eventSummary.time}`,
            paymentStatus: this.eventosMapper.toPaymentBadgeStatus(rawPaymentStatus),
            paymentStatusRaw: rawPaymentStatus,
          }
          : undefined,
      });
    }

    return rows;
  });

  protected readonly inscritosSummary = computed(() => {
    const count = this.inscritosRows().length;
    if (count === 0) return 'Todavía no guardaste participantes para este evento.';
    if (count === 1) return 'Tenés 1 persona apuntada en este evento.';
    return `Tenés ${count} personas apuntadas en este evento.`;
  });

  protected readonly participantsLookup = computed(() => {
    const lookup = new Map<string, FamilyMember>();

    for (const participant of this.participants()) {
      lookup.set(this.participantKey(participant), participant);
    }

    if (this.guestManagementEnabled()) {
      for (const invitado of this.invitadosRows()) {
        lookup.set(this.participantKey(invitado), invitado);
      }
    }

    return lookup;
  });

  protected readonly participants = computed<FamilyMember[]>(() => {
    const titular = this.usuarioLogado();
    return uniqueParticipantsByKey([
      ...(titular ? [titular] : []),
      ...this.members(),
      ...(this.guestManagementEnabled() ? this.invitadosRows() : []),
    ]);
  });

  protected readonly eventSummary = computed<EventSummary | null>(() => {
    const event = this.event();
    if (!event) return null;

    const status: EventSummary['status'] = event.inscripcionAbierta === true ? 'abierto' : 'cerrado';

    return {
      id: event.id,
      title: event.titulo,
      date: formatLocalDate(event.fechaEvento),
      time: formatTime(event.horaInicio),
      location: event.lugar ?? 'Lugar por confirmar',
      status,
      description: event.descripcion ?? 'Sin descripción disponible.',
    };
  });

  protected readonly eventStatusLabel = computed(() => {
    const labels: Record<string, string> = {
      abierto: 'Abierto',
      cerrado: 'Cerrado',
    };
    return labels[this.eventSummary()?.status ?? ''] ?? 'Cerrado';
  });

  protected readonly inscripcionCerrada = computed(() => this.event()?.inscripcionAbierta === false);
  protected readonly hasActiveMenus = computed(() => this.hasActiveMenusFromEvent(this.event()));
  protected readonly guestManagementEnabled = computed(() => this.canUseGuestParticipantsFromEvent(this.event()));
  protected readonly canManageParticipants = computed(() =>
    Boolean(this.event()?.inscripcionAbierta === true && this.hasActiveMenus()),
  );
  protected readonly activeMenuCompatibilityScope = computed(() => {
    const event = this.event();
    if (!event || !Array.isArray(event.menus)) {
      return { allowsAdult: false, allowsInfantil: false };
    }

    const activeMenus = event.menus.filter((menu) => menu.activo !== false);
    if (!activeMenus.length) {
      return { allowsAdult: false, allowsInfantil: false };
    }

    let allowsAdult = false;
    let allowsInfantil = false;

    for (const menu of activeMenus) {
      if (menu.compatibilidadPersona === 'ambos') {
        allowsAdult = true;
        allowsInfantil = true;
        continue;
      }

      if (menu.compatibilidadPersona === 'adulto') {
        allowsAdult = true;
      }

      if (menu.compatibilidadPersona === 'infantil') {
        allowsInfantil = true;
      }
    }

    return { allowsAdult, allowsInfantil };
  });

  protected readonly guestTypeInfantilOnly = computed(() => {
    const scope = this.activeMenuCompatibilityScope();
    return this.guestManagementEnabled() && scope.allowsInfantil && !scope.allowsAdult;
  });

  protected readonly guestTypeAdultOnly = computed(() => {
    const scope = this.activeMenuCompatibilityScope();
    return this.guestManagementEnabled() && scope.allowsAdult && !scope.allowsInfantil;
  });

  protected readonly participantsCompatibilityMessage = computed(() => {
    const scope = this.activeMenuCompatibilityScope();
    if (scope.allowsAdult && scope.allowsInfantil) {
      return null;
    }

    if (scope.allowsInfantil) {
      return 'Este evento solo tiene actividades infantiles: se muestran únicamente perfiles infantiles.';
    }

    if (scope.allowsAdult) {
      return 'Este evento solo tiene actividades para adultos: se muestran únicamente perfiles adultos.';
    }

    return null;
  });

  protected readonly emptySelectionMessage = computed(() => {
    const scope = this.activeMenuCompatibilityScope();
    if (scope.allowsInfantil && !scope.allowsAdult) {
      return 'No hay perfiles infantiles disponibles para este evento.';
    }

    if (scope.allowsAdult && !scope.allowsInfantil) {
      return 'No hay perfiles adultos disponibles para este evento.';
    }

    return 'No tienes personas disponibles para este evento.';
  });

  protected readonly guestTypeRestrictionMessage = computed(() => {
    if (this.guestTypeInfantilOnly()) {
      return 'Este evento solo permite crear invitados infantiles.';
    }

    if (this.guestTypeAdultOnly()) {
      return 'Este evento solo permite crear invitados adultos.';
    }

    return null;
  });

  protected readonly participantsForSelection = computed<FamilyMember[]>(() => {
    const allParticipants = this.participants();
    const scope = this.activeMenuCompatibilityScope();
    if (scope.allowsAdult && scope.allowsInfantil) {
      return allParticipants;
    }

    if (scope.allowsInfantil) {
      return allParticipants.filter((member) => member.personType === 'infantil');
    }

    if (scope.allowsAdult) {
      return allParticipants.filter((member) => member.personType === 'adulto');
    }

    return [];
  });

  protected readonly selectedMemberIdsInScope = computed(() => {
    const allowed = new Set(this.participantsForSelection().map((member) => this.participantKey(member)));
    return this.selectedMemberIds().filter((id) => allowed.has(id));
  });

  protected readonly selectedCountLabel = computed(() => {
    const count = this.selectedMemberIdsInScope().length;
    if (count === 0) return 'Todavía no seleccionaste personas para inscribir.';
    if (count === 1) return '1 persona seleccionada para pasar a actividades.';
    return `${count} personas seleccionadas para pasar a actividades.`;
  });

  protected readonly canContinueToActivities = computed(() =>
    Boolean(this.canManageParticipants() && this.selectedMemberIdsInScope().length > 0),
  );

  protected readonly activitiesButtonLabel = computed(() => {
    if (!this.hasActiveMenus()) {
      return 'Este evento no tiene comidas';
    }

    const count = this.selectedMemberIdsInScope().length;
    return count === 0
      ? 'Seleccioná al menos una persona'
      : `Seleccionar actividades (${count})`;
  });

  constructor() {
    this.selectionSaveRequests$
      .pipe(
        distinctUntilChanged((previous, current) => sameSelectionRequest(previous, current)),
        concatMap((request) => {
          this.errorMessage.set(null);
          this.inscritosError.set(null);
          this.savingSelection.set(true);

          const participantes = this.toParticipantesSeleccion(request.selectedKeys);

          return this.cancelRemovedParticipantLines(request.selectedKeys)
            .pipe(
              concatMap(() => this.eventosApi.guardarSeleccionParticipantes(request.eventId, participantes)),
              tap((saved) => {
                this.inscritos.set(saved);
                this.selectedMemberIds.set(toSelectedMemberKeys(saved));
              }),
              catchError((error: unknown) => {
                this.errorMessage.set(this.resolveSelectionErrorMessage(error));
                return of([] as ParticipanteSeleccionApi[]);
              }),
              finalize(() => {
                this.savingSelection.set(false);
              }),
            );
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe();

    this.eventId$
      .pipe(
        tap((id) => this.loadEvent(id)),
        tap(() => this.loadPersonasMias()),
        tap((id) => this.loadInvitados(id)),
        tap((id) => this.loadInscritos(id)),
        tap((id) => this.loadInscripcionExistente(id)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe();
  }

  // ── Handlers de UI ────────────────────────────────────────────────────

  protected participantTrackKey(member: FamilyMember): string {
    return this.participantKey(member);
  }

  protected inscritoTrackKey(member: FamilyMember): string {
    return `${member.origin}:${member.id}`;
  }

  protected isMemberSelected(member: FamilyMember): boolean {
    return this.selectedMemberIds().includes(this.participantKey(member));
  }

  protected isAlreadyEnrolled(member: FamilyMember): boolean {
    return member.enrollment?.eventId === this.eventSummary()?.id;
  }

  protected participantActionLabel(member: FamilyMember): string {
    if (this.inscripcionCerrada()) {
      return '';
    }

    if (this.isPaidEnrollmentLocked(member)) {
      return '';
    }

    if (member.origin === 'invitado') {
      return this.isMemberSelected(member) ? 'Quitar' : 'Inscribir';
    }

    if (this.isAlreadyEnrolled(member) && !this.isMemberSelected(member)) return 'Eliminar';
    return this.isMemberSelected(member) ? 'Quitar' : 'Inscribir';
  }

  protected participantSecondaryActionLabel(member: FamilyMember): string {
    if (this.inscripcionCerrada()) {
      return '';
    }

    if (member.origin !== 'invitado') {
      return '';
    }

    if (this.isPaidEnrollmentLocked(member)) {
      return '';
    }

    return this.isDeletingInvitado(member.id) ? 'Borrando...' : 'Borrar';
  }

  protected isParticipantActionDisabled(member: FamilyMember): boolean {
    if (this.participantActionsLocked()) {
      return true;
    }

    if (this.inscripcionCerrada()) {
      return true;
    }

    if (this.isPaidEnrollmentLocked(member)) {
      return true;
    }

    return member.origin === 'invitado' && this.isDeletingInvitado(member.id);
  }

  protected toggleMemberSelection(member: FamilyMember): void {
    if (this.inscripcionCerrada()) {
      return;
    }

    if (this.isPaidEnrollmentLocked(member)) {
      return;
    }

    if (member.origin === 'invitado' && !this.guestManagementEnabled()) {
      return;
    }

    const key = this.participantKey(member);
    this.selectedMemberIds.update((current) =>
      current.includes(key) ? current.filter((id) => id !== key) : [...current, key],
    );
    this.persistSelectedParticipants();
  }

  protected handleParticipantSecondaryAction(memberId: string): void {
    this.removeInvitado(memberId);
  }

  protected goBack(): void {
    void this.router.navigate(['/eventos/inicio']);
  }

  protected openActivities(): void {
    const selected = this.selectedMemberIdsInScope();
    const eventId = this.eventId();
    if (!selected.length || !eventId || !this.canManageParticipants()) return;

    void this.router.navigate(['/eventos', eventId, 'actividades']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected isDeletingInvitado(memberId: string): boolean {
    return this.deletingInvitadoId() === memberId;
  }

  // ── Acciones con Observables ──────────────────────────────────────────

  protected submitInvitado(): void {
    if (this.inscripcionCerrada()) {
      return;
    }

    if (!this.guestManagementEnabled()) {
      return;
    }

    if (this.invitadoForm.invalid || this.submittingInvitado()) {
      this.invitadoForm.markAllAsTouched();
      return;
    }

    if (this.guestTypeInfantilOnly() && this.invitadoForm.controls.tipoPersona.value !== 'infantil') {
      this.invitadoForm.controls.tipoPersona.setValue('infantil');
    }

    const eventId = this.eventId();
    if (!eventId) return;

    this.invitadosError.set(null);
    this.submittingInvitado.set(true);

    const value = this.invitadoForm.getRawValue();
    const payload: AltaInvitadoPayload = {
      nombre:        value.nombre.trim(),
      apellidos:     value.apellidos.trim(),
      tipoPersona:   this.guestTypeInfantilOnly()
        ? 'infantil'
        : (this.guestTypeAdultOnly() ? 'adulto' : value.tipoPersona),
      parentesco:    'Invitado/a',
      observaciones: value.observaciones.trim(),
    };

    this.eventosStore
      .altaInvitadoEnEvento(eventId, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (invitadoCreado) => {
          const invitado = this.eventosMapper.toFamilyMember(invitadoCreado);
          const invitadoKey = this.participantKey(invitado);

          this.invitados.update((current) => uniqueParticipantsByKey([...current, invitado]));
          this.selectedMemberIds.update((current) =>
            current.includes(invitadoKey) ? current : [...current, invitadoKey],
          );
          this.persistSelectedParticipants();
          this.invitadoForm.patchValue({
            nombre: '',
            apellidos: '',
            tipoPersona: this.guestTypeInfantilOnly() ? 'infantil' : 'adulto',
            parentesco: 'Invitado/a', observaciones: '',
          });
          this.syncInvitadoTipoPersonaControl();
          this.invitadoForm.markAsPristine();
          this.invitadoForm.markAsUntouched();
          this.submittingInvitado.set(false);
        },
        error: (error: unknown) => {
          this.invitadosError.set(this.resolveAltaInvitadoErrorMessage(error));
          this.submittingInvitado.set(false);
        },
      });
  }

  protected removeInvitado(memberId: string): void {
    if (this.inscripcionCerrada()) {
      return;
    }

    if (!this.guestManagementEnabled()) {
      return;
    }

    const eventId = this.eventId();
    if (!eventId || this.deletingInvitadoId()) return;

    this.invitadosError.set(null);
    this.deletingInvitadoId.set(memberId);

    this.eventosStore
      .bajaInvitadoEnEvento(eventId, memberId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          const normalizedMemberId = normalizeParticipantId(memberId);
          this.selectedMemberIds.update((current) =>
            current.filter((id) => id !== `invitado:${normalizedMemberId}`),
          );
          this.persistSelectedParticipants();
          this.loadInvitados(eventId);
          this.deletingInvitadoId.set(null);
        },
        error: () => {
          this.invitadosError.set('No se pudo dar de baja al invitado seleccionado.');
          this.deletingInvitadoId.set(null);
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
        next: (event) => {
          this.event.set(event);
          this.syncInvitadoTipoPersonaControl();

          if (!this.canUseGuestParticipantsFromEvent(event)) {
            this.invitados.set([]);
            this.selectedMemberIds.update((current) => current.filter((id) => !id.startsWith('invitado:')));
          }

          this.loading.set(false);
        },
        error: () => {
          this.errorMessage.set('No pudimos cargar el detalle del evento. Volvé a intentar.');
          this.event.set(null);
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
          this.members.set(members);
          this.loadingPeople.set(false);
        },
        error: () => {
          this.errorMessage.set('No pudimos cargar tus familiares para la inscripción.');
          this.members.set([]);
          this.loadingPeople.set(false);
        },
      });
  }

  private loadInvitados(eventId: string): void {

    this.loadingInvitados.set(true);

    this.eventosStore
      .getInvitadosByEvento(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (invitados) => {
          this.invitados.set(invitados.map((p) => this.eventosMapper.toFamilyMember(p)));
          this.loadingInvitados.set(false);
        },
        error: () => {
          this.invitadosError.set('No pudimos cargar los invitados para este evento.');
          this.invitados.set([]);
          this.loadingInvitados.set(false);
        },
      });
  }

  private loadInscritos(eventId: string): void {
    this.loadingInscritos.set(true);
    this.inscritosLoaded.set(false);
    this.inscritosError.set(null);
    this.selectedMemberIds.set([]);

    this.eventosStore
      .getSeleccionParticipantes(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (inscritos) => {
          this.inscritos.set(inscritos);
          this.selectedMemberIds.set(toSelectedMemberKeys(inscritos));
          this.inscritosLoaded.set(true);
          this.loadingInscritos.set(false);
        },
        error: () => {
          this.inscritos.set([]);
          this.selectedMemberIds.set([]);
          this.inscritosError.set('No pudimos cargar a quiénes ya apuntaste en este evento.');
          this.inscritosLoaded.set(true);
          this.loadingInscritos.set(false);
        },
      });
  }

  private loadInscripcionExistente(eventId: string): void {
    this.inscripcionExistente.set(null);

    this.eventosApi
      .getInscripcionesMiasCollection()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (inscripciones) => {
          this.inscripcionExistente.set(
            inscripciones.find((inscripcion) => inscripcion.evento.id === eventId) ?? null,
          );
        },
        error: () => {
          this.inscripcionExistente.set(null);
        },
      });
  }

  private mapInscripcionExistenteRows(inscripcion: InscripcionApi | null): FamilyMember[] {
    if (!inscripcion) {
      return [];
    }

    const rows = new Map<string, FamilyMember>();

    for (const linea of inscripcion.lineas) {
      if (linea.estadoLinea === 'cancelada') {
        continue;
      }

      const origin: 'familiar' | 'invitado' = linea.invitadoId ? 'invitado' : 'familiar';
      if (origin === 'invitado' && !this.guestManagementEnabled()) {
        continue;
      }

      const participantId = (linea.invitadoId ?? linea.usuarioId ?? linea.id).trim();
      const key = `${origin}:${participantId}`;
      const current = rows.get(key);

      if (!current) {
        rows.set(key, {
          id: participantId,
          name: linea.nombrePersonaSnapshot.trim() || 'Participante',
          role: origin === 'invitado' ? 'Invitado' : 'Familiar',
          personType: linea.tipoPersonaSnapshot === 'infantil' ? 'infantil' : 'adulto',
          origin,
          avatarInitial: (linea.nombrePersonaSnapshot.trim().charAt(0) || '?').toUpperCase(),
          notes: linea.nombreMenuSnapshot.trim()
            ? `Actividades: ${linea.nombreMenuSnapshot.trim()}`
            : undefined,
        });
        continue;
      }

      const nextMenu = linea.nombreMenuSnapshot.trim();
      if (!nextMenu) {
        continue;
      }

      const existingNote = current.notes ?? '';
      const prefix = 'Actividades: ';
      const existingMenus = existingNote.startsWith(prefix)
        ? existingNote.slice(prefix.length).split(',').map((item) => item.trim()).filter(Boolean)
        : [];

      if (!existingMenus.includes(nextMenu)) {
        current.notes = `${prefix}${[...existingMenus, nextMenu].join(', ')}`;
      }
    }

    return [...rows.values()];
  }

  private participantKey(member: FamilyMember): string {
    const normalizedId = normalizeParticipantId(member.id);
    return `${member.origin === 'invitado' ? 'invitado' : 'familiar'}:${normalizedId}`;
  }

  private inscritoKey(inscrito: ParticipanteSeleccionApi): string {
    return `${inscrito.origen}:${inscrito.id}`;
  }

  private toParticipantesSeleccion(selectedKeys: string[]): ParticipanteSeleccionApi[] {
    const participantes: ParticipanteSeleccionApi[] = [];
    const seen = new Set<string>();

    for (const key of selectedKeys) {
      const [originRaw = '', ...idParts] = key.split(':');
      const rawId = idParts.length > 0 ? idParts.join(':').trim() : originRaw.trim();
      const id = normalizeParticipantId(rawId);
      if (!id) continue;

      const origen: 'familiar' | 'invitado' =
        originRaw === 'invitado' ? 'invitado' : 'familiar';
      if (origen === 'invitado' && !this.guestManagementEnabled()) continue;

      const uniqueKey = `${origen}:${id}`;
      if (seen.has(uniqueKey)) continue;

      seen.add(uniqueKey);
      participantes.push({ id, origen });
    }

    return participantes;
  }

  private persistSelectedParticipants(): void {
    const eventId = this.eventId();
    if (!eventId || !this.canManageParticipants()) {
      return;
    }

    const selectedKeys = this.selectedMemberIdsInScope();
    if (selectedKeys.length !== this.selectedMemberIds().length) {
      this.selectedMemberIds.set(selectedKeys);
    }

    this.selectionSaveRequests$.next({
      eventId,
      selectedKeys: [...selectedKeys],
      });
  }

  private cancelRemovedParticipantLines(selectedKeys: string[]) {
    const selectedSet = new Set(selectedKeys);
    const cancellableLines: Array<{ inscripcionId: string; lineId: string }> = [];

    for (const inscrito of this.inscritos()) {
      const participantKey = this.inscritoKey(inscrito);
      if (selectedSet.has(participantKey)) {
        continue;
      }

      const relation = inscrito.inscripcionRelacion;
      if (!relation || !Array.isArray(relation.lineas)) {
        continue;
      }

      for (const linea of relation.lineas) {
        if (!linea.id || linea.estadoLinea === 'cancelada' || Boolean((linea as { pagada?: unknown }).pagada)) {
          continue;
        }

        if (!this.lineBelongsToParticipant(inscrito, linea.usuarioId, linea.invitadoId)) {
          continue;
        }

        cancellableLines.push({
          inscripcionId: relation.id,
          lineId: linea.id,
        });
      }
    }

    if (!cancellableLines.length) {
      return of(void 0);
    }

    return forkJoin(
      cancellableLines.map((line) => this.eventosApi.cancelarLineaInscripcion(line.inscripcionId, line.lineId)),
    ).pipe(map(() => void 0));
  }

  private lineBelongsToParticipant(
    inscrito: ParticipanteSeleccionApi,
    usuarioId?: string,
    invitadoId?: string,
  ): boolean {
    const participantId = normalizeParticipantId(inscrito.id);

    if (!usuarioId && !invitadoId) {
      return true;
    }

    if (inscrito.origen === 'invitado') {
      return normalizeParticipantId(invitadoId) === participantId;
    }

    return normalizeParticipantId(usuarioId) === participantId;
  }

  private resolveSelectionErrorMessage(error: unknown): string {
    const fallbackMessage = 'No pudimos guardar la selección de participantes. Probá nuevamente.';

    if (!(error instanceof HttpErrorResponse)) {
      return fallbackMessage;
    }

    const source = this.toRecord(error.error);
    const detail = this.readString(source?.['detail'])
      ?? this.readString(source?.['description'])
      ?? this.readString(source?.['hydra:description'])
      ?? this.readString(source?.['title'])
      ?? this.readString(source?.['message'])
      ?? this.readString(error.message);

    return detail ?? fallbackMessage;
  }

  private resolveInscritoPaymentStatus(
    inscrito: ParticipanteSeleccionApi,
    activeLinesFromCaller?: Array<NonNullable<ParticipanteSeleccionApi['inscripcionRelacion']>['lineas'][number]>,
  ): string {
    const relation = inscrito.inscripcionRelacion;
    if (!relation || !Array.isArray(relation.lineas)) {
      return 'pendiente';
    }

    const activeLines = activeLinesFromCaller ?? this.getActiveParticipantLines(inscrito);

    if (!activeLines.length) {
      return 'pendiente';
    }

    if (activeLines.every((linea) => Number(linea.precioUnitario ?? 0) <= 0)) {
      return 'no_requiere';
    }

    const paidLines = activeLines.filter((linea) => this.isLinePaid(linea));
    if (!paidLines.length) {
      return 'pendiente';
    }

    if (paidLines.length === activeLines.length) {
      return 'pagado';
    }

    return 'parcial';
  }

  private isPaidEnrollmentLocked(member: FamilyMember): boolean {
    const hasPaidLines = this.findInscritoHasPaidLines(member);
    if (hasPaidLines) {
      return true;
    }

    return false;
  }

  private findInscritoHasPaidLines(member: FamilyMember): boolean {
    const key = this.participantKey(member);
    const inscrito = this.inscritos().find((item) => this.inscritoKey(item) === key);

    if (!inscrito?.inscripcionRelacion?.lineas) {
      return false;
    }

    for (const linea of inscrito.inscripcionRelacion.lineas) {
      if (linea.estadoLinea === 'cancelada') {
        continue;
      }

      if (!this.lineBelongsToParticipant(inscrito, linea.usuarioId, linea.invitadoId)) {
        continue;
      }

      if (this.isLinePaid(linea)) {
        return true;
      }
    }

    return false;
  }

  private isLinePaid(linea: { estadoLinea?: string; pagada?: unknown }): boolean {
    return Boolean(linea.pagada);
  }

  private getActiveParticipantLines(
    inscrito: ParticipanteSeleccionApi,
  ): Array<NonNullable<ParticipanteSeleccionApi['inscripcionRelacion']>['lineas'][number]> {
    const relation = inscrito.inscripcionRelacion;
    if (!relation || !Array.isArray(relation.lineas)) {
      return [];
    }

    return relation.lineas.filter((linea) =>
      linea.estadoLinea !== 'cancelada'
      && this.lineBelongsToParticipant(inscrito, linea.usuarioId, linea.invitadoId),
    );
  }

  private resolveAltaInvitadoErrorMessage(error: unknown): string {
    const fallbackMessage = 'No pudimos dar de alta al invitado. Probá nuevamente.';

    if (!(error instanceof HttpErrorResponse)) {
      return fallbackMessage;
    }

    if (error.status === 422) {
      const apiError = this.toRecord(error.error);
      const violationMessage = this.readViolationMessage(apiError);
      const detailMessage = this.readString(apiError?.['detail'])
        ?? this.readString(apiError?.['hydra:description'])
        ?? this.readString(apiError?.['message'])
        ?? this.readString(error.message);

      return violationMessage ?? detailMessage ?? 'Ya existe un invitado activo con ese nombre completo para este evento.';
    }

    return fallbackMessage;
  }

  private toRecord(value: unknown): Record<string, unknown> | null {
    return typeof value === 'object' && value !== null ? (value as Record<string, unknown>) : null;
  }

  private readViolationMessage(source: Record<string, unknown> | null): string | null {
    const violations = source?.['violations'];
    if (!Array.isArray(violations) || violations.length === 0) {
      return null;
    }

    const firstViolation = violations[0];
    if (typeof firstViolation !== 'object' || firstViolation === null) {
      return null;
    }

    return this.readString((firstViolation as Record<string, unknown>)['message']) ?? null;
  }

  private readString(value: unknown): string | null {
    if (typeof value !== 'string') {
      return null;
    }

    const cleaned = value.trim();
    return cleaned.length > 0 ? cleaned : null;
  }

  private hasActiveMenusFromEvent(event: EventoDetalleApi | null): boolean {
    if (!event || !Array.isArray(event.menus)) {
      return false;
    }

    return event.menus.some((menu) => menu.activo !== false);
  }

  private canUseGuestParticipantsFromEvent(event: EventoDetalleApi | null): boolean {
    return Boolean(event?.permiteInvitados && this.hasActiveMenusFromEvent(event));
  }

  private syncInvitadoTipoPersonaControl(): void {
    const tipoPersonaControl = this.invitadoForm.controls.tipoPersona;

    if (this.guestTypeInfantilOnly()) {
      tipoPersonaControl.setValue('infantil');
      tipoPersonaControl.disable({ emitEvent: false });
      return;
    }

    if (this.guestTypeAdultOnly()) {
      tipoPersonaControl.setValue('adulto');
      tipoPersonaControl.disable({ emitEvent: false });
      return;
    }

    tipoPersonaControl.enable({ emitEvent: false });
  }
}

interface SelectionSaveRequest {
  eventId: string;
  selectedKeys: string[];
}

export function toSelectedMemberKeys(participantes: ParticipanteSeleccionApi[]): string[] {
  if (!participantes.length) return [];

  const selected: string[] = [];
  const seen = new Set<string>();

  for (const participante of participantes) {
    const id = normalizeParticipantId(participante.id);
    if (!id) continue;

    if (hasOnlyCancelledParticipantLines(participante)) {
      continue;
    }

    const origin = participante.origen === 'invitado' ? 'invitado' : 'familiar';
    const key = `${origin}:${id}`;

    if (seen.has(key)) continue;
    seen.add(key);
    selected.push(key);
  }

  return selected;
}

function hasOnlyCancelledParticipantLines(participante: ParticipanteSeleccionApi): boolean {
  const relation = participante.inscripcionRelacion;
  if (!relation || !Array.isArray(relation.lineas) || relation.lineas.length === 0) {
    return false;
  }

  const participantId = normalizeParticipantId(participante.id);

  const hasActiveOwnLine = relation.lineas.some((linea) => {
    if (linea.estadoLinea === 'cancelada') {
      return false;
    }

    if (!linea.usuarioId && !linea.invitadoId) {
      return true;
    }

    if (participante.origen === 'invitado') {
      return normalizeParticipantId(linea.invitadoId) === participantId;
    }

    return normalizeParticipantId(linea.usuarioId) === participantId;
  });

  return !hasActiveOwnLine;
}

function normalizeParticipantId(rawId: string | null | undefined): string {
  if (!rawId) {
    return '';
  }

  const cleaned = rawId.trim();
  if (!cleaned) {
    return '';
  }

  if (!cleaned.includes('/')) {
    return cleaned;
  }

  return cleaned.split('/').filter(Boolean).at(-1) ?? '';
}

function sameSelectionRequest(previous: SelectionSaveRequest, current: SelectionSaveRequest): boolean {
  if (previous.eventId !== current.eventId) {
    return false;
  }

  if (previous.selectedKeys.length !== current.selectedKeys.length) {
    return false;
  }

  return previous.selectedKeys.every((key, index) => key === current.selectedKeys[index]);
}

export function uniqueParticipantsByKey(participants: FamilyMember[]): FamilyMember[] {
  if (participants.length <= 1) {
    return participants;
  }

  const unique = new Map<string, FamilyMember>();

  for (const participant of participants) {
    const id = normalizeParticipantId(participant.id);
    if (!id) {
      continue;
    }

    const origin = participant.origin === 'invitado' ? 'invitado' : 'familiar';
    const key = `${origin}:${id}`;

    if (!unique.has(key)) {
      unique.set(key, {
        ...participant,
        id,
        origin,
      });
    }
  }

  return Array.from(unique.values());
}
