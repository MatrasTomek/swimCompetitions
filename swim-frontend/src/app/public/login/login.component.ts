import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { InputText } from 'primeng/inputtext';
import { Password } from 'primeng/password';
import { Button } from 'primeng/button';
import { Card } from 'primeng/card';
import { Message } from 'primeng/message';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  imports: [FormsModule, RouterLink, InputText, Password, Button, Card, Message],
  template: `
    <div class="login-page">
      <p-card header="Wyniki i Statystyki" styleClass="login-card">
        <form (ngSubmit)="submit()" class="login-form">
          <div class="field">
            <label for="username">E-mail</label>
            <input pInputText id="username" [(ngModel)]="username" name="username" autocomplete="username" />
          </div>
          <div class="field">
            <label for="password">Hasło</label>
            <p-password inputId="password" [(ngModel)]="password" name="password" [feedback]="false" [toggleMask]="true" autocomplete="current-password" />
            <a routerLink="/konto/zapomniane-haslo" class="forgot">Nie pamiętasz hasła?</a>
          </div>
          @if (error()) {
            <p-message [severity]="pending() ? 'warn' : 'error'" [text]="error()!" />
          }
          <p-button type="submit" label="Zaloguj się" [loading]="loading()" styleClass="w-full" />
        </form>
        <div class="register">
          <p class="register-info">
            <i class="pi pi-lock"></i>
            Wyniki i statystyki zawodników to płatna opcja — dostęp wymaga rejestracji konta.
            Nie masz jeszcze konta? Zarejestruj się, aby śledzić wyniki i postępy swoich zawodników.
          </p>
          <a routerLink="/rejestracja" class="register-link">
            <p-button type="button" label="Rejestracja" icon="pi pi-user-plus" severity="secondary" [outlined]="true" styleClass="w-full" />
          </a>
        </div>
      </p-card>
    </div>
  `,
  styles: [`
    .login-page  { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--swim-dark); }
    .login-card  { width: 360px; background: var(--swim-card) !important; }
    .login-card ::ng-deep .p-card-title { color: var(--swim-gold); text-align: center; }
    .login-form  { display: flex; flex-direction: column; gap: 1rem; }
    .field       { display: flex; flex-direction: column; gap: .4rem; }
    .field label { font-size: .85rem; color: var(--swim-muted); }
    .forgot      { align-self: flex-end; font-size: .8rem; color: var(--swim-gold); text-decoration: none; }
    .forgot:hover { text-decoration: underline; }
    .register      { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--swim-border); display: flex; flex-direction: column; gap: .9rem; }
    .register-info { margin: 0; font-size: .85rem; line-height: 1.45; color: var(--swim-muted); text-align: center; }
    .register-info .pi { color: var(--swim-gold); margin-right: .3rem; }
    .register-link { display: block; text-decoration: none; }
    input[pinputtext], ::ng-deep .p-password input { width: 100%; }
  `]
})
export class LoginComponent {
  private auth   = inject(AuthService);
  private router = inject(Router);

  username = '';
  password = '';
  loading  = signal(false);
  error    = signal<string | null>(null);
  /** Correct password, but the account is not active yet (e-mail not confirmed / waiting for the admin). */
  pending  = signal(false);

  constructor() {
    if (this.auth.isLoggedIn()) this.router.navigateByUrl(this.auth.homeUrl());
  }

  submit() {
    if (!this.username || !this.password) return;
    this.loading.set(true);
    this.error.set(null);
    this.auth.login(this.username.trim(), this.password).subscribe({
      next: () => this.router.navigateByUrl(this.auth.homeUrl()),
      error: err => {
        this.loading.set(false);
        this.pending.set(err.status === 403);
        this.error.set(err.error?.error ?? 'Błąd logowania.');
      },
    });
  }
}
