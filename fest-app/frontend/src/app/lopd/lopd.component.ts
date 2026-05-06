import { ChangeDetectionStrategy, Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

import { LopdService } from './lopd.service';
import { AuthStore } from '../core/auth/auth-store';
import { MobileHeader } from '../features/shared/components/mobile-header/mobile-header';

@Component({
  selector: 'app-lopd',
  standalone: true,
  imports: [CommonModule, MobileHeader],
  templateUrl: './lopd.component.html',
  styleUrls: ['./lopd.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LopdComponent implements OnInit {
  private readonly lopdService = inject(LopdService);
  private readonly authStore = inject(AuthStore);
  private readonly router = inject(Router);

  loading = signal(true);
  accepting = signal(false);
  textoLopd = signal<string | null>(null);

  ngOnInit(): void {
    this.loading.set(true);

    this.lopdService.getLopd().subscribe({
      next: (textoLopd) => {
        this.textoLopd.set(textoLopd);
        this.loading.set(false);
      },
      error: () => {
        this.textoLopd.set(null);
        this.loading.set(false);
      },
    });
  }

  aceptar(): void {
    const user = this.authStore.user();

    if (!user?.id || this.accepting()) {
      return;
    }

    this.accepting.set(true);

    this.lopdService.patchAcepto(user.id, true).subscribe({
      next: () => {
        this.authStore.patchLocalUser({ aceptoLopd: true });

        if (user.debeCambiarPassword) {
          void this.router.navigateByUrl('/auth/cambiar-password');
          return;
        }

        void this.router.navigateByUrl('/eventos/inicio');
      },
      error: () => {
        this.accepting.set(false);
      },
    });
  }

  declinar(): void {
    const user = this.authStore.user();

    if (!user?.id) {
      return;
    }

    this.lopdService.patchAcepto(user.id, false).subscribe({
      next: () => this.authStore.logout(),
    });
  }
}
