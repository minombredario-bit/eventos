import { CommonModule } from '@angular/common';
import { Component, effect, inject, OnInit } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { RouterOutlet } from '@angular/router';
import { AuthStore } from './core/auth/auth-store';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, RouterOutlet],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss'
})
export class AppComponent implements OnInit {
  private readonly title = inject(Title);
  private readonly authStore = inject(AuthStore);

  loading = true;

  constructor() {
    effect(() => {
      const user = this.authStore.user();
      const entidad = typeof user?.nombreEntidad === 'string' ? user.nombreEntidad.trim() : '';

      this.title.setTitle(entidad ? `FestApp - ${entidad}` : 'FestApp');
    });
  }

  ngOnInit(): void {
    setTimeout(() => {
      this.loading = false;
    }, 1200);
  }
}
