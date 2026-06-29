import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter, withHashLocation } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { providePrimeNG } from 'primeng/config';
import Aura from '@primeng/themes/aura';

import { routes } from './app.routes';
import { jwtInterceptor } from './core/interceptors/jwt.interceptor';
import { errorInterceptor } from './core/interceptors/error.interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes, withHashLocation()),
    provideHttpClient(withInterceptors([jwtInterceptor, errorInterceptor])),
    provideAnimationsAsync(),
    providePrimeNG({
      ripple: true,
      translation: {
        accept: 'Tak',
        reject: 'Nie',
        cancel: 'Anuluj',
        clear: 'Wyczyść',
        apply: 'Zastosuj',
        today: 'Dziś',
        emptyMessage: 'Brak wyników',
        emptyFilterMessage: 'Brak wyników',
        aria: {
          trueLabel: 'Prawda',
          falseLabel: 'Fałsz',
          nullLabel: 'Nie wybrano',
          close: 'Zamknij',
          pageLabel: 'Strona',
          firstPageLabel: 'Pierwsza strona',
          lastPageLabel: 'Ostatnia strona',
          nextPageLabel: 'Następna strona',
          previousPageLabel: 'Poprzednia strona',
        },
      },
      theme: {
        preset: Aura,
        options: {
          darkModeSelector: 'body',
          cssLayer: { name: 'primeng', order: 'tailwind-base, primeng, tailwind-utilities' }
        }
      }
    }),
  ]
};
