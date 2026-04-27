import { Injectable, inject } from '@angular/core';
import { SwPush } from '@angular/service-worker';
import { HttpClient } from '@angular/common/http';
import {environment} from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class PushService {

  private readonly swPush = inject(SwPush);
  private readonly http = inject(HttpClient);
  private readonly vapidPublicKey = environment.vapidPublicKey;

  subscribe(): void {

    if (!this.swPush.isEnabled) {
      console.warn('Service worker no activo');
      return;
    }

    this.swPush.requestSubscription({
      serverPublicKey: this.vapidPublicKey
    }).then(subscription => {

      console.log('SUBSCRIPCIÓN:', subscription);

      this.http.post('/api/push/subscribe', subscription).subscribe();

    }).catch(err => {
      if (err.name === 'NotAllowedError') {
        console.warn('El usuario denegó las notificaciones');
      } else {
        console.error('Error inesperado al suscribirse:', err);
      }
    });
  }
}
