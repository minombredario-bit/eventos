import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { NgClass } from '@angular/common';

@Component({
  selector: 'app-cta-button',
  standalone: true,
  imports: [NgClass],
  templateUrl: './cta-button.html',
  styleUrl: './cta-button.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CtaButton {
  readonly label = input.required<string>();
  readonly loadingLabel = input('Procesando...');
  readonly disabled = input(false);
  readonly loading = input(false);
  readonly fullWidth = input(true);
  readonly type = input<'button' | 'submit'>('button');
  readonly ariaLabel = input<string | null>(null);
  readonly variant = input<'primary' | 'ghost'>('primary');

  readonly pressed = output<void>();

  protected onPressed(): void {
    this.pressed.emit();
  }
}
