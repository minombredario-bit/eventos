import { ChangeDetectionStrategy, Component, HostListener, inject, input, output, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth';

@Component({
  selector: 'app-mobile-header',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './mobile-header.html',
  styleUrl: './mobile-header.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MobileHeader {
  readonly lopdMode = input(false);

  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
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

  protected onUserMenuSelect(): void {
    this.userMenuOpen.set(false);
    if (this.isLogoutAction()) {
      this.onAction();
    }
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
    return this.isUserMenuEnabled() || this.actionLabel().trim().length > 0;
  }

  protected isUserMenuEnabled(): boolean {
    return this.isLogoutAction() || this.canOpenAdmin();
  }

  protected canOpenAdmin(): boolean {
    if (this.lopdMode()) return false;
    const roles = this.authService.userSignal()?.roles ?? [];
    return Array.isArray(roles) && (roles.includes('ROLE_ADMIN_ENTIDAD') || roles.includes('ROLE_SUPERADMIN'));
  }

  protected isAdminView(): boolean {
    return this.router.url.startsWith('/admin');
  }

  protected viewSwitchLabel(): string {
    return this.isAdminView() ? 'Vista usuario' : 'Administración';
  }

  protected viewSwitchRoute(): string {
    return this.isAdminView() ? '/eventos/inicio' : '/admin/dashboard';
  }

  @HostListener('document:click')
  protected onDocumentClick(): void {
    if (this.userMenuOpen()) {
      this.userMenuOpen.set(false);
    }
  }
}
