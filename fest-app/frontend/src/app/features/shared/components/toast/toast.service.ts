import { Injectable } from '@angular/core';
import { Subject } from 'rxjs';

export type ToastType = 'success' | 'error' | 'info';
export interface ToastMessage {
  id: string;
  type: ToastType;
  text: string;
}

@Injectable({ providedIn: 'root' })
export class ToastService {
  private readonly subject = new Subject<ToastMessage>();
  public readonly messages$ = this.subject.asObservable();

  show(text: string, type: ToastType = 'info') {
    const msg: ToastMessage = { id: Date.now().toString(), type, text };
    this.subject.next(msg);
  }

  showSuccess(text: string) { this.show(text, 'success'); }
  showError(text: string) { this.show(text, 'error'); }
  showInfo(text: string) { this.show(text, 'info'); }
}

