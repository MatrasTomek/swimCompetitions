import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { Card } from 'primeng/card';
import { InputText } from 'primeng/inputtext';
import { Password } from 'primeng/password';
import { Button } from 'primeng/button';
import { Message } from 'primeng/message';
import { Dialog } from 'primeng/dialog';
import { Toast } from 'primeng/toast';
import { ProgressSpinner } from 'primeng/progressspinner';
import { MessageService } from 'primeng/api';
import { HeaderComponent } from '../shared/header/header.component';
import { PASSWORD_MIN } from '../shared/password';
import { plural } from '../shared/plural';
import { ApiService } from '../core/services/api.service';
import { AuthService } from '../core/services/auth.service';
import { Account, UserInvoice } from '../core/models';
import { EMPTY_INVOICE, InvoiceFieldsComponent, invoiceValid } from '../shared/invoice-fields/invoice-fields.component';

/** /konto — club user's account: club name, invoice details, password change, account removal. */
@Component({
  selector: 'app-account',
  imports: [FormsModule, DatePipe, RouterLink, Card, InputText, Password, Button, Message, Dialog, Toast, ProgressSpinner, HeaderComponent, InvoiceFieldsComponent],
  providers: [MessageService],
  template: `
    <app-header />
    <p-toast />
    <div class="swim-page">
      <h1 class="swim-page-title">Moje konto</h1>

      @if (loadError()) {
        <p-message severity="error" [text]="loadError()!" />
      } @else if (!account()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else {
        <div class="grid">
          <p-card header="Dane konta" styleClass="acc-card">
            <dl class="info">
              <dt>E-mail (login)</dt><dd>{{ account()!.userEmail }}</dd>
              <dt>Konto od</dt><dd>{{ account()!.createdAt | date:'dd.MM.yyyy' }}</dd>
              <dt>Zawodnicy</dt>
              <dd><a routerLink="/konto/zawodnicy">{{ membersLabel(account()!.clubItems.clubMembers.length) }} →</a></dd>
            </dl>
            <form #cf="ngForm" (ngSubmit)="saveClub(cf)" class="form" novalidate>
              <div class="field">
                <label for="club">Klub</label>
                <input pInputText id="club" name="club" [(ngModel)]="club" required maxlength="150" autocomplete="organization" />
              </div>
              <p-button type="submit" label="Zapisz" icon="pi pi-save" [loading]="savingClub()"
                [disabled]="!club.trim() || club.trim() === account()!.userClub" />
            </form>
          </p-card>

          <p-card header="Dane do faktury" styleClass="acc-card">
            @if (!account()!.userInvoice) {
              <p-message severity="warn" text="Uzupełnij dane do faktury — są potrzebne do rozliczenia konta." styleClass="inv-warn" />
            }
            <form #invf="ngForm" (ngSubmit)="saveInvoice(invf)" class="form" novalidate>
              <app-invoice-fields [value]="invoice" [submitted]="invf.submitted" />
              <p-button type="submit" label="Zapisz" icon="pi pi-save" [loading]="savingInvoice()" />
            </form>
          </p-card>

          <p-card header="Zmiana hasła" styleClass="acc-card">
            <form #pf="ngForm" (ngSubmit)="changePassword(pf)" class="form" novalidate>
              <div class="field">
                <label for="current">Obecne hasło</label>
                <p-password inputId="current" name="current" [(ngModel)]="current" required [feedback]="false" [toggleMask]="true" autocomplete="current-password" />
              </div>
              <div class="field">
                <label for="newPwd">Nowe hasło</label>
                <p-password inputId="newPwd" name="newPwd" [(ngModel)]="newPwd" required [minlength]="minPassword" [toggleMask]="true"
                  autocomplete="new-password" promptLabel="Min. {{ minPassword }} znaków" weakLabel="Słabe" mediumLabel="Średnie" strongLabel="Silne" #np="ngModel" />
                @if (np.invalid && (np.touched || pf.submitted)) { <small class="err">Hasło musi mieć co najmniej {{ minPassword }} znaków.</small> }
              </div>
              <div class="field">
                <label for="newPwd2">Powtórz nowe hasło</label>
                <p-password inputId="newPwd2" name="newPwd2" [(ngModel)]="newPwd2" required [feedback]="false" [toggleMask]="true" autocomplete="new-password" #np2="ngModel" />
                @if (newPwd2 !== newPwd && (np2.touched || pf.submitted)) { <small class="err">Hasła nie są identyczne.</small> }
              </div>
              @if (pwdError()) { <p-message severity="error" [text]="pwdError()!" /> }
              <small class="hint">Po zmianie hasła pozostałe urządzenia zostaną wylogowane.</small>
              <p-button type="submit" label="Zmień hasło" icon="pi pi-key" [loading]="savingPwd()" />
            </form>
          </p-card>

          <p-card header="Usunięcie konta" styleClass="acc-card danger">
            <p class="hint">Usuwa konto wraz ze wszystkimi zawodnikami i ich wynikami. Tej operacji nie można cofnąć.</p>
            <p-button label="Usuń konto" icon="pi pi-trash" severity="danger" [outlined]="true" (onClick)="deleteVisible = true" />
          </p-card>
        </div>
      }
    </div>

    <p-dialog header="Usuń konto" [(visible)]="deleteVisible" [modal]="true" [style]="{width:'420px'}" [breakpoints]="{'640px':'95vw'}">
      <form (ngSubmit)="deleteAccount()" class="form">
        <p class="hint">Aby potwierdzić, podaj hasło do konta.</p>
        <div class="field">
          <label for="delPwd">Hasło</label>
          <p-password inputId="delPwd" name="delPwd" [(ngModel)]="deletePwd" [feedback]="false" [toggleMask]="true" autocomplete="current-password" />
        </div>
        @if (deleteError()) { <p-message severity="error" [text]="deleteError()!" /> }
        <div class="dialog-actions">
          <p-button type="button" label="Anuluj" severity="secondary" [outlined]="true" (onClick)="deleteVisible = false" />
          <p-button type="submit" label="Usuń konto" icon="pi pi-trash" severity="danger" [loading]="deleting()" [disabled]="!deletePwd" />
        </div>
      </form>
    </p-dialog>
  `,
  styles: [`
    .grid        { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; align-items: start; }
    :host ::ng-deep .acc-card { background: var(--swim-card) !important; }
    :host ::ng-deep .acc-card .p-card-title { color: var(--swim-gold); font-size: 1.05rem; }
    :host ::ng-deep .acc-card.danger .p-card-title { color: #f44336; }
    .info        { display: grid; grid-template-columns: auto 1fr; gap: .4rem 1rem; margin: 0 0 1.25rem; font-size: .9rem; }
    .info dt     { color: var(--swim-muted); }
    .info dd     { margin: 0; overflow-wrap: anywhere; color: var(--swim-text); }
    .info a      { color: var(--swim-gold); text-decoration: none; }
    .form        { display: flex; flex-direction: column; gap: 1rem; align-items: flex-start; }
    .field       { display: flex; flex-direction: column; gap: .4rem; width: 100%; }
    .field label { font-size: .85rem; color: var(--swim-muted); }
    .field input, .field ::ng-deep .p-password, .field ::ng-deep .p-password input { width: 100%; }
    .err         { color: #f44336; font-size: .78rem; }
    .hint        { color: var(--swim-muted); font-size: .8rem; line-height: 1.45; margin: 0 0 1rem; }
    .form .hint  { margin: 0; }
    .dialog-actions { display: flex; justify-content: flex-end; gap: .5rem; width: 100%; }
    :host ::ng-deep .inv-warn { margin-bottom: 1rem; }
    .center-spin { display: flex; justify-content: center; padding: 3rem; }
  `]
})
export class AccountComponent implements OnInit {
  private api      = inject(ApiService);
  private auth     = inject(AuthService);
  private router   = inject(Router);
  private messages = inject(MessageService);

