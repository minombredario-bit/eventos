import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { BottomNav } from '../shared/components/bottom-nav/bottom-nav';
import { BOTTOM_NAV_ITEMS } from './data/mock';

@Component({
  selector: 'app-eventos',
  standalone: true,
  imports: [RouterOutlet, BottomNav],
  templateUrl: './eventos.html',
  styleUrl: './eventos.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Eventos {
  protected readonly navItems = BOTTOM_NAV_ITEMS;
}
