import { Component, OnInit, inject } from '@angular/core';
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
})
export class LopdComponent implements OnInit {
  private readonly lopdService = inject(LopdService);
  private readonly authStore = inject(AuthStore);
  private readonly router = inject(Router);

  loading = true;
  accepting = false;
  textoLopd: string | null = null;

  ngOnInit(): void {
    this.lopdService.getLopd().subscribe({
      next: (textoLopd) => {
        this.textoLopd = textoLopd;
        this.loading = false;
      },
      error: () => {
        this.textoLopd = null;
        this.loading = false;
      },
    });
  }

  aceptar(): void {
    const user = this.authStore.user();

    if (!user?.id || this.accepting) {
      return;
    }

    this.accepting = true;

    this.lopdService.patchAcepto(user.id, true).subscribe({
      next: () => {
        this.authStore.patchLocalUser({ aceptoLopd: true });

        if (user.debeCambiarPassword) {
          void this.router.navigateByUrl('/auth/cambiar-password');
          return;
        }

        void this.router.navigateByUrl('/inicio');
      },
      error: () => {
        this.accepting = false;
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
