import { Injectable, signal } from '@angular/core';

const DISMISS_KEY = 'pwa-install-dismissed';
const DISMISS_TTL_MS = 7 * 24 * 60 * 60 * 1000; // 7 días

@Injectable({ providedIn: 'root' })
export class PwaInstallService {
  private deferredPrompt: any = null;

  canInstall = signal(false);
  isInstalled = signal(false);

  constructor() {
    this.listenForInstallPrompt();
    this.listenForInstalled();
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
    // FIX: persistir el dismiss para no volver a mostrar el banner durante 7 días
    try {
      localStorage.setItem(DISMISS_KEY, Date.now().toString());
    } catch {
      // noop
    }
  }
}
