import { computed, Injectable, signal } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class PwaInstallService {
  private deferredPrompt: any = null;

  canInstall = signal(false);
  isInstalled = signal(false);

  private dismissed = signal(false);

  readonly isIos = signal(
    /iphone|ipad|ipod/i.test(navigator.userAgent) &&
    !(window.navigator as any).standalone
  );

  readonly showIosHint = computed(
    () => this.isIos() && !this.isInstalled() && !this.dismissed()
  );

  constructor() {
    this.isInstalled.set(this.checkInstalled());

    this.listenForInstallPrompt();
    this.listenForInstalled();
  }

  private listenForInstallPrompt(): void {
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();

      this.deferredPrompt = e;
      this.dismissed.set(false);

      if (!this.checkInstalled()) {
        this.canInstall.set(true);
      }
    });
  }

  private listenForInstalled(): void {
    window.addEventListener('appinstalled', () => {
      this.deferredPrompt = null;
      this.canInstall.set(false);
      this.isInstalled.set(true);
      this.dismissed.set(false);
    });
  }

  async promptInstall(): Promise<'accepted' | 'dismissed' | 'unavailable'> {
    if (!this.deferredPrompt) {
      return 'unavailable';
    }

    this.deferredPrompt.prompt();

    const { outcome } = await this.deferredPrompt.userChoice;

    this.deferredPrompt = null;
    this.canInstall.set(false);

    if (outcome === 'accepted') {
      this.isInstalled.set(true);
      this.dismissed.set(false);
    }

    return outcome;
  }

  dismiss(): void {
    // Solo oculta el banner en la sesión/pantalla actual.
    // No se guarda en localStorage para que vuelva a aparecer
    // al recargar o al volver a entrar desde navegador.
    this.canInstall.set(false);
    this.dismissed.set(true);
  }

  private checkInstalled(): boolean {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      (window.navigator as any).standalone === true
    );
  }
}
