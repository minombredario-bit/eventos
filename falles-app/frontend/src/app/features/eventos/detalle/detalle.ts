import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, firstValueFrom, map } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { CtaButton } from '../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../shared/components/member-row/member-row';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { EventSummary, FamilyMember } from '../models/ui';
import { EventoDetalleApi, EventosApi, PersonaFamiliarApi } from '../services/eventos-api';

@Component({
  selector: 'app-detalle',
  standalone: true,
  imports: [MobileHeader, MemberRow, CtaButton],
  templateUrl: './detalle.html',
  styleUrl: './detalle.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Detalle {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);

  private readonly eventId = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id') ?? '')),
    { initialValue: '' }
  );

  protected readonly loading = signal(true);
  protected readonly loadingPeople = signal(true);
  protected readonly errorMessage = signal<string | null>(null);

  protected readonly event = signal<EventoDetalleApi | null>(null);
  protected readonly members = signal<FamilyMember[]>([]);

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
      });

    void this.loadMembers();
  }

  protected readonly eventSummary = computed<EventSummary | null>(() => {
    const event = this.event();
    if (!event) {
      return null;
    }

    return {
      id: event.id,
      title: event.titulo,
      date: this.formatEventDate(event.fechaEvento),
      time: this.formatEventTime(event.horaInicio),
      location: event.lugar ?? 'Lugar por confirmar',
      status: event.inscripcionAbierta ? 'abierto' : 'cerrado',
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
      return 'Todavía no seleccionaste familiares para inscribir.';
    }

    if (count === 1) {
      return '1 familiar seleccionado para pasar a menús.';
    }

    return `${count} familiares seleccionados para pasar a menús.`;
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

  protected isMemberSelected(memberId: string): boolean {
    return this.selectedMemberIds().includes(memberId);
  }

  protected toggleMemberSelection(memberId: string): void {
    this.selectedMemberIds.update((current) => {
      if (current.includes(memberId)) {
        return current.filter((id) => id !== memberId);
      }

      return [...current, memberId];
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
}
