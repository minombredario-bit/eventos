import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { DatePipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { map } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { CtaButton } from '../../shared/components/cta-button/cta-button';
import { MemberRow } from '../../shared/components/member-row/member-row';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { FAMILY_MEMBERS, UPCOMING_EVENTS } from '../data/mock';

@Component({
  selector: 'app-detalle',
  standalone: true,
  imports: [DatePipe, MobileHeader, MemberRow, CtaButton],
  templateUrl: './detalle.html',
  styleUrl: './detalle.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Detalle {
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

  protected readonly eventStatusLabel = computed(() => {
    const status = this.event().status;
    if (status === 'abierto') {
      return 'Inscripción abierta';
    }

    if (status === 'ultimas_plazas') {
      return 'Últimas plazas';
    }

    return 'Inscripción cerrada';
  });

  protected readonly members = FAMILY_MEMBERS;

  protected goBack(): void {
    void this.router.navigate(['/eventos/inicio']);
  }

  protected openMenus(): void {
    void this.router.navigate(['/eventos', this.event().id, 'menus']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }
}
