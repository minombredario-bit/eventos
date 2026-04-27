import { CommonModule } from '@angular/common';
import { Component, effect, inject, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { Title } from '@angular/platform-browser';
import { RouterOutlet } from '@angular/router';
import { AuthStore } from './core/auth/auth-store';
import { LopdComponent } from './lopd/lopd.component';
import { ToastComponent } from './features/shared/components/toast/toast.component';
import { InstallBannerComponent } from './features/shared/components/install-banner/install-banner.component';
import { TranslateService, TranslateModule } from '@ngx-translate/core';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, RouterOutlet, LopdComponent, ToastComponent, InstallBannerComponent, TranslateModule],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss'
})
export class AppComponent implements OnInit {
  private readonly title = inject(Title);
  public readonly authStore = inject(AuthStore);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);

  loading = true;

  constructor() {
    this.translate.setDefaultLang('es');
    const browserLang = typeof navigator !== 'undefined' ? (navigator.language ?? 'es').split('-')[0] : 'es';
    this.translate.use(browserLang);

    effect(() => {
      const user = this.authStore.user();
      const entidad = typeof user?.nombreEntidad === 'string' ? user.nombreEntidad.trim() : '';
      this.title.setTitle(entidad ? `Festiva - ${entidad}` : 'Festiva');
    });

    effect(() => {
      const user = this.authStore.user();
      if (user && user.aceptoLopd === false) {
        void this.router.navigate(['/lopd'], { replaceUrl: true });
      }
    });
  }

  ngOnInit(): void {
    setTimeout(() => {
      this.loading = false;
    }, 1200);
  }
}
