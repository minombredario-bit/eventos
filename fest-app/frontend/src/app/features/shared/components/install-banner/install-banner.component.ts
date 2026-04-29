import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PwaInstallService } from '../../../../services/pwa-install.service';
import { PushService } from '../../../../core/push/push.service';
import { ToastService } from '../toast/toast.service';

@Component({
  selector: 'app-install-banner',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './install-banner.component.html',
  styleUrl: './install-banner.component.scss',
})
export class InstallBannerComponent {
  readonly pwa = inject(PwaInstallService);
  readonly push = inject(PushService);
  private readonly toast = inject(ToastService);

  async install(): Promise<void> {
    await this.pwa.promptInstall();
  }

  async togglePush(): Promise<void> {
    if (this.push.subscribed()) {
      const result = await this.push.unsubscribe();

      switch (result) {
        case 'unsubscribed':
          this.toast.showSuccess('Notificaciones desactivadas.');
          break;
        case 'not_subscribed':
          this.toast.showInfo('Las notificaciones ya estaban desactivadas.');
          break;
        case 'unavailable':
          this.toast.showError('Las notificaciones no están disponibles en este dispositivo.');
          break;
        case 'error':
          this.toast.showError('No se pudieron desactivar las notificaciones. Inténtalo de nuevo.');
          break;
      }

      return;
    }

    const result = await this.push.subscribe();

    switch (result) {
      case 'subscribed':
        this.toast.showSuccess('Notificaciones activadas correctamente.');
        break;
      case 'already_subscribed':
        this.toast.showInfo('Las notificaciones ya estaban activadas.');
        break;
      case 'denied':
        this.toast.showError('Has bloqueado las notificaciones. Puedes cambiar esto en la configuración del navegador.');
        break;
      case 'unavailable':
        this.toast.showError('Las notificaciones no están disponibles en este dispositivo.');
        break;
      case 'error':
        this.toast.showError('No se pudieron activar las notificaciones. Inténtalo de nuevo.');
        break;
    }
  }
}
