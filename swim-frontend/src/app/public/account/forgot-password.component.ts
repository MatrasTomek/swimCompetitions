import { Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Card } from 'primeng/card';
import { InputText } from 'primeng/inputtext';
import { Button } from 'primeng/button';
import { Message } from 'primeng/message';
import { ApiService } from '../../core/services/api.service';
import { ACCOUNT_CARD_STYLES } from './account-card.styles';

/** /konto/zapomniane-haslo — sends a password reset link. */
@Component({
  selector: 'app-forgot-password',
  imports: [FormsModule, RouterLink, Card, InputText, Button, Message],
  template: `
    <div class="acc-page">
      <p-card header="Nie pamiętasz hasła?" styleClass="acc-card">
        @if (sent()) {
          <div class="done">
            <i class="pi pi-envelope"></i>
            <p>Jeśli konto <strong>{{ email }}</strong> istnieje, wysłaliśmy na nie link do ustawienia nowego hasła.
               Link jest ważny 1 godzinę.</p>
          </div>
        } @else {
          <p class="intro">Podaj adres e-mail konta — wyślemy link do ustawienia nowego hasła.</p>
          <form #f="ngForm" (ngSubmit)="submit(f)" class="acc-form" novalidate>
            <div class="field">
              <label for="email">E-mail</label>
              <input pInputText id="email" name="email" type="email" [(ngModel)]="email" required email autocomplete="email" #em="ngModel" />
              @if (em.invalid && (em.touched || f.submitted)) { <small class="err">Podaj poprawny adres e-mail.</small> }
            </div>
            @if (error()) { <p-message severity="error" [text]="error()!" /> }
            <p-button type="submit" label="Wyślij link" icon="pi pi-send" [loading]="loading()" styleClass="w-full" />
          </form>
        }
        <div class="links"><a routerLink="/logowanie">← Logowanie</a></div>
      </p-card>
    </div>
  `,
  styles: [ACCOUNT_CARD_STYLES],
})
export class ForgotPasswordComponent {
  private api = inject(ApiService);

  email   = '';
  loading = signal(false);
  error   = signal<string | null>(null);
  sent    = signal(false);

  submit(f: NgForm) {
    if (f.invalid) return;
    this.loading.set(true);
    this.error.set(null);
    this.email = this.email.trim();
    this.api.forgotPassword(this.email).subscribe({
      next: () => { this.loading.set(false); this.sent.set(true); },
      error: err => {
        this.loading.set(false);
        this.error.set(err.error?.error ?? 'Nie udało się wysłać linku. Spróbuj ponownie później.');
      },
    });
  }
}
