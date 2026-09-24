import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { CookieBannerComponent } from './shared/cookie-banner/cookie-banner.component';
import { ConfirmDeleteComponent } from './shared/confirm-delete/confirm-delete.component';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, CookieBannerComponent, ConfirmDeleteComponent],
  template: '<router-outlet /><app-cookie-banner /><app-confirm-delete />',
})
export class App {}
