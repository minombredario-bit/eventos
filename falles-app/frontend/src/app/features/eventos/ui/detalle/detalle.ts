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
import { catchError, concatMap, distinctUntilChanged, filter, finalize, map, of, Subject, tap } from 'rxjs';
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
  ParticipanteSeleccionApi,
  RelacionUsuarioApi,
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
  protected readonly loadingRelaciones    = signal(false);
  protected readonly loadingInscritos     = signal(true);
  protected readonly submittingInvitado    = signal(false);
  protected readonly deletingInvitadoId    = signal<string | null>(null);
  protected readonly deletingInscritoInvitadoId = signal<string | null>(null);
  protected readonly errorMessage         = signal<string | null>(null);
  protected readonly invitadosError        = signal<string | null>(null);
  protected readonly relacionesError      = signal<string | null>(null);
  protected readonly inscritosError       = signal<string | null>(null);

  protected readonly event        = signal<EventoDetalleApi | null>(null);
  protected readonly members      = signal<FamilyMember[]>([]);
  protected readonly invitados     = signal<FamilyMember[]>([]);
  protected readonly relaciones   = signal<RelacionUsuarioApi[]>([]);
  protected readonly inscritos    = signal<ParticipanteSeleccionApi[]>([]);
  protected readonly selectedMemberIds = signal<string[]>([]);
  protected readonly savingSelection = signal(false);

  private readonly selectionSaveRequests$ = new Subject<SelectionSaveRequest>();

  // ── Form ──────────────────────────────────────────────────────────────
  protected readonly invitadoForm = this.fb.nonNullable.group({
    nombre:        ['', [Validators.required, Validators.minLength(2)]],
    apellidos:     ['', [Validators.required, Validators.minLength(2)]],
    tipoPersona:   this.fb.nonNullable.control<'adulto' | 'infantil'>('adulto'),
    parentesco:    ['Invitado/a', [Validators.required, Validators.minLength(2)]],
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

  protected readonly relacionesComoMiembros = computed<FamilyMember[]>(() => {
    const myId = this.authService.currentUserId;
    return this.relaciones().map((relacion) => {
      const relacionado = relacion.usuarioOrigen.id === myId
        ? relacion.usuarioDestino
        : relacion.usuarioOrigen;
      const relacionadoId = normalizeParticipantId(relacionado.id ?? relacionado['@id'] ?? '');
      const relacionadoNombreCompleto = relacionado.nombreCompleto?.trim() ?? '';
      const relacionadoNombre = relacionado.nombre?.trim() ?? '';
      const relacionadoApellidos = relacionado.apellidos?.trim() ?? '';
      const name = relacionadoNombreCompleto
        || [relacionadoNombre, relacionadoApellidos].filter(Boolean).join(' ').trim()
        || (relacionadoId ? `Usuario ${relacionadoId}` : 'Usuario relacionado');

      return {
        id: relacionadoId,
        name,
        role: relacion.tipoRelacion,
        personType: 'adulto' as const,
        origin: 'familiar' as const,
        avatarInitial: name.charAt(0).toUpperCase(),
      };
    }).filter((member) => member.id.length > 0);
  });

  protected readonly invitadosRows = computed<FamilyMember[]>(() => this.invitados());

  protected readonly inscritosRows = computed<FamilyMember[]>(() => {
    const eventSummary = this.eventSummary();

    return this.inscritos().map((inscrito) => {
      const key = this.inscritoKey(inscrito);
      const knownMember = this.participantsLookup().get(key);
      const fullName = [inscrito.nombre?.trim() ?? '', inscrito.apellidos?.trim() ?? '']
        .filter(Boolean)
        .join(' ')
        .trim();
      const fallbackLabel = inscrito.origen === 'invitado' ? 'Invitado' : 'Familiar';
      const name = fullName || knownMember?.name || `${fallbackLabel} ${inscrito.id}`;
      const rawPaymentStatus = inscrito.inscripcionRelacion?.estadoPago ?? 'pendiente';
      const menuNames = inscrito.inscripcionRelacion?.lineas
        ?.map((linea) => linea.nombreMenuSnapshot.trim())
        .filter(Boolean)
        .join(', ');

      return {
        id: String(inscrito.id),
        name,
        role: inscrito.origen === 'invitado' ? 'Invitado' : (knownMember?.role ?? 'Familiar'),
        personType: knownMember?.personType ?? 'adulto',
        origin: inscrito.origen,
        avatarInitial: name.charAt(0).toUpperCase() || '?',
        notes: menuNames ? `Menús: ${menuNames}` : undefined,
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
      } satisfies FamilyMember;
    });
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

    for (const invitado of this.invitadosRows()) {
      lookup.set(this.participantKey(invitado), invitado);
    }

    return lookup;
  });

  protected readonly participants = computed<FamilyMember[]>(() => {
    const titular = this.usuarioLogado();
    return uniqueParticipantsByKey([
      ...(titular ? [titular] : []),
      ...this.members(),
      ...this.relacionesComoMiembros(),
      ...this.invitadosRows(),
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

  protected readonly selectedCountLabel = computed(() => {
    const count = this.selectedMemberIds().length;
    if (count === 0) return 'Todavía no seleccionaste personas para inscribir.';
    if (count === 1) return '1 persona seleccionada para pasar a menús.';
    return `${count} personas seleccionadas para pasar a menús.`;
  });

  protected readonly canContinueToMenus = computed(() =>
    Boolean(this.event()?.inscripcionAbierta === true && this.selectedMemberIds().length > 0),
  );

  protected readonly menusButtonLabel = computed(() => {
    const count = this.selectedMemberIds().length;
    return count === 0
      ? 'Seleccioná al menos un familiar'
      : `Seleccionar menús (${count})`;
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

          return this.eventosApi
            .guardarSeleccionParticipantes(request.eventId, participantes)
            .pipe(
              tap((saved) => {
                this.inscritos.set(saved);
                this.selectedMemberIds.set(toSelectedMemberKeys(saved));
              }),
              catchError(() => {
                this.errorMessage.set('No pudimos guardar la selección de participantes. Probá nuevamente.');
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
        tap(() => this.loadRelaciones()),
        tap((id) => this.loadInscritos(id)),
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
    if (member.origin === 'invitado') {
      return this.isMemberSelected(member) ? 'Quitar' : 'Inscribir';
    }

    if (this.isAlreadyEnrolled(member) && !this.isMemberSelected(member)) return 'Eliminar';
    return this.isMemberSelected(member) ? 'Quitar' : 'Inscribir';
  }

  protected participantSecondaryActionLabel(member: FamilyMember): string {
    if (member.origin !== 'invitado') {
      return '';
    }

    return this.isDeletingInvitado(member.id) ? 'Borrando...' : 'Borrar';
  }

  protected isParticipantActionDisabled(member: FamilyMember): boolean {
    return member.origin === 'invitado' && this.isDeletingInvitado(member.id);
  }

  protected toggleMemberSelection(member: FamilyMember): void {
    const key = this.participantKey(member);
    this.selectedMemberIds.update((current) =>
      current.includes(key) ? current.filter((id) => id !== key) : [...current, key],
    );
    this.persistSelectedParticipants();
  }

  protected handleParticipantSecondaryAction(memberId: string): void {
    this.removeInvitado(memberId);
  }

  protected getRelacionadoNombre(relacion: RelacionUsuarioApi): string {
    const myId = this.authService.currentUserId;
    const relacionado = relacion.usuarioOrigen.id === myId
      ? relacion.usuarioDestino
      : relacion.usuarioOrigen;
    return `${relacionado.nombre} ${relacionado.apellidos}`.trim();
  }

  protected goBack(): void {
    void this.router.navigate(['/eventos/inicio']);
  }

  protected openMenus(): void {
    const selected = this.selectedMemberIds();
    const eventId = this.eventId();
    if (!selected.length || !eventId || this.event()?.inscripcionAbierta !== true) return;

    void this.router.navigate(['/eventos', eventId, 'menus']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected isDeletingInvitado(memberId: string): boolean {
    return this.deletingInvitadoId() === memberId;
  }

  protected isDeletingInscritoInvitado(memberId: string): boolean {
    return this.deletingInscritoInvitadoId() === memberId;
  }

  protected inscritoActionLabel(member: FamilyMember): string {
    if (member.origin !== 'invitado') {
      return '';
    }

    return this.isDeletingInscritoInvitado(member.id) ? 'Borrando...' : 'Borrar';
  }

  // ── Acciones con Observables ──────────────────────────────────────────

  protected submitInvitado(): void {
    if (this.invitadoForm.invalid || this.submittingInvitado()) {
      this.invitadoForm.markAllAsTouched();
      return;
    }

    const eventId = this.eventId();
    if (!eventId) return;

    this.invitadosError.set(null);
    this.submittingInvitado.set(true);

    const value = this.invitadoForm.getRawValue();
    const payload: AltaInvitadoPayload = {
      nombre:        value.nombre.trim(),
      apellidos:     value.apellidos.trim(),
      tipoPersona:   value.tipoPersona,
      parentesco:    value.parentesco.trim(),
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
            nombre: '', apellidos: '', tipoPersona: 'adulto',
            parentesco: 'Invitado/a', observaciones: '',
          });
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

  protected removeInscritoInvitado(memberId: string): void {
    const eventId = this.eventId();
    if (!eventId || this.deletingInscritoInvitadoId()) return;

    this.inscritosError.set(null);
    this.deletingInscritoInvitadoId.set(memberId);

    this.eventosStore
      .bajaInvitadoEnEvento(eventId, memberId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          const normalizedMemberId = normalizeParticipantId(memberId);
          const selectedKey = `invitado:${normalizedMemberId}`;

          this.selectedMemberIds.update((current) => current.filter((id) => id !== selectedKey));
          this.inscritos.update((current) =>
            current.filter((inscrito) => !(inscrito.origen === 'invitado' && normalizeParticipantId(inscrito.id) === normalizedMemberId)),
          );
          this.invitados.update((current) =>
            current.filter((member) => !(member.origin === 'invitado' && normalizeParticipantId(member.id) === normalizedMemberId)),
          );

          this.persistSelectedParticipants();
          this.deletingInscritoInvitadoId.set(null);
        },
        error: () => {
          this.inscritosError.set('No se pudo borrar el invitado seleccionado.');
          this.deletingInscritoInvitadoId.set(null);
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

  private loadRelaciones(): void {
    const userId = this.authService.currentUserId;
    if (!userId) return;

    this.loadingRelaciones.set(true);
    this.relacionesError.set(null);

    this.eventosApi
      .getRelacionesByUsuario(userId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (relaciones) => {
          this.relaciones.set(relaciones);
          this.loadingRelaciones.set(false);
        },
        error: () => {
          this.relacionesError.set('No pudimos cargar las relaciones familiares.');
          this.relaciones.set([]);
          this.loadingRelaciones.set(false);
        },
      });
  }

  private loadInscritos(eventId: string): void {
    this.loadingInscritos.set(true);
    this.inscritosError.set(null);
    this.selectedMemberIds.set([]);

    this.eventosStore
      .getSeleccionParticipantes(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (inscritos) => {
          this.inscritos.set(inscritos);
          this.selectedMemberIds.set(toSelectedMemberKeys(inscritos));
          this.loadingInscritos.set(false);
        },
        error: () => {
          this.inscritos.set([]);
          this.selectedMemberIds.set([]);
          this.inscritosError.set('No pudimos cargar a quiénes ya apuntaste en este evento.');
          this.loadingInscritos.set(false);
        },
      });
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
      const uniqueKey = `${origen}:${id}`;
      if (seen.has(uniqueKey)) continue;

      seen.add(uniqueKey);
      participantes.push({ id, origen });
    }

    return participantes;
  }

  private persistSelectedParticipants(): void {
    const eventId = this.eventId();
    if (!eventId || this.event()?.inscripcionAbierta !== true) {
      return;
    }

    this.selectionSaveRequests$.next({
      eventId,
      selectedKeys: [...this.selectedMemberIds()],
      });
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

    const origin = participante.origen === 'invitado' ? 'invitado' : 'familiar';
    const key = `${origin}:${id}`;

    if (seen.has(key)) continue;
    seen.add(key);
    selected.push(key);
  }

  return selected;
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
