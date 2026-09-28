import { Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { InputText } from 'primeng/inputtext';
import { Password } from 'primeng/password';
import { Checkbox } from 'primeng/checkbox';
import { Button } from 'primeng/button';
import { Card } from 'primeng/card';
import { Message } from 'primeng/message';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { RegisterRequest } from '../../core/models';
import { PASSWORD_MIN } from '../../shared/password';

const EMPTY_FORM: RegisterRequest = { email: '', password: '', userClub: '', zgoda: false, website: '' };

@Component({
  selector: 'app-register',
  imports: [FormsModule, RouterLink, InputText, Password, Checkbox, Button, Card, Message, HeaderComponent],
  template: `
    <app-header />
    <div class="swim-page">
      <a routerLink="/logowanie" class="back">← Logowanie</a>
      <h1 class="swim-page-title">Rejestracja</h1>

      <p-card styleClass="register-card">
        @if (sent()) {
          <div class="sent">
            <i class="pi pi-envelope"></i>
            <h2>Sprawdź skrzynkę e-mail</h2>
            <p>Jeśli adres <strong>{{ sentTo() }}</strong> nie był jeszcze zarejestrowany, wysłaliśmy na niego link
               potwierdzający. Po potwierdzeniu adresu konto zostanie aktywowane przez administratora
               — poinformujemy Cię o tym e-mailem.</p>
            <p-button label="Wróć do zawodów" severity="secondary" [outlined]="true" routerLink="/" />
          </div>
        } @else {
          <p class="intro">
            <i class="pi pi-lock"></i>
            Wyniki i statystyki zawodników to płatna opcja. Załóż konto — po potwierdzeniu adresu e-mail
            skontaktujemy się z Tobą w sprawie warunków i aktywujemy konto.
          </p>

          <form #f="ngForm" (ngSubmit)="submit(f)" class="form" novalidate>
            <div class="field">
              <label for="email">E-mail (login) *</label>
              <input pInputText id="email" name="email" type="email" [(ngModel)]="form.email" required email maxlength="150" autocomplete="email" #email="ngModel" />
              @if (email.invalid && (email.touched || f.submitted)) { <small class="err">Podaj poprawny adres e-mail.</small> }
            </div>

            <div class="row">
              <div class="field">
                <label for="password">Hasło *</label>
                <p-password inputId="password" name="password" [(ngModel)]="form.password" required [minlength]="minPassword"
                  [toggleMask]="true" autocomplete="new-password" promptLabel="Min. {{ minPassword }} znaków"
                  weakLabel="Słabe" mediumLabel="Średnie" strongLabel="Silne" #pwd="ngModel" />
                @if (pwd.invalid && (pwd.touched || f.submitted)) { <small class="err">Hasło musi mieć co najmniej {{ minPassword }} znaków.</small> }
              </div>
              <div class="field">
                <label for="password2">Powtórz hasło *</label>
                <p-password inputId="password2" name="password2" [(ngModel)]="password2" required [feedback]="false"
                  [toggleMask]="true" autocomplete="new-password" #pwd2="ngModel" />
                @if (password2 !== form.password && (pwd2.touched || f.submitted)) { <small class="err">Hasła nie są identyczne.</small> }
              </div>
            </div>

            <div class="field">
              <label for="klub">Klub *</label>
              <input pInputText id="klub" name="klub" [(ngModel)]="form.userClub" required maxlength="150" autocomplete="organization" #klub="ngModel" />
              @if (klub.invalid && (klub.touched || f.submitted)) { <small class="err">Podaj nazwę klubu.</small> }
            </div>

            <!-- Honeypot: hidden from people, filled in only by spam bots -->
            <div class="hp" aria-hidden="true">
              <label for="website">Strona www</label>
              <input id="website" name="website" [(ngModel)]="form.website" tabindex="-1" autocomplete="off" />
            </div>

            <div class="consent">
              <p-checkbox inputId="zgoda" name="zgoda" [(ngModel)]="form.zgoda" [binary]="true" required #zgoda="ngModel" />
              <label for="zgoda">
                Wyrażam zgodę na przetwarzanie podanych danych w celu założenia i obsługi konta.
                <a routerLink="/rodo" target="_blank" class="rodo-link">Informacja o sposobie przetwarzania danych</a> *
              </label>
            </div>
            @if (zgoda.invalid && f.submitted) { <small class="err">Zgoda jest wymagana.</small> }

            @if (error()) {
              <p-message severity="error" [text]="error()!" />
            }

            <div class="actions">
              <p-button type="submit" label="Załóż konto" icon="pi pi-user-plus" [loading]="loading()" />
              <small class="hint">* pola wymagane</small>
            </div>
          </form>
        }
      </p-card>
    </div>
  `,
  styles: [`
    .back          { display: inline-block; margin-bottom: 1rem; color: var(--swim-muted); text-decoration: none; font-size: .85rem; }
    .register-card { max-width: 650px; background: var(--swim-card) !important; }
    .intro         { margin: 0 0 1.5rem; font-size: .85rem; line-height: 1.5; color: var(--swim-muted); }
    .intro .pi     { color: var(--swim-gold); margin-right: .3rem; }
    .form          { display: flex; flex-direction: column; gap: 1.25rem; }
    .row           { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
    .field         { display: flex; flex-direction: column; gap: .4rem; min-width: 0; }
    .field label   { font-size: .85rem; color: var(--swim-muted); }
    .field input, .field ::ng-deep .p-password, .field ::ng-deep .p-password input { width: 100%; }
    .consent       { display: flex; align-items: flex-start; gap: .6rem; }
    .consent label { font-size: .8rem; line-height: 1.45; color: var(--swim-muted); cursor: pointer; }
    .rodo-link     { color: var(--swim-gold); }
    .err           { color: #f44336; font-size: .78rem; }
    .hint          { font-size: .78rem; color: var(--swim-muted); }
    .actions       { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .hp            { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }

    .sent          { text-align: center; padding: 1rem 0; }
    .sent .pi      { font-size: 2.5rem; color: var(--swim-gold); }
    .sent h2       { color: var(--swim-gold); margin: .75rem 0 .5rem; font-size: 1.25rem; }
    .sent p        { color: var(--swim-muted); font-size: .9rem; line-height: 1.5; margin: 0 0 1.25rem; }

    @media (max-width: 600px) {
      .row { grid-template-columns: 1fr; }
    }
  `]
})
export class RegisterComponent {
  private api = inject(ApiService);

  readonly minPassword = PASSWORD_MIN;
  form: RegisterRequest = { ...EMPTY_FORM };
  password2 = '';
  loading = signal(false);
  error   = signal<string | null>(null);
  sent    = signal(false);
  sentTo  = signal('');

  submit(f: NgForm) {
    if (f.invalid || !this.form.zgoda || this.password2 !== this.form.password) return;
    this.loading.set(true);
    this.error.set(null);
    this.api.register({ ...this.form, email: this.form.email.trim() }).subscribe({
      next: () => {
        this.loading.set(false);
        this.sentTo.set(this.form.email);
        this.sent.set(true);
        this.form = { ...EMPTY_FORM };
        this.password2 = '';
      },
      error: err => {
        this.loading.set(false);
        this.error.set(err.error?.error ?? 'Nie udało się założyć konta. Spróbuj ponownie później.');
      },
    });
  }
}
