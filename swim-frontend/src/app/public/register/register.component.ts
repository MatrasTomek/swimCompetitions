import { Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { InputText } from 'primeng/inputtext';
import { Textarea } from 'primeng/textarea';
import { Checkbox } from 'primeng/checkbox';
import { Button } from 'primeng/button';
import { Card } from 'primeng/card';
import { Message } from 'primeng/message';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { ContactRequest } from '../../core/models';

const EMPTY_FORM: ContactRequest = {
  imie: '', email: '', telefon: '', klub: '', zawodnicy: '', wiadomosc: '', zgoda: false, website: '',
};

@Component({
  selector: 'app-register',
  imports: [FormsModule, RouterLink, InputText, Textarea, Checkbox, Button, Card, Message, HeaderComponent],
  template: `
    <app-header />
    <div class="swim-page">
      <a routerLink="/admin/login" class="back">← Logowanie</a>
      <h1 class="swim-page-title">Rejestracja</h1>

      <p-card styleClass="register-card">
        @if (sent()) {
          <div class="sent">
            <i class="pi pi-check-circle"></i>
            <h2>Dziękujemy za zgłoszenie!</h2>
            <p>Otrzymaliśmy Twoje dane. Skontaktujemy się z Tobą na adres <strong>{{ sentTo() }}</strong>,
               aby dokończyć rejestrację konta.</p>
            <p-button label="Wróć do zawodów" severity="secondary" [outlined]="true" routerLink="/" />
          </div>
        } @else {
          <p class="intro">
            <i class="pi pi-lock"></i>
            Wyniki i statystyki zawodników to płatna opcja. Wypełnij formularz — skontaktujemy się z Tobą
            z informacją o warunkach i założymy konto.
          </p>

          <form #f="ngForm" (ngSubmit)="submit(f)" class="form" novalidate>
            <div class="row">
              <div class="field">
                <label for="imie">Imię i nazwisko *</label>
                <input pInputText id="imie" name="imie" [(ngModel)]="form.imie" required maxlength="100" autocomplete="name" #imie="ngModel" />
                @if (imie.invalid && (imie.touched || f.submitted)) { <small class="err">Podaj imię i nazwisko.</small> }
              </div>
              <div class="field">
                <label for="email">E-mail *</label>
                <input pInputText id="email" name="email" type="email" [(ngModel)]="form.email" required email maxlength="150" autocomplete="email" #email="ngModel" />
                @if (email.invalid && (email.touched || f.submitted)) { <small class="err">Podaj poprawny adres e-mail.</small> }
              </div>
            </div>

            <div class="row">
              <div class="field">
                <label for="telefon">Telefon</label>
                <input pInputText id="telefon" name="telefon" type="tel" [(ngModel)]="form.telefon" maxlength="30" pattern="[0-9 +()\\-]{6,30}" autocomplete="tel" #tel="ngModel" />
                @if (tel.invalid && (tel.touched || f.submitted)) { <small class="err">Podaj poprawny numer telefonu.</small> }
              </div>
              <div class="field">
                <label for="klub">Klub</label>
                <input pInputText id="klub" name="klub" [(ngModel)]="form.klub" maxlength="150" autocomplete="organization" />
              </div>
            </div>

            <div class="field">
              <label for="zawodnicy">Zawodnicy</label>
              <textarea pTextarea id="zawodnicy" name="zawodnicy" [(ngModel)]="form.zawodnicy" rows="3" maxlength="1000"
                        placeholder="Imiona i nazwiska zawodników, których wyniki chcesz śledzić (np. rocznik)"></textarea>
            </div>

            <div class="field">
              <label for="wiadomosc">Wiadomość</label>
              <textarea pTextarea id="wiadomosc" name="wiadomosc" [(ngModel)]="form.wiadomosc" rows="4" maxlength="2000"></textarea>
            </div>

            <!-- Honeypot: hidden from people, filled in only by spam bots -->
            <div class="hp" aria-hidden="true">
              <label for="website">Strona www</label>
              <input id="website" name="website" [(ngModel)]="form.website" tabindex="-1" autocomplete="off" />
            </div>

            <div class="consent">
              <p-checkbox inputId="zgoda" name="zgoda" [(ngModel)]="form.zgoda" [binary]="true" required #zgoda="ngModel" />
              <label for="zgoda">
                Wyrażam zgodę na przetwarzanie podanych danych w celu obsługi zgłoszenia i kontaktu w sprawie rejestracji.
                <a routerLink="/rodo" target="_blank" class="rodo-link">Informacja o sposobie przetwarzania danych</a> *
              </label>
            </div>
            @if (zgoda.invalid && f.submitted) { <small class="err">Zgoda jest wymagana.</small> }

            @if (error()) {
              <p-message severity="error" [text]="error()!" />
            }

            <div class="actions">
              <p-button type="submit" label="Wyślij zgłoszenie" icon="pi pi-send" [loading]="loading()" />
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
    .field input, .field textarea { width: 100%; }
    .field textarea { resize: vertical; }
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

  form: ContactRequest = { ...EMPTY_FORM };
  loading = signal(false);
  error   = signal<string | null>(null);
  sent    = signal(false);
  sentTo  = signal('');

  submit(f: NgForm) {
    if (f.invalid || !this.form.zgoda) return;
    this.loading.set(true);
    this.error.set(null);
    this.api.sendContact(this.form).subscribe({
      next: () => {
        this.loading.set(false);
        this.sentTo.set(this.form.email);
        this.sent.set(true);
        this.form = { ...EMPTY_FORM };
      },
      error: err => {
        this.loading.set(false);
        this.error.set(err.error?.error ?? 'Nie udało się wysłać zgłoszenia. Spróbuj ponownie później.');
      },
    });
  }
}
