import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

@Component({
  selector: 'app-mobile-header',
  standalone: true,
  templateUrl: './mobile-header.html',
  styleUrl: './mobile-header.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MobileHeader {
  readonly title = input.required<string>();
  readonly subtitle = input('');
  readonly showBack = input(false);
  readonly actionLabel = input('');
  readonly actionAriaLabel = input('Acción de cabecera');

  readonly backPressed = output<void>();
  readonly actionPressed = output<void>();

  protected onBack(): void {
    this.backPressed.emit();
  }

  protected onAction(): void {
    this.actionPressed.emit();
  }
}
