import {
  ApplicationConfig,
  ENVIRONMENT_INITIALIZER,
  LOCALE_ID,
  inject,
  provideZoneChangeDetection,
  isDevMode,
  importProvidersFrom,
} from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { clientPanelInterceptor } from './core/http/client-panel-interceptor';
import { registerLocaleData } from '@angular/common';
import localeEs from '@angular/common/locales/es';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';
import { authInterceptor } from './core/auth/auth-interceptor';
import { ThemeService } from './core/theme/theme.service';
import { provideServiceWorker } from '@angular/service-worker';

import { TranslateModule } from '@ngx-translate/core';
import { provideTranslateHttpLoader } from '@ngx-translate/http-loader';

registerLocaleData(localeEs);

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection(),
    provideRouter(routes),

    provideHttpClient(
      withInterceptors([
        authInterceptor,
        clientPanelInterceptor,
      ])
    ),

    importProvidersFrom(
      TranslateModule.forRoot()
    ),

    provideTranslateHttpLoader({
      prefix: '/assets/i18n/',
      suffix: '.json',
    }),

    {
      provide: LOCALE_ID,
      useValue: 'es-ES',
    },
    {
      provide: ENVIRONMENT_INITIALIZER,
      multi: true,
      useValue: () => inject(ThemeService),
    },

    provideServiceWorker('ngsw-worker.js', {
      enabled: !isDevMode(),
      registrationStrategy: 'registerWhenStable:30000',
    }),
  ],
};
