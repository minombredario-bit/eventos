import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, from, of } from 'rxjs';
import { switchMap, catchError, map, tap } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PushSubscriptionData {
  endpoint: string;
  keys: {
    p256dh: string;
    auth: string;
  };
}

@Injectable({ providedIn: 'root' })
export class PushNotificationService {
  private readonly http = inject(HttpClient);

  /**
   * Verifica si el navegador soporta notificaciones push.
   */
  isSupported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  }

  /**
   * Verifica si el usuario tiene una suscripción push activa.
   */
  checkIfEnabled(): Observable<boolean> {
    return this.http
      .get<{ hasSubscription: boolean }>(
        `${environment.apiUrl}/me/notificaciones-push/status`
      )
      .pipe(
        map((response) => response.hasSubscription),
        catchError(() => of(false))
      );
  }

  /**
   * Solicita permiso al usuario y habilita las notificaciones push.
   * Retorna true si se concedió permiso.
   */
  requestPermissionAndEnable(): Observable<boolean> {
    console.log('📡 requestPermissionAndEnable called');

    if (!this.isSupported()) {
      console.log('❌ Push notifications not supported');
      return of(false);
    }

    console.log('Current permission:', Notification.permission);

    // Si ya se rechazó, retornar false
    if (Notification.permission === 'denied') {
      console.log('❌ Notification permission already denied');
      return of(false);
    }

    // Si ya se concedió, directamente habilitar
    if (Notification.permission === 'granted') {
      console.log('✅ Notification permission already granted');
      return this.enablePushNotifications();
    }

    // Solicitar permiso (Notification.permission debe ser 'default')
    console.log('🔔 Requesting notification permission...');

    return from(Promise.resolve(Notification.requestPermission())).pipe(
      tap((permission) => console.log('📡 Permission result:', permission)),
      switchMap((permission) => {
        console.log('✅ Permission response:', permission);
        if (permission === 'granted') {
          console.log('🎉 Permission granted, enabling push...');
          return this.enablePushNotifications();
        }
        console.log('❌ Permission not granted:', permission);
        return of(false);
      }),
      catchError((err) => {
        console.error('❌ Error requesting permission:', err);
        return of(false);
      })
    );
  }

   /**
    * Habilita las notificaciones push registrando la suscripción en el backend.
    */
   private enablePushNotifications(): Observable<boolean> {
      console.log('📡 enablePushNotifications called');

      if (!('serviceWorker' in navigator)) {
        console.error('❌ Service Worker not available');
        return of(false);
      }

      console.log('🔍 navigator.serviceWorker:', navigator.serviceWorker);

      // Crear una promesa que intente obtener el SW listo
      const getSwReady = (): Promise<ServiceWorkerRegistration> => {
        // Si already hay un controller activo, usar la promesa ready
        if (navigator.serviceWorker.controller) {
          console.log('✅ SW controller already active, using ready promise');
          return navigator.serviceWorker.ready;
        }

        // Si no hay controller, intentar registrar el SW
        console.log('📡 No SW controller found, attempting to register...');
        return navigator.serviceWorker.register('/ngsw-worker.js', { scope: '/' })
          .then((registration) => {
            console.log('✅ SW registered, waiting for ready...');
            return navigator.serviceWorker.ready;
          })
          .catch((err) => {
            console.error('❌ Failed to register SW:', err);
            throw new Error('Service Worker registration failed: ' + err.message);
          });
      };

      return from(getSwReady()).pipe(
        tap((registration: ServiceWorkerRegistration) => {
          console.log('✅ Service Worker ready! Registration:', registration);
          console.log('✅ pushManager available:', !!registration.pushManager);
        }),
        switchMap((registration: ServiceWorkerRegistration) => {
          console.log('📡 Inside switchMap, getting pushManager');
          const pushManager = registration.pushManager;

          if (!pushManager) {
            console.error('❌ pushManager not available');
            return of(false);
          }

          console.log('📡 pushManager obtained, subscribing...');
          const publicKey = this.getPublicVapidKey();
          console.log('📡 Public key:', publicKey?.substring(0, 20) + '...');

          if (!publicKey) {
            console.error('❌ No VAPID public key configured');
            return of(false);
          }

          const applicationServerKey = this.urlBase64ToUint8Array(publicKey);
          console.log('📡 applicationServerKey converted, length:', applicationServerKey.length);

          return from(
            pushManager.subscribe({
              userVisibleOnly: true,
              applicationServerKey: applicationServerKey,
            })
          );
        }),
        tap((subscription: any) => {
          console.log('✅ Subscription created!');
          console.log('✅ Endpoint:', subscription.endpoint?.substring(0, 50) + '...');
        }),
        switchMap((sub: any) => {
          console.log('📡 Saving subscription to backend...');
          return this.savePushSubscription(sub);
        }),
        tap((result: boolean) => {
          console.log('✅ Backend response:', result);
        }),
        catchError((err) => {
          console.error('❌ Complete error chain:', err);
          console.error('❌ Error type:', err.constructor?.name);
          console.error('❌ Error message:', err instanceof Error ? err.message : String(err));
          return of(false);
        })
      );
    }

  /**
   * Guarda la suscripción push en el backend.
   */
  private savePushSubscription(subscription: PushSubscription): Observable<boolean> {
    const data: PushSubscriptionData = {
      endpoint: subscription.endpoint,
      keys: {
        p256dh: this.arrayBufferToBase64(subscription.getKey('p256dh') as ArrayBuffer),
        auth: this.arrayBufferToBase64(subscription.getKey('auth') as ArrayBuffer),
      },
    };

    return this.http
      .post<{ success: boolean }>(
        `${environment.apiUrl}/me/notificaciones-push/subscribe`,
        data
      )
      .pipe(
        map((response) => response.success),
        catchError(() => of(false))
      );
  }

  /**
   * Deshabilita las notificaciones push eliminando la suscripción.
   */
  disablePushNotifications(): Observable<boolean> {
    return this.http
      .post<{ success: boolean }>(
        `${environment.apiUrl}/me/notificaciones-push/unsubscribe`,
        {}
      )
      .pipe(
        map((response) => response.success),
        catchError(() => of(false))
      );
  }

  /**
   * Envía una notificación de prueba.
   */
  sendTestNotification(): Observable<{ success: boolean }> {
    return this.http.post<{ success: boolean }>(
      `${environment.apiUrl}/me/notificaciones-push/test`,
      {}
    );
  }

  /**
   * Obtiene la clave pública VAPID del backend (configurada en environment).
   */
  private getPublicVapidKey(): string {
    // Este valor debe estar en environment.ts
    return (environment as any).vapidPublicKey || '';
  }

  /**
   * Convierte una cadena base64 URL a Uint8Array.
   */
  private urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
  }

  /**
   * Convierte un ArrayBuffer a base64.
   */
  private arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
  }
}
