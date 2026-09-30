import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { CookieBannerComponent } from './shared/cookie-banner/cookie-banner.component';
import { ConfirmDeleteComponent } from './shared/confirm-delete/confirm-delete.component';
import { FooterComponent } from './shared/footer/footer.component';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, CookieBannerComponent, ConfirmDeleteComponent, FooterComponent],
  template: '<router-outlet /><app-footer /><app-cookie-banner /><app-confirm-delete />',
})
export class App {}
