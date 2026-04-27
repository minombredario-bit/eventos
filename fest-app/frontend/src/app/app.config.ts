import {
  ApplicationConfig,
  LOCALE_ID,
  inject,
  provideZoneChangeDetection,
  isDevMode,
  provideEnvironmentInitializer,
} from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { clientPanelInterceptor } from './core/http/client-panel-interceptor';
import { registerLocaleData } from '@angular/common';
import localeEs from '@angular/common/locales/es';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { provideTranslateHttpLoader } from '@ngx-translate/http-loader';

import { routes } from './app.routes';
import { authInterceptor } from './core/auth/auth-interceptor';
import { ThemeService } from './core/theme/theme.service';
import { provideServiceWorker } from '@angular/service-worker';

registerLocaleData(localeEs);

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection(),
    provideRouter(routes),
    provideHttpClient(withInterceptors([authInterceptor, clientPanelInterceptor])),
    {
      provide: LOCALE_ID,
      useValue: 'es-ES',
    },
    provideEnvironmentInitializer(() => inject(ThemeService)),
    provideTranslateService({
      fallbackLang: 'es',
      loader: provideTranslateHttpLoader({
        prefix: '/assets/i18n/',
        suffix: '.json',
      }),
    }),
    provideServiceWorker('ngsw-worker.js', {
      enabled: !isDevMode(),
      registrationStrategy: 'registerWhenStable:30000',
    }),
  ],
};
