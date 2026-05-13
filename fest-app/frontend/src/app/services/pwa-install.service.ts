import { computed, Injectable, signal } from '@angular/core';

const DISMISS_KEY = 'pwa-install-dismissed';
const DISMISS_TTL_MS = 7 * 24 * 60 * 60 * 1000; // 7 días

@Injectable({ providedIn: 'root' })
export class PwaInstallService {
  private deferredPrompt: any = null;

  canInstall = signal(false);
  isInstalled = signal(false);

  /** true si el usuario cerró el banner (tanto Android como iOS) */
  private dismissed = signal(false);

  /** true si es iOS Safari y la app NO está instalada en standalone */
  readonly isIos = signal(
    /iphone|ipad|ipod/i.test(navigator.userAgent) &&
    !(window.navigator as any).standalone
  );

  /** Mostrar el hint iOS: es iOS, no instalada, y no ha descartado el banner */
  readonly showIosHint = computed(
    () => this.isIos() && !this.isInstalled() && !this.dismissed()
  );

  constructor() {
    this.listenForInstallPrompt();
    this.listenForInstalled();

    // Restaurar estado de dismiss persistido (aplica también a iOS)
    if (this.isDismissedRecently()) {
      this.dismissed.set(true);
    }
  }

  private isDismissedRecently(): boolean {
    try {
      const raw = localStorage.getItem(DISMISS_KEY);
      if (!raw) return false;
      return Date.now() - Number(raw) < DISMISS_TTL_MS;
    } catch {
      return false;
    }
  }

  private listenForInstallPrompt() {
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      this.deferredPrompt = e;

      // FIX: no mostrar el banner si el usuario lo descartó recientemente
      if (!this.isDismissedRecently()) {
        this.canInstall.set(true);
      }
    });
  }

  private listenForInstalled() {
    window.addEventListener('appinstalled', () => {
      this.deferredPrompt = null;
      this.canInstall.set(false);
      this.isInstalled.set(true);
      try {
        localStorage.removeItem(DISMISS_KEY);
      } catch {
        // noop
      }
    });
  }

  async promptInstall(): Promise<'accepted' | 'dismissed' | 'unavailable'> {
    if (!this.deferredPrompt) return 'unavailable';
    this.deferredPrompt.prompt();
    const { outcome } = await this.deferredPrompt.userChoice;
    this.deferredPrompt = null;
    this.canInstall.set(false);
    return outcome;
  }

  dismiss() {
    this.canInstall.set(false);
    this.dismissed.set(true);
    try {
      localStorage.setItem(DISMISS_KEY, Date.now().toString());
    } catch {
      // noop
    }
  }
}
