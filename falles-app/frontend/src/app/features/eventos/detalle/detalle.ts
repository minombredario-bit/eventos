import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, firstValueFrom, map } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { CtaButton } from '../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../shared/components/member-row/member-row';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { EventSummary, FamilyMember } from '../models/ui';
import { EventosMapper } from '../services/eventos-mapper';
import { AltaNoFalleroPayload, EventoDetalleApi, EventosApi } from '../services/eventos-api';

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

  private readonly eventId = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id') ?? '')),
    { initialValue: '' }
  );

  protected readonly loading = signal(true);
  protected readonly loadingPeople = signal(true);
  protected readonly loadingNoFalleros = signal(true);
  protected readonly submittingNoFallero = signal(false);
  protected readonly deletingNoFalleroId = signal<string | null>(null);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly noFallerosError = signal<string | null>(null);

  protected readonly event = signal<EventoDetalleApi | null>(null);
  protected readonly members = signal<FamilyMember[]>([]);
  protected readonly noFalleros = signal<FamilyMember[]>([]);

  protected readonly noFallerosRows = computed<FamilyMember[]>(() => {
    const summary = this.eventSummary();

    return this.noFalleros().map((member) => {
      if (member.enrollment || !summary) {
        return member;
      }

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

  protected readonly noFalleroForm = this.fb.nonNullable.group({
    nombre: ['', [Validators.required, Validators.minLength(2)]],
    apellidos: ['', [Validators.required, Validators.minLength(2)]],
    tipoPersona: this.fb.nonNullable.control<'adulto' | 'infantil'>('adulto'),
    parentesco: ['Invitado/a', [Validators.required, Validators.minLength(2)]],
    observaciones: [''],
  });

  protected readonly participants = computed(() => {
    return [...this.members(), ...this.noFallerosRows()];
  });

  protected participantTrackKey(member: FamilyMember): string {
    return this.participantKey(member);
  }

  constructor() {
    this.route.paramMap
      .pipe(
        map((params) => params.get('id') ?? ''),
        filter((id): id is string => Boolean(id)),
        distinctUntilChanged(),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe((id) => {
        this.selectedMemberIds.set([]);
        void this.loadEvent(id);
        void this.loadNoFalleros(id);
      });

    void this.loadMembers();
  }

  protected readonly eventSummary = computed<EventSummary | null>(() => {
    const event = this.event();
    if (!event) {
      return null;
    }

    let status: EventSummary['status'] = 'cerrado';
    if (event.inscripcionAbierta === true) {
      status = 'abierto';
    } else if (event.inscripcionAbierta === false) {
      status = 'cerrado';
    } else if (event.estado === 'publicado') {
      status = 'ultimas_plazas';
    }

    return {
      id: event.id,
      title: event.titulo,
      date: this.formatEventDate(event.fechaEvento),
      time: this.formatEventTime(event.horaInicio),
      location: event.lugar ?? 'Lugar por confirmar',
      status,
      description: event.descripcion ?? 'Sin descripción disponible.',
    };
  });

  protected readonly eventStatusLabel = computed(() => {
    const status = this.eventSummary()?.status;
    if (status === 'abierto') {
      return 'Inscripción abierta';
    }

    if (status === 'ultimas_plazas') {
      return 'Últimas plazas';
    }

    return 'Inscripción cerrada';
  });

  protected readonly selectedMemberIds = signal<string[]>([]);

  protected readonly selectedCountLabel = computed(() => {
    const count = this.selectedMemberIds().length;

    if (count === 0) {
      return 'Todavía no seleccionaste personas para inscribir.';
    }

    if (count === 1) {
      return '1 persona seleccionada para pasar a menús.';
    }

    return `${count} personas seleccionadas para pasar a menús.`;
  });

  protected readonly canContinueToMenus = computed(() => {
    const event = this.eventSummary();
    return Boolean(event?.status === 'abierto' && this.selectedMemberIds().length > 0);
  });

  protected readonly menusButtonLabel = computed(() => {
    const count = this.selectedMemberIds().length;

    if (count === 0) {
      return 'Seleccioná al menos un familiar';
    }

    return `Seleccionar menús (${count})`;
  });

  protected isMemberSelected(member: FamilyMember): boolean {
    return this.selectedMemberIds().includes(this.participantKey(member));
  }

  protected isAlreadyEnrolled(member: FamilyMember): boolean {
    const currentEventId = this.eventSummary()?.id;
    if (!currentEventId) {
      return false;
    }

    return member.enrollment?.eventId === currentEventId;
  }

  protected participantActionLabel(member: FamilyMember): string {
    if (this.isAlreadyEnrolled(member) && !this.isMemberSelected(member)) {
      return 'Eliminar';
    }

    return this.isMemberSelected(member) ? 'Quitar' : 'Inscribir';
  }

  protected toggleMemberSelection(member: FamilyMember): void {
    const memberKey = this.participantKey(member);

    this.selectedMemberIds.update((current) => {
      if (current.includes(memberKey)) {
        return current.filter((id) => id !== memberKey);
      }

      return [...current, memberKey];
    });
  }

  protected goBack(): void {
    void this.router.navigate(['/eventos/inicio']);
  }

  protected openMenus(): void {
    const selected = this.selectedMemberIds();
    if (!selected.length) {
      return;
    }

    const eventId = this.eventId();
    if (!eventId) {
      return;
    }

    void this.router.navigate(['/eventos', eventId, 'menus'], {
      queryParams: {
        participants: selected.join(','),
      },
    });
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  private async loadEvent(id: string): Promise<void> {
    this.loading.set(true);
    this.errorMessage.set(null);

    try {
      const event = await firstValueFrom(this.eventosApi.getEvento(id));
      this.event.set(event);
    } catch {
      this.errorMessage.set('No pudimos cargar el detalle del evento. Volvé a intentar en unos segundos.');
      this.event.set(null);
    } finally {
      this.loading.set(false);
    }
  }

  private async loadMembers(): Promise<void> {
    this.loadingPeople.set(true);

    try {
      const personas = await firstValueFrom(this.eventosApi.getPersonasMias());
      this.members.set(personas.map((persona) => this.eventosMapper.toFamilyMember(persona)));
    } catch {
      this.errorMessage.set('No pudimos cargar tus familiares para la inscripción.');
      this.members.set([]);
    } finally {
      this.loadingPeople.set(false);
    }
  }

  protected async submitNoFallero(): Promise<void> {
    if (this.noFalleroForm.invalid || this.submittingNoFallero()) {
      this.noFalleroForm.markAllAsTouched();
      return;
    }

    const eventId = this.eventId();
    if (!eventId) {
      return;
    }

    this.noFallerosError.set(null);
    this.submittingNoFallero.set(true);

    const value = this.noFalleroForm.getRawValue();
    const payload: AltaNoFalleroPayload = {
      nombre: value.nombre.trim(),
      apellidos: value.apellidos.trim(),
      tipoPersona: value.tipoPersona,
      parentesco: value.parentesco.trim(),
      observaciones: value.observaciones.trim(),
    };

    try {
      await firstValueFrom(this.eventosApi.altaNoFalleroEnEvento(eventId, payload));
      await this.loadNoFalleros(eventId);
      this.noFalleroForm.patchValue({
        nombre: '',
        apellidos: '',
        tipoPersona: 'adulto',
        parentesco: 'Invitado/a',
        observaciones: '',
      });
      this.noFalleroForm.markAsPristine();
      this.noFalleroForm.markAsUntouched();
    } catch {
      this.noFallerosError.set('No pudimos dar de alta al no fallero. Probá nuevamente.');
    } finally {
      this.submittingNoFallero.set(false);
    }
  }

  protected async removeNoFallero(memberId: string): Promise<void> {
    const eventId = this.eventId();
    if (!eventId || this.deletingNoFalleroId()) {
      return;
    }

    this.noFallerosError.set(null);
    this.deletingNoFalleroId.set(memberId);

    try {
      await firstValueFrom(this.eventosApi.bajaNoFalleroEnEvento(eventId, memberId));
      this.selectedMemberIds.update((current) => current.filter((id) => id !== `no_fallero:${memberId}`));
      await this.loadNoFalleros(eventId);
    } catch {
      this.noFallerosError.set('No se pudo dar de baja al no fallero seleccionado.');
    } finally {
      this.deletingNoFalleroId.set(null);
    }
  }

  protected isDeletingNoFallero(memberId: string): boolean {
    return this.deletingNoFalleroId() === memberId;
  }

  private async loadNoFalleros(eventId: string): Promise<void> {
    this.loadingNoFalleros.set(true);

    try {
      const noFalleros = await firstValueFrom(this.eventosApi.getNoFallerosByEvento(eventId));
      this.noFalleros.set(noFalleros.map((persona) => this.eventosMapper.toFamilyMember(persona)));
    } catch {
      this.noFallerosError.set('No pudimos cargar no falleros para este evento.');
      this.noFalleros.set([]);
    } finally {
      this.loadingNoFalleros.set(false);
    }
  }

  private formatEventDate(rawDate: string): string {
    const normalizedDate = rawDate.includes('T') ? rawDate.slice(0, 10) : rawDate;
    const [yearRaw, monthRaw, dayRaw] = normalizedDate.split('-');

    const year = Number(yearRaw);
    const month = Number(monthRaw);
    const day = Number(dayRaw);

    if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
      return 'Fecha por confirmar';
    }

    const eventDate = new Date(year, month - 1, day);
    if (Number.isNaN(eventDate.getTime())) {
      return 'Fecha por confirmar';
    }

    return eventDate.toLocaleDateString('es-ES', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    });
  }

  private formatEventTime(rawTime?: string | null): string {
    if (!rawTime) {
      return 'Sin hora';
    }

    const hhmmMatch = rawTime.match(/^(\d{2}:\d{2})/);
    if (hhmmMatch) {
      return hhmmMatch[1];
    }

    const isoTimeMatch = rawTime.match(/T(\d{2}:\d{2})/);
    if (isoTimeMatch) {
      return isoTimeMatch[1];
    }

    return 'Sin hora';
  }

  private participantKey(member: FamilyMember): string {
    return `${member.origin === 'no_fallero' ? 'no_fallero' : 'familiar'}:${member.id}`;
  }
}
