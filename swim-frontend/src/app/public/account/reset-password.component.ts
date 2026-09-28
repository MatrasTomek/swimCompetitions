import { Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Card } from 'primeng/card';
import { Password } from 'primeng/password';
import { Button } from 'primeng/button';
import { Message } from 'primeng/message';
import { ApiService } from '../../core/services/api.service';
import { PASSWORD_MIN } from '../../shared/password';
import { ACCOUNT_CARD_STYLES } from './account-card.styles';

/** /konto/reset-hasla?token=… — link from the password reset e-mail. */
@Component({
  selector: 'app-reset-password',
  imports: [FormsModule, RouterLink, Card, Password, Button, Message],
  template: `
    <div class="acc-page">
      <p-card header="Nowe hasło" styleClass="acc-card">
        @if (done()) {
          <div class="done">
            <i class="pi pi-check-circle"></i>
            <p>Hasło zostało zmienione. Zaloguj się nowym hasłem.</p>
          </div>
        } @else {
          <form #f="ngForm" (ngSubmit)="submit(f)" class="acc-form" novalidate>
            <div class="field">
              <label for="password">Nowe hasło</label>
              <p-password inputId="password" name="password" [(ngModel)]="password" required [minlength]="minPassword"
                [toggleMask]="true" autocomplete="new-password" promptLabel="Min. {{ minPassword }} znaków"
                weakLabel="Słabe" mediumLabel="Średnie" strongLabel="Silne" #pwd="ngModel" />
              @if (pwd.invalid && (pwd.touched || f.submitted)) { <small class="err">Hasło musi mieć co najmniej {{ minPassword }} znaków.</small> }
            </div>
            <div class="field">
              <label for="password2">Powtórz hasło</label>
              <p-password inputId="password2" name="password2" [(ngModel)]="password2" required [feedback]="false"
                [toggleMask]="true" autocomplete="new-password" #pwd2="ngModel" />
              @if (password2 !== password && (pwd2.touched || f.submitted)) { <small class="err">Hasła nie są identyczne.</small> }
            </div>
            @if (error()) { <p-message severity="error" [text]="error()!" /> }
            <p-button type="submit" label="Ustaw hasło" icon="pi pi-check" [loading]="loading()" styleClass="w-full" />
          </form>
        }
        <div class="links">
          <a routerLink="/logowanie">← Logowanie</a>
          @if (error()) { · <a routerLink="/konto/zapomniane-haslo">Wyślij nowy link</a> }
        </div>
      </p-card>
    </div>
  `,
  styles: [ACCOUNT_CARD_STYLES],
})
export class ResetPasswordComponent {
  private api   = inject(ApiService);
  private route = inject(ActivatedRoute);

  readonly minPassword = PASSWORD_MIN;
  password  = '';
  password2 = '';
  loading   = signal(false);
  error     = signal<string | null>(null);
  done      = signal(false);

  submit(f: NgForm) {
    if (f.invalid || this.password !== this.password2) return;
    const token = this.route.snapshot.queryParamMap.get('token') ?? '';
    this.loading.set(true);
    this.error.set(null);
    this.api.resetPassword(token, this.password).subscribe({
      next: () => { this.loading.set(false); this.done.set(true); },
      error: err => {
        this.loading.set(false);
        this.error.set(err.error?.error ?? 'Nie udało się zmienić hasła. Spróbuj ponownie później.');
      },
    });
  }
}