  readonly minPassword = PASSWORD_MIN;
  account    = signal<Account | null>(null);
  loadError  = signal<string | null>(null);

  club       = '';
  savingClub = signal(false);

  invoice: UserInvoice = { ...EMPTY_INVOICE };
  savingInvoice = signal(false);

  current    = '';
  newPwd     = '';
  newPwd2    = '';
  savingPwd  = signal(false);
  pwdError   = signal<string | null>(null);

  deleteVisible = false;
  deletePwd     = '';
  deleting      = signal(false);
  deleteError   = signal<string | null>(null);

  ngOnInit() {
    this.api.getAccount().subscribe({
      next: a => { this.account.set(a); this.club = a.userClub; this.invoice = { ...(a.userInvoice ?? EMPTY_INVOICE) }; },
      error: err => this.loadError.set(err.error?.error ?? 'Nie udało się wczytać konta.'),
    });
  }

  membersLabel(n: number): string {
    return `${n} ${plural(n, 'zawodnik', 'zawodników', 'zawodników')}`;
  }

  saveClub(f: NgForm) {
    if (f.invalid || !this.club.trim()) return;
    this.savingClub.set(true);
    this.api.updateAccount({ userClub: this.club.trim() }).subscribe({
      next: a => {
        this.savingClub.set(false);
        this.account.set(a);
        this.club = a.userClub;
        this.messages.add({ severity: 'success', summary: 'Zapisano', detail: 'Nazwa klubu została zmieniona.' });
      },
      error: err => {
        this.savingClub.set(false);
        this.messages.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się zapisać.' });
      },
    });
  }

  saveInvoice(f: NgForm) {
    if (f.invalid || !invoiceValid(this.invoice)) return;
    this.savingInvoice.set(true);
    this.api.updateAccount({ userInvoice: this.invoice }).subscribe({
      next: a => {
        this.savingInvoice.set(false);
        this.account.set(a);
        this.invoice = { ...a.userInvoice! };
        this.messages.add({ severity: 'success', summary: 'Zapisano', detail: 'Dane do faktury zostały zapisane.' });
      },
      error: err => {
        this.savingInvoice.set(false);
        this.messages.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się zapisać.' });
      },
    });
  }

  changePassword(f: NgForm) {
    if (f.invalid || this.newPwd !== this.newPwd2) return;
    this.savingPwd.set(true);
    this.pwdError.set(null);
    this.api.changePassword(this.current, this.newPwd).subscribe({
      next: token => {
        this.auth.setSession(token);
        this.savingPwd.set(false);
        f.resetForm();
        this.messages.add({ severity: 'success', summary: 'Hasło zmienione', detail: 'Pozostałe urządzenia zostały wylogowane.' });
      },
      error: err => {
        this.savingPwd.set(false);
        this.pwdError.set(err.error?.error ?? 'Nie udało się zmienić hasła.');
      },
    });
  }

  deleteAccount() {
    if (!this.deletePwd) return;
    this.deleting.set(true);
    this.deleteError.set(null);
    this.api.deleteAccount(this.deletePwd).subscribe({
      next: () => {
        this.auth.clearSession();
        this.router.navigate(['/']);
      },
      error: err => {
        this.deleting.set(false);
        this.deleteError.set(err.error?.error ?? 'Nie udało się usunąć konta.');
      },
    });
  }
}
