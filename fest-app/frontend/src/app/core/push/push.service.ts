import { Injectable, inject, signal } from '@angular/core';
import { SwPush } from '@angular/service-worker';
import { HttpClient } from '@angular/common/http';
import { take } from 'rxjs';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class PushService {

  private readonly swPush = inject(SwPush);
  private readonly http = inject(HttpClient);
  private readonly vapidPublicKey = environment.vapidPublicKey;

  // FIX: estado público para que el componente pueda mostrar feedback
  readonly subscribing = signal(false);
  readonly subscribed = signal(false);

  constructor() {
    this.syncCurrentSubscriptionState();
  }

  subscribe(): Promise<'subscribed' | 'already_subscribed' | 'denied' | 'unavailable' | 'error'> {
    if (!this.swPush.isEnabled) {
      console.warn('Service worker no activo');
      return Promise.resolve('unavailable');
    }

    // FIX: comprobar si ya existe una suscripción activa antes de pedir permisos
    return new Promise((resolve) => {
      this.swPush.subscription.pipe(take(1)).subscribe(existing => {
        if (existing) {
          this.subscribed.set(true);
          resolve('already_subscribed');
          return;
        }

        this.subscribing.set(true);

        this.swPush.requestSubscription({
          serverPublicKey: this.vapidPublicKey
        }).then(subscription => {
          this.http.post(`${environment.apiUrl}/push/subscribe`, subscription).subscribe({
            next: () => {
              this.subscribed.set(true);
              this.subscribing.set(false);
              resolve('subscribed');
            },
            error: () => {
              this.subscribing.set(false);
              resolve('error');
            },
          });
        }).catch(err => {
          this.subscribing.set(false);
          if (err.name === 'NotAllowedError') {
            console.warn('El usuario denegó las notificaciones');
            resolve('denied');
          } else {
            console.error('Error inesperado al suscribirse:', err);
            resolve('error');
          }
        });
      });
    });
  }

  unsubscribe(): Promise<'unsubscribed' | 'not_subscribed' | 'unavailable' | 'error'> {
    if (!this.swPush.isEnabled) {
      return Promise.resolve('unavailable');
    }

    this.subscribing.set(true);

    return new Promise((resolve) => {
      this.swPush.subscription.pipe(take(1)).subscribe(existing => {
        if (!existing) {
          this.subscribed.set(false);
          this.subscribing.set(false);
          resolve('not_subscribed');
          return;
        }

        const endpoint = existing.endpoint;

        existing.unsubscribe()
          .then((browserResult) => {
            if (!browserResult) {
              this.subscribing.set(false);
              resolve('error');
              return;
            }

            this.http.post(`${environment.apiUrl}/push/unsubscribe`, { endpoint }).subscribe({
              next: () => {
                this.subscribed.set(false);
                this.subscribing.set(false);
                resolve('unsubscribed');
              },
              error: () => {
                this.subscribing.set(false);
                resolve('error');
              },
            });
          })
          .catch(() => {
            this.subscribing.set(false);
            resolve('error');
          });
      });
    });
  }

  private syncCurrentSubscriptionState(): void {
    if (!this.swPush.isEnabled) {
      this.subscribed.set(false);
      return;
    }

    this.swPush.subscription.pipe(take(1)).subscribe(subscription => {
      this.subscribed.set(!!subscription);
    });
  }
}
