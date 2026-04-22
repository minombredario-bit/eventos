import {CommonModule} from '@angular/common';
import {ChangeDetectionStrategy, Component, input, output, signal} from '@angular/core';

@Component({
  selector: 'app-confirm-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './confirm-modal.html',
  styleUrls: ['./confirm-modal.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConfirmModal {
  readonly title = input('Confirmar');
  readonly message = input('¿Estás seguro?');
  readonly confirmLabel = input('Eliminar');
  readonly cancelLabel = input('Cancelar');

  readonly confirmed = output<boolean>();

  private readonly loading = signal(false);

  protected onConfirm(): void {
    this.loading.set(true);
    this.confirmed.emit(true);
    this.loading.set(false);
  }

  protected onCancel(): void {
    this.confirmed.emit(false);
  }
}

