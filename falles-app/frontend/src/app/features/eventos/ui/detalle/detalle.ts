import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, map, tap } from 'rxjs';
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
  protected readonly loadingNoFalleros    = signal(true);
  protected readonly loadingRelaciones    = signal(false);
  protected readonly submittingNoFallero  = signal(false);
  protected readonly deletingNoFalleroId  = signal<string | null>(null);
  protected readonly errorMessage         = signal<string | null>(null);
  protected readonly noFallerosError      = signal<string | null>(null);
  protected readonly relacionesError      = signal<string | null>(null);

  protected readonly event        = signal<EventoDetalleApi | null>(null);
  protected readonly members      = signal<FamilyMember[]>([]);
  protected readonly noFalleros   = signal<FamilyMember[]>([]);
  protected readonly relaciones   = signal<RelacionUsuarioApi[]>([]);
  protected readonly selectedMemberIds = signal<string[]>([]);

  // ── Form ──────────────────────────────────────────────────────────────
  protected readonly noFalleroForm = this.fb.nonNullable.group({
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

      return {
        id: relacionado.id,
        name: `${relacionado.nombre} ${relacionado.apellidos}`.trim(),
        role: relacion.tipoRelacion,
        personType: 'adulto' as const,
        origin: 'familiar' as const,
        avatarInitial: relacionado.nombre.charAt(0).toUpperCase(),
      };
    });
  });

  protected readonly noFallerosRows = computed<FamilyMember[]>(() => {
    const summary = this.eventSummary();
    return this.noFalleros().map((member) => {
      if (member.enrollment || !summary) return member;
      return {
        ...member,
        enrollment: {
          eventId: summary.id,
          eventTitle: summary.title,
          eventLabel: summary.time === 'Sin hora'
            ? `${summary.title} · ${summary.date}`
            : `${summary.title} · ${summary.date} ${summary.time}`,
          paymentStatus: this.eventosMapper.toPaymentBadgeStatus('pendiente'),
          paymentStatusRaw: 'pendiente',
        },
      };
    });
  });

  protected readonly participants = computed<FamilyMember[]>(() => {
    const titular = this.usuarioLogado();
    return [
      ...(titular ? [titular] : []),
      ...this.members(),
      ...this.relacionesComoMiembros(),
      //...this.noFallerosRows(),
    ];
  });

  protected readonly eventSummary = computed<EventSummary | null>(() => {
    const event = this.event();
    if (!event) return null;

    let status: EventSummary['status'] = 'cerrado';
    if (event.inscripcionAbierta === true) status = 'abierto';
    else if (event.estado === 'publicado') status = 'ultimas_plazas';

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
      abierto: 'Inscripción abierta',
      ultimas_plazas: 'Últimas plazas',
    };
    return labels[this.eventSummary()?.status ?? ''] ?? 'Inscripción cerrada';
  });

  protected readonly selectedCountLabel = computed(() => {
    const count = this.selectedMemberIds().length;
    if (count === 0) return 'Todavía no seleccionaste personas para inscribir.';
    if (count === 1) return '1 persona seleccionada para pasar a menús.';
    return `${count} personas seleccionadas para pasar a menús.`;
  });

  protected readonly canContinueToMenus = computed(() =>
    Boolean(this.eventSummary()?.status === 'abierto' && this.selectedMemberIds().length > 0),
  );

  protected readonly menusButtonLabel = computed(() => {
    const count = this.selectedMemberIds().length;
    return count === 0
      ? 'Seleccioná al menos un familiar'
      : `Seleccionar menús (${count})`;
  });

  constructor() {
    this.eventId$
      .pipe(
        tap(() => this.selectedMemberIds.set([])),
        tap((id) => this.loadEvent(id)),
        tap(() => this.loadPersonasMias()),
        tap((id) => this.loadNoFalleros(id)),
        tap(() => this.loadRelaciones()),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe();
  }

  // ── Handlers de UI ────────────────────────────────────────────────────

  protected participantTrackKey(member: FamilyMember): string {
    return this.participantKey(member);
  }

  protected isMemberSelected(member: FamilyMember): boolean {
    return this.selectedMemberIds().includes(this.participantKey(member));
  }

  protected isAlreadyEnrolled(member: FamilyMember): boolean {
    return member.enrollment?.eventId === this.eventSummary()?.id;
  }

  protected participantActionLabel(member: FamilyMember): string {
    if (this.isAlreadyEnrolled(member) && !this.isMemberSelected(member)) return 'Eliminar';
    return this.isMemberSelected(member) ? 'Quitar' : 'Inscribir';
  }

  protected toggleMemberSelection(member: FamilyMember): void {
    const key = this.participantKey(member);
    this.selectedMemberIds.update((current) =>
      current.includes(key) ? current.filter((id) => id !== key) : [...current, key],
    );
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
    if (!selected.length || !eventId) return;

    const participantes = this.toParticipantesSeleccion(selected);

    this.eventosApi
      .guardarSeleccionParticipantes(eventId, participantes)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          void this.router.navigate(['/eventos', eventId, 'menus']);
        },
        error: () => {
          this.errorMessage.set('No pudimos guardar la selección de participantes. Probá nuevamente.');
        },
      });
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected isDeletingNoFallero(memberId: string): boolean {
    return this.deletingNoFalleroId() === memberId;
  }

  // ── Acciones con Observables ──────────────────────────────────────────

  protected submitNoFallero(): void {
    if (this.noFalleroForm.invalid || this.submittingNoFallero()) {
      this.noFalleroForm.markAllAsTouched();
      return;
    }

    const eventId = this.eventId();
    if (!eventId) return;

    this.noFallerosError.set(null);
    this.submittingNoFallero.set(true);

    const value = this.noFalleroForm.getRawValue();
    const payload: AltaInvitadoPayload = {
      nombre:        value.nombre.trim(),
      apellidos:     value.apellidos.trim(),
      tipoPersona:   value.tipoPersona,
      parentesco:    value.parentesco.trim(),
      observaciones: value.observaciones.trim(),
    };

    this.eventosStore
      .altaNoFalleroEnEvento(eventId, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.loadNoFalleros(eventId);
          this.noFalleroForm.patchValue({
            nombre: '', apellidos: '', tipoPersona: 'adulto',
            parentesco: 'Invitado/a', observaciones: '',
          });
          this.noFalleroForm.markAsPristine();
          this.noFalleroForm.markAsUntouched();
          this.submittingNoFallero.set(false);
        },
        error: () => {
          this.noFallerosError.set('No pudimos dar de alta al invitado. Probá nuevamente.');
          this.submittingNoFallero.set(false);
        },
      });
  }

  protected removeNoFallero(memberId: string): void {
    const eventId = this.eventId();
    if (!eventId || this.deletingNoFalleroId()) return;

    this.noFallerosError.set(null);
    this.deletingNoFalleroId.set(memberId);

    this.eventosStore
      .bajaNoFalleroEnEvento(eventId, memberId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.selectedMemberIds.update((current) =>
            current.filter((id) => id !== `no_fallero:${memberId}`),
          );
          this.loadNoFalleros(eventId);
          this.deletingNoFalleroId.set(null);
        },
        error: () => {
          this.noFallerosError.set('No se pudo dar de baja al invitado seleccionado.');
          this.deletingNoFalleroId.set(null);
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

  private loadNoFalleros(eventId: string): void {
    this.loadingNoFalleros.set(true);

    this.eventosStore
      .getNoFallerosByEvento(eventId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (noFalleros) => {
          this.noFalleros.set(noFalleros.map((p) => this.eventosMapper.toFamilyMember(p)));
          this.loadingNoFalleros.set(false);
        },
        error: () => {
          this.noFallerosError.set('No pudimos cargar los invitados para este evento.');
          this.noFalleros.set([]);
          this.loadingNoFalleros.set(false);
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

  private participantKey(member: FamilyMember): string {
    return `${member.origin === 'no_fallero' ? 'no_fallero' : 'familiar'}:${member.id}`;
  }

  private toParticipantesSeleccion(selectedKeys: string[]): ParticipanteSeleccionApi[] {
    const participantes: ParticipanteSeleccionApi[] = [];
    const seen = new Set<string>();

    for (const key of selectedKeys) {
      const [originRaw = '', ...idParts] = key.split(':');
      const id = idParts.length > 0 ? idParts.join(':').trim() : originRaw.trim();
      if (!id) continue;

      const origen: 'familiar' | 'no_fallero' = originRaw === 'no_fallero' ? 'no_fallero' : 'familiar';
      const uniqueKey = `${origen}:${id}`;
      if (seen.has(uniqueKey)) continue;

      seen.add(uniqueKey);
      participantes.push({ id, origen });
    }

    return participantes;
  }
}
