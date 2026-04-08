import { ChangeDetectionStrategy, Component, HostListener, input, output, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-mobile-header',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './mobile-header.html',
  styleUrl: './mobile-header.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MobileHeader {
  readonly title = input.required<string>();
  readonly subtitle = input('');
  readonly showBack = input(false);
  readonly actionType = input<'default' | 'logout' | ''>('');
  readonly actionLabel = input('');
  readonly actionAriaLabel = input('Acción de cabecera');

  readonly backPressed = output<void>();
  readonly actionPressed = output<void>();
  private readonly userMenuOpen = signal(false);

  protected onBack(): void {
    this.backPressed.emit();
  }

  protected onAction(): void {
    this.actionPressed.emit();
  }

  protected toggleUserMenu(): void {
    this.userMenuOpen.update((open) => !open);
  }

  protected isUserMenuOpen(): boolean {
    return this.userMenuOpen();
  }

  protected onUserMenuSelect(option: 'salir'): void {
    this.userMenuOpen.set(false);
    this.onAction();
  }

  protected closeUserMenu(): void {
    this.userMenuOpen.set(false);
  }

  protected isLogoutAction(): boolean {
    if (this.actionType() === 'logout') {
      return true;
    }

    return this.actionLabel().trim().toLowerCase() === 'salir';
  }

  protected hasAction(): boolean {
    return this.isLogoutAction() || this.actionLabel().trim().length > 0;
  }

  @HostListener('document:click')
  protected onDocumentClick(): void {
    if (this.userMenuOpen()) {
      this.userMenuOpen.set(false);
    }
  }
}
