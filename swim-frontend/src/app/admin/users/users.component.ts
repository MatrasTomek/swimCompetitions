import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { TableModule } from 'primeng/table';
import { Button } from 'primeng/button';
import { Tag } from 'primeng/tag';
import { Toast } from 'primeng/toast';
import { ProgressSpinner } from 'primeng/progressspinner';
import { MessageService } from 'primeng/api';
import { HeaderComponent } from '../../shared/header/header.component';
import { SearchInputComponent } from '../../shared/search-input/search-input.component';
import { plural } from '../../shared/plural';
import { ApiService } from '../../core/services/api.service';
import { AccountStatus, AccountSummary } from '../../core/models';

const STATUS: Record<AccountStatus, { label: string; severity: 'success' | 'warn' | 'secondary' | 'danger' }> = {
  pending_email:    { label: 'Niepotwierdzony e-mail', severity: 'secondary' },
  pending_approval: { label: 'Czeka na aktywację',     severity: 'warn' },
  active:           { label: 'Aktywne',                severity: 'success' },
  disabled:         { label: 'Zablokowane',            severity: 'danger' },
};

/** /admin/uzytkownicy — club user accounts: activate after payment, block. */
@Component({
  selector: 'app-users',
  imports: [DatePipe, TableModule, Button, Tag, Toast, ProgressSpinner, HeaderComponent, SearchInputComponent],
  providers: [MessageService],
  template: `
    <app-header [isAdmin]="true" />
    <p-toast />
    <div class="swim-page">
      <h1 class="swim-page-title">Konta użytkowników</h1>

      @if (loadError()) {
        <p class="load-error">⚠ {{ loadError() }}</p>
      } @else if (!loaded()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else {
        <div class="search-row">
          <app-search-input class="search-box" placeholder="Szukaj po e-mailu lub klubie..." [(value)]="query"
            [badge]="accountsLabel(filtered().length)" />
          @if (pendingCount()) {
            <span class="pending"><i class="pi pi-bell"></i> {{ pendingCount() }} {{ pendingLabel(pendingCount()) }}</span>
          }
        </div>

        <p-table [value]="filtered()" dataKey="userId" [tableStyle]="{'min-width':'960px'}" styleClass="swim-datatable" [paginator]="filtered().length > 50" [rows]="50">
          <ng-template pTemplate="header">
            <tr><th>E-mail</th><th>Klub</th><th>Status</th><th>Zawodników</th><th>Utworzone</th><th>Ostatnie logowanie</th><th></th></tr>
          </ng-template>
          <ng-template pTemplate="body" let-u>
            <tr>
              <td class="email">{{ u.userEmail }}</td>
              <td>{{ u.userClub }}</td>
              <td class="nowrap"><p-tag [value]="status(u).label" [severity]="status(u).severity" /></td>
              <td>{{ u.memberCount }}</td>
              <td class="nowrap">{{ u.createdAt | date:'dd.MM.yyyy HH:mm' }}</td>
              <td class="nowrap">{{ u.lastLoginAt ? (u.lastLoginAt | date:'dd.MM.yyyy HH:mm') : '—' }}</td>
              <td class="actions-col">
                @if (u.status === 'pending_approval' || u.status === 'disabled') {
                  <p-button label="Aktywuj" icon="pi pi-check" size="small" [loading]="busy() === u.userId" (onClick)="setStatus(u, 'active')" />
                }
                @if (u.status === 'active' || u.status === 'pending_approval') {
                  <p-button label="Zablokuj" icon="pi pi-ban" size="small" severity="danger" [outlined]="true"
                    [loading]="busy() === u.userId" (onClick)="setStatus(u, 'disabled')" />
                }
              </td>
            </tr>
          </ng-template>
          <ng-template pTemplate="emptymessage">
            <tr><td colspan="7" class="empty">Brak kont.</td></tr>
          </ng-template>
        </p-table>
      }
    </div>
  `,
  styles: [`
    .search-row  { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .search-box  { flex: 1; min-width: 220px; max-width: 420px; }
    .pending     { color: var(--swim-gold); font-size: .85rem; }
    .email       { white-space: nowrap; }
    .nowrap      { white-space: nowrap; }
    .actions-col { text-align: right; white-space: nowrap; }
    .actions-col p-button + p-button { margin-left: .4rem; }
    .empty       { text-align: center; color: var(--swim-muted); padding: 1.5rem; }
    .center-spin { display: flex; justify-content: center; padding: 3rem; }
    .load-error  { color: #f44336; }
  `]
})
export class UsersComponent implements OnInit {
  private api      = inject(ApiService);
  private messages = inject(MessageService);

  users     = signal<AccountSummary[]>([]);
  loaded    = signal(false);
  loadError = signal<string | null>(null);
  busy      = signal<string | null>(null);
  query     = signal('');

  filtered = computed(() => {
    const q = this.query().trim().toLocaleLowerCase('pl');
    const list = this.users();
    return q ? list.filter(u => `${u.userEmail} ${u.userClub}`.toLocaleLowerCase('pl').includes(q)) : list;
  });
  pendingCount = computed(() => this.users().filter(u => u.status === 'pending_approval').length);

  ngOnInit() {
    this.api.getUsers().subscribe({
      next: list => { this.users.set(list); this.loaded.set(true); },
      error: err => this.loadError.set(err.error?.error ?? 'Nie udało się wczytać kont.'),
    });
  }

  status(u: AccountSummary) {
    return STATUS[u.status];
  }

  accountsLabel(n: number): string {
    return `${n} ${plural(n, 'konto', 'konta', 'kont')}`;
  }

  pendingLabel(n: number): string {
    return plural(n, 'konto czeka', 'konta czekają', 'kont czeka') + ' na aktywację';
  }

  setStatus(u: AccountSummary, status: AccountStatus) {
    this.busy.set(u.userId);
    this.api.setUserStatus(u.userId, status).subscribe({
      next: updated => {
        this.busy.set(null);
        this.users.update(list => list.map(x => x.userId === updated.userId ? updated : x));
        this.messages.add({
          severity: 'success',
          summary: status === 'active' ? 'Konto aktywne' : 'Konto zablokowane',
          detail: status === 'active' ? `Wysłano powiadomienie na ${u.userEmail}.` : u.userEmail,
        });
      },
      error: err => {
        this.busy.set(null);
        this.messages.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się zmienić statusu.' });
      },
    });
  }
}
