import { Component, effect, inject } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { RouterOutlet } from '@angular/router';
import { AuthStore } from './core/auth/auth-store';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss'
})
export class AppComponent {
  private readonly title = inject(Title);
  private readonly authStore = inject(AuthStore);

  constructor() {
    effect(() => {
      const user = this.authStore.user();
      const entidad = typeof user?.nombreEntidad === 'string' ? user.nombreEntidad.trim() : '';

      this.title.setTitle(entidad ? `FestApp - ${entidad}` : 'FestApp');
    });
  }
}

