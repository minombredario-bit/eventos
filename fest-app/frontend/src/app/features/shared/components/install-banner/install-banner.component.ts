import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PwaInstallService } from '../../../../services/pwa-install.service';

@Component({
  selector: 'app-install-banner',
  standalone: true,
  imports: [CommonModule],
  template: `
    @if (pwa.canInstall()) {
      <div class="install-banner">
        <div class="install-banner__content">
          <img src="/icons/icon-192x192.png" alt="FestivApp" class="install-banner__icon" />
          <div class="install-banner__text">
            <strong>Instalar FestivApp</strong>
            <span>Acceso rápido desde tu pantalla de inicio</span>
          </div>
        </div>
        <div class="install-banner__actions">
          <button class="btn-dismiss" (click)="pwa.dismiss()">Ahora no</button>
          <button class="btn-install" (click)="install()">Instalar</button>
        </div>
      </div>
    }
  `,
  styles: [`
    .install-banner {
      position: fixed;
      bottom: 1rem;
      left: 1rem;
      right: 1rem;
      background: white;
      border-radius: 12px;
      padding: 1rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      display: flex;
      flex-direction: column;
      gap: 1rem;
      z-index: 9999;
      border-left: 4px solid #f05a00;
      animation: slideUp 0.3s ease;
    }
    @keyframes slideUp {
      from { transform: translateY(100%); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }
    .install-banner__content {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .install-banner__icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
    }
    .install-banner__text {
      display: flex;
      flex-direction: column;
      font-size: 0.9rem;
      span { color: #666; font-size: 0.8rem; }
    }
    .install-banner__actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
    }
    .btn-dismiss {
      background: none;
      border: none;
      color: #666;
      cursor: pointer;
      padding: 0.5rem 1rem;
      border-radius: 8px;
    }
    .btn-install {
      background: #f05a00;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 0.5rem 1.25rem;
      cursor: pointer;
      font-weight: 600;
    }
  `]
})
export class InstallBannerComponent {
  pwa = inject(PwaInstallService);

  async install() {
    await this.pwa.promptInstall();
  }
}
