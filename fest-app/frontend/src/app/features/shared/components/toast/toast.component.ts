import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastService, ToastMessage } from './toast.service';
import { AsyncPipe } from '@angular/common';

@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="toast-container" aria-live="polite">
      <div *ngFor="let msg of messages" class="toast" [ngClass]="msg.type">
        {{ msg.text }}
      </div>
    </div>
  `,
  styles: [
    `
    .toast-container { position: fixed; bottom: 1rem; right: 1rem; z-index: 9999; }
    .toast { margin-top: .5rem; padding: .75rem 1rem; border-radius: 6px; color: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.2); }
    .toast.success { background: #2d8a2d; }
    .toast.error { background: #b00020; }
    .toast.info { background: #333; }
    `
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ToastComponent {
  private readonly toastService = inject(ToastService);
  protected messages: ToastMessage[] = [];

  constructor() {
    this.toastService.messages$.subscribe((msg) => {
      this.messages = [...this.messages, msg];
      // Auto remove after 4s
      setTimeout(() => {
        this.messages = this.messages.filter((m) => m.id !== msg.id);
      }, 4000);
    });
  }
}

