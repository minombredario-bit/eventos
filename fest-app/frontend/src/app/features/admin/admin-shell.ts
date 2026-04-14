import { ChangeDetectionStrategy, Component, computed } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { BottomNav } from '../shared/components/bottom-nav/bottom-nav';
import { NavItem } from '../eventos/domain/eventos.models';

@Component({
  selector: 'app-admin-shell',
  standalone: true,
  imports: [RouterOutlet, BottomNav],
  templateUrl: './admin-shell.html',
  styleUrl: './admin-shell.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminShell {
  protected readonly navItems = computed<NavItem[]>(() => [
    { key: 'dashboard', label: 'Dashboard', icon: '📊', route: '/admin/dashboard' },
    { key: 'censo', label: 'Censo', icon: '👥', route: '/admin/censo-usuarios' },
  ]);
}

