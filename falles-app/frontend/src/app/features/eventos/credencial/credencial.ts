import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { DatePipe } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { map } from 'rxjs';
import { AuthService } from '../../../core/auth/auth';
import { MobileHeader } from '../../shared/components/mobile-header/mobile-header';
import { CREDENTIAL_DATA, UPCOMING_EVENTS } from '../data/mock';

const QR_SIZE = 21;

@Component({
  selector: 'app-credencial',
  standalone: true,
  imports: [MobileHeader, DatePipe],
  templateUrl: './credencial.html',
  styleUrl: './credencial.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Credencial {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);

  private readonly eventId = toSignal(
    this.route.paramMap.pipe(map((params) => params.get('id') ?? UPCOMING_EVENTS[0].id)),
    { initialValue: UPCOMING_EVENTS[0].id }
  );

  protected readonly credential = computed(() => {
    const selectedEvent = UPCOMING_EVENTS.find((item) => item.id === this.eventId()) ?? UPCOMING_EVENTS[0];

    return {
      ...CREDENTIAL_DATA,
      eventTitle: selectedEvent.title,
      eventDate: `${selectedEvent.date}T${selectedEvent.time}:00`,
    };
  });

  protected readonly qrRows = computed(() => this.buildPseudoQr(this.credential().qrToken));

  protected goBack(): void {
    void this.router.navigate(['/eventos', this.eventId(), 'menus']);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  private buildPseudoQr(token: string): boolean[][] {
    return Array.from({ length: QR_SIZE }, (_, row) => {
      return Array.from({ length: QR_SIZE }, (_, col) => {
        const charCode = token.charCodeAt((row * QR_SIZE + col) % token.length);
        return (charCode + row + col) % 3 === 0;
      });
    });
  }
}
