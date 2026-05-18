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

  // Emite cuando el usuario confirma la acción
  readonly confirmed = output<boolean>();
  // Emite cuando el usuario cancela / cierra el modal
  readonly cancel = output<void>();

  private readonly loading = signal(false);

  protected onConfirm(): void {
    this.loading.set(true);
    this.confirmed.emit(true);
    this.loading.set(false);
  }

  protected onCancel(): void {
    // Emitimos sólo el evento de cancel para que el consumidor lo gestione
    // (no debemos emitir `confirmed(false)` porque en plantillas se
    // manejan ambos eventos por separado).
    this.cancel.emit();
  }
}

