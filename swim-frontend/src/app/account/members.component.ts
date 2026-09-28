import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { TableModule } from 'primeng/table';
import { Button } from 'primeng/button';
import { Dialog } from 'primeng/dialog';
import { InputText } from 'primeng/inputtext';
import { InputNumber } from 'primeng/inputnumber';
import { Select } from 'primeng/select';
import { SelectButton } from 'primeng/selectbutton';
import { Message } from 'primeng/message';
import { Toast } from 'primeng/toast';
import { ProgressSpinner } from 'primeng/progressspinner';
import { MessageService } from 'primeng/api';
import { HeaderComponent } from '../shared/header/header.component';
import { SearchInputComponent } from '../shared/search-input/search-input.component';
import { plural } from '../shared/plural';
import { ApiService } from '../core/services/api.service';
import { ConfirmDeleteService } from '../core/services/confirm-delete.service';
import { ClubMember, ClubMemberInput, MemberTimeInput, SwimKind } from '../core/models';

const SEX_OPTIONS = [{ label: 'M', value: 'M' }, { label: 'K', value: 'K' }];
const KIND_OPTIONS: { label: string; value: SwimKind }[] = [
  { label: 'Dowolny', value: 'dowolny' },
  { label: 'Grzbietowy', value: 'grzbietowy' },
  { label: 'Klasyczny', value: 'klasyczny' },
  { label: 'Motylkowy', value: 'motylkowy' },
  { label: 'Zmienny', value: 'zmienny' },
];
// Same lists as SWIM_LENGTHS / POOL_LENGTHS in includes/user_repo.php
const LENGTH_OPTIONS = [25, 50, 100, 200, 400, 800, 1500].map(m => ({ label: `${m} m`, value: m }));
const POOL_OPTIONS = [{ label: '25 m', value: 25 }, { label: '50 m', value: 50 }];

const emptyTime = (): MemberTimeInput => ({
  competitionName: '', competitionDate: new Date().toISOString().slice(0, 10), poolLength: 25,
  competitionKind: 'dowolny', competitionLength: 50, competitionTime: '',
});

/** /konto/zawodnicy — club members of the logged-in user and their times. */
@Component({
  selector: 'app-members',
  imports: [FormsModule, DatePipe, TableModule, Button, Dialog, InputText, InputNumber, Select, SelectButton, Message, Toast,
            ProgressSpinner, HeaderComponent, SearchInputComponent],
  providers: [MessageService],
  template: `
    <app-header />
    <p-toast />
    <div class="swim-page">
      <div class="page-toolbar">
        <h1 class="swim-page-title">Moi zawodnicy</h1>
        <p-button label="Dodaj zawodnika" icon="pi pi-plus" (onClick)="openMember()" />
      </div>

      @if (loadError()) {
        <p-message severity="error" [text]="loadError()!" />
      } @else if (!loaded()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else {
        <div class="search-row">
          <app-search-input class="search-box" placeholder="Szukaj zawodnika..." [(value)]="query"
            [badge]="membersLabel(filtered().length)" />
          @if (!query().trim()) { <span class="count">{{ membersLabel(members().length) }}</span> }
        </div>

        <p-table [value]="filtered()" dataKey="memberId" [tableStyle]="{'min-width':'520px'}" styleClass="swim-datatable" [paginator]="filtered().length > 50" [rows]="50">
          <ng-template pTemplate="header">
            <tr><th>Imię i nazwisko</th><th>Płeć</th><th>Rok ur.</th><th>Wyniki</th><th class="actions-col"></th></tr>
          </ng-template>
          <ng-template pTemplate="body" let-m>
            <tr>
              <td>{{ m.memberName }}</td>
              <td>{{ m.memberSex }}</td>
              <td>{{ m.memberBirthYear }} <span class="muted">({{ age(m.memberBirthYear) }} l.)</span></td>
              <td>{{ m.memberTimes.length }}</td>
              <td class="actions-col">
                <p-button icon="pi pi-stopwatch" [rounded]="true" [text]="true" ariaLabel="Wyniki" (onClick)="openTimes(m)" />
                <p-button icon="pi pi-pencil" [rounded]="true" [text]="true" ariaLabel="Edytuj" (onClick)="openMember(m)" />
                <p-button icon="pi pi-trash" [rounded]="true" [text]="true" severity="danger" ariaLabel="Usuń" (onClick)="removeMember(m)" />
              </td>
            </tr>
          </ng-template>
          <ng-template pTemplate="emptymessage">
            <tr><td colspan="5" class="empty">
              {{ members().length ? 'Brak zawodników pasujących do wyszukiwania.' : 'Nie dodano jeszcze żadnego zawodnika.' }}
            </td></tr>
          </ng-template>
        </p-table>
      }
    </div>

    <!-- Add / edit club member -->
    <p-dialog [header]="editing() ? 'Edytuj zawodnika' : 'Nowy zawodnik'" [(visible)]="memberVisible" [modal]="true"
      [style]="{width:'440px'}" [breakpoints]="{'640px':'95vw'}">
      <form #mf="ngForm" (ngSubmit)="saveMember(mf)" class="form" novalidate>
        <div class="field">
          <label for="mName">Imię i nazwisko</label>
          <input pInputText id="mName" name="mName" [(ngModel)]="member.memberName" required maxlength="100" #mn="ngModel" />
          @if (mn.invalid && (mn.touched || mf.submitted)) { <small class="err">Podaj imię i nazwisko.</small> }
        </div>
        <div class="row">
          <div class="field">
            <label>Płeć</label>
            <p-selectbutton name="mSex" [options]="sexOptions" [(ngModel)]="member.memberSex" optionLabel="label" optionValue="value" [allowEmpty]="false" />
          </div>
          <div class="field">
            <label for="mYear">Rok urodzenia</label>
            <p-inputnumber inputId="mYear" name="mYear" [(ngModel)]="member.memberBirthYear" [useGrouping]="false"
              [min]="1920" [max]="currentYear" required #my="ngModel" />
            @if (my.invalid && (my.touched || mf.submitted)) { <small class="err">Podaj rok urodzenia.</small> }
          </div>
        </div>
        @if (formError()) { <p-message severity="error" [text]="formError()!" /> }
        <div class="dialog-actions">
          <p-button type="button" label="Anuluj" severity="secondary" [outlined]="true" (onClick)="memberVisible = false" />
          <p-button type="submit" label="Zapisz" icon="pi pi-save" [loading]="saving()" />
        </div>
      </form>
    </p-dialog>

    <!-- Times of one club member -->
    <p-dialog [header]="'Wyniki — ' + (timesOf()?.memberName ?? '')" [(visible)]="timesVisible" [modal]="true"
      [style]="{width:'760px'}" [breakpoints]="{'800px':'98vw'}">
      @if (timesOf(); as m) {
        <p-table [value]="sortedTimes()" [tableStyle]="{'min-width':'640px'}" styleClass="swim-datatable" [scrollable]="true" scrollHeight="300px">
          <ng-template pTemplate="header">
            <tr><th>Data</th><th>Zawody</th><th>Basen</th><th>Konkurencja</th><th>Czas</th><th></th></tr>
          </ng-template>
          <ng-template pTemplate="body" let-t>
            <tr>
              <td class="nowrap">{{ t.competitionDate | date:'dd.MM.yyyy' }}</td>
              <td>{{ t.competitionName }}</td>
              <td>{{ t.poolLength }} m</td>
              <td class="nowrap">{{ t.competitionLength }} m {{ t.competitionKind }}</td>
              <td class="time">{{ t.competitionTime }}</td>
              <td><p-button icon="pi pi-trash" [rounded]="true" [text]="true" severity="danger" ariaLabel="Usuń wynik" (onClick)="removeTime(m, t.competitionId)" /></td>
            </tr>
          </ng-template>
          <ng-template pTemplate="emptymessage">
            <tr><td colspan="6" class="empty">Brak wyników — dodaj pierwszy poniżej.</td></tr>
          </ng-template>
        </p-table>

        <form #tf="ngForm" (ngSubmit)="saveTime(tf, m)" class="form time-form" novalidate>
          <h3>Dodaj wynik</h3>
          <div class="row">
            <div class="field grow">
              <label for="tName">Zawody</label>
              <input pInputText id="tName" name="tName" [(ngModel)]="time.competitionName" required maxlength="200" />
            </div>
            <div class="field">
              <label for="tDate">Data</label>
              <input pInputText id="tDate" name="tDate" type="date" [(ngModel)]="time.competitionDate" required />
            </div>
          </div>
          <div class="row row-4">
            <div class="field">
              <label>Basen</label>
              <p-select name="tPool" [options]="poolOptions" [(ngModel)]="time.poolLength" optionLabel="label" optionValue="value" />
            </div>
            <div class="field">
              <label>Dystans</label>
              <p-select name="tLen" [options]="lengthOptions" [(ngModel)]="time.competitionLength" optionLabel="label" optionValue="value" />
            </div>
            <div class="field">
              <label>Styl</label>
              <p-select name="tKind" [options]="kindOptions" [(ngModel)]="time.competitionKind" optionLabel="label" optionValue="value" />
            </div>
            <div class="field">
              <label for="tTime">Czas</label>
              <input pInputText id="tTime" name="tTime" [(ngModel)]="time.competitionTime" required placeholder="1:02.34"
                pattern="^(\\d{1,2}:)?\\d{1,2}[.,]\\d{2}$" />
            </div>
          </div>
          @if (timeError()) { <p-message severity="error" [text]="timeError()!" /> }
          <p-button type="submit" label="Dodaj wynik" icon="pi pi-plus" [loading]="saving()" [disabled]="tf.invalid ?? true" />
        </form>
      }
    </p-dialog>
  `,
  styles: [`
    .page-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
    .page-toolbar .swim-page-title { margin: 0; }
    .search-row   { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .search-box   { flex: 1; min-width: 220px; max-width: 420px; }
    .count, .muted { color: var(--swim-muted); font-size: .85rem; }
    .actions-col  { text-align: right; white-space: nowrap; width: 1%; }
    .empty        { text-align: center; color: var(--swim-muted); padding: 1.5rem; }
    .nowrap       { white-space: nowrap; }
    .time         { font-variant-numeric: tabular-nums; font-weight: 700; }
    .center-spin  { display: flex; justify-content: center; padding: 3rem; }
    .form         { display: flex; flex-direction: column; gap: 1rem; }
    .row          { display: flex; gap: 1rem; flex-wrap: wrap; }
    .row > .field { flex: 1; min-width: 140px; }
    .row > .grow  { flex: 3; min-width: 220px; }
    .row-4 > .field { min-width: 120px; }
    .field        { display: flex; flex-direction: column; gap: .4rem; }
    .field label  { font-size: .85rem; color: var(--swim-muted); }
    .field input, .field ::ng-deep .p-inputnumber, .field ::ng-deep .p-inputnumber input, .field ::ng-deep .p-select { width: 100%; }
    .err          { color: #f44336; font-size: .78rem; }
    .dialog-actions { display: flex; justify-content: flex-end; gap: .5rem; }
    .time-form    { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--swim-border); align-items: flex-start; }
    .time-form .row { width: 100%; }
    .time-form h3 { margin: 0; font-size: .95rem; color: var(--swim-gold); }
  `]
})
export class MembersComponent implements OnInit {
  private api      = inject(ApiService);
  private confirm  = inject(ConfirmDeleteService);
  private messages = inject(MessageService);

  readonly sexOptions    = SEX_OPTIONS;
  readonly kindOptions   = KIND_OPTIONS;
  readonly lengthOptions = LENGTH_OPTIONS;
  readonly poolOptions   = POOL_OPTIONS;
  readonly currentYear   = new Date().getFullYear();

  members   = signal<ClubMember[]>([]);
  loaded    = signal(false);
  loadError = signal<string | null>(null);
  query     = signal('');
  filtered  = computed(() => {
    const q = this.query().trim().toLocaleLowerCase('pl');
    const list = [...this.members()].sort((a, b) => a.memberName.localeCompare(b.memberName, 'pl'));
    return q ? list.filter(m => m.memberName.toLocaleLowerCase('pl').includes(q)) : list;
  });

  saving    = signal(false);

  memberVisible = false;
  editing   = signal<ClubMember | null>(null);
  member: ClubMemberInput = { memberName: '', memberSex: 'M', memberBirthYear: this.currentYear - 10 };
  formError = signal<string | null>(null);

  timesVisible = false;
  timesOf   = signal<ClubMember | null>(null);
  sortedTimes = computed(() =>
    [...(this.timesOf()?.memberTimes ?? [])].sort((a, b) => b.competitionDate.localeCompare(a.competitionDate)));
  time: MemberTimeInput = emptyTime();
  timeError = signal<string | null>(null);

  ngOnInit() {
    this.api.getAccount().subscribe({
      next: a => { this.members.set(a.clubItems.clubMembers); this.loaded.set(true); },
      error: err => this.loadError.set(err.error?.error ?? 'Nie udało się wczytać zawodników.'),
    });
  }

  membersLabel(n: number): string {
    return `${n} ${plural(n, 'zawodnik', 'zawodników', 'zawodników')}`;
  }

  age(birthYear: number): number {
    return this.currentYear - birthYear;
  }

  openMember(m?: ClubMember) {
    this.editing.set(m ?? null);
    this.member = m
      ? { memberName: m.memberName, memberSex: m.memberSex, memberBirthYear: m.memberBirthYear }
      : { memberName: '', memberSex: 'M', memberBirthYear: this.currentYear - 10 };
    this.formError.set(null);
    this.memberVisible = true;
  }

  saveMember(f: NgForm) {
    if (f.invalid) return;
    const data: ClubMemberInput = { ...this.member, memberName: this.member.memberName.trim() };
    const edited = this.editing();
    this.saving.set(true);
    this.formError.set(null);

    const done = () => { this.saving.set(false); this.memberVisible = false; };
    const fail = (err: any) => { this.saving.set(false); this.formError.set(err.error?.error ?? 'Nie udało się zapisać.'); };

    if (edited) {
      this.api.updateMember(edited.memberId, data).subscribe({
        next: () => {
          this.members.update(list => list.map(m => m.memberId === edited.memberId ? { ...m, ...data } : m));
          done();
        },
        error: fail,
      });
    } else {
      this.api.addMember(data).subscribe({
        next: m => { this.members.update(list => [...list, m]); done(); },
        error: fail,
      });
    }
  }

  async removeMember(m: ClubMember) {
    const count = m.memberTimes.length;
    const ok = await this.confirm.confirm({
      message: `Czy na pewno usunąć zawodnika „${m.memberName}”` +
        (count ? ` wraz z ${count} ${plural(count, 'wynikiem', 'wynikami', 'wynikami')}?` : '?'),
    });
    if (!ok) return;
    this.api.deleteMember(m.memberId).subscribe({
      next: () => this.members.update(list => list.filter(x => x.memberId !== m.memberId)),
      error: err => this.messages.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się usunąć.' }),
    });
  }

  openTimes(m: ClubMember) {
    this.timesOf.set(m);
    this.time = emptyTime();
    this.timeError.set(null);
    this.timesVisible = true;
  }

  saveTime(f: NgForm, m: ClubMember) {
    if (f.invalid) return;
    this.saving.set(true);
    this.timeError.set(null);
    this.api.addMemberTime(m.memberId, { ...this.time, competitionName: this.time.competitionName.trim() }).subscribe({
      next: t => {
        this.saving.set(false);
        this.patchTimes(m.memberId, times => [...times, t]);
        // Keep competition/date/pool for entering the next event of the same meet
        this.time = { ...this.time, competitionTime: '' };
        f.controls['tTime']?.markAsPristine();
        f.controls['tTime']?.markAsUntouched();
      },
      error: err => {
        this.saving.set(false);
        this.timeError.set(err.error?.error ?? 'Nie udało się dodać wyniku.');
      },
    });
  }

  async removeTime(m: ClubMember, competitionId: string) {
    if (!(await this.confirm.confirm({ message: 'Czy na pewno usunąć ten wynik?' }))) return;
    this.api.deleteMemberTime(m.memberId, competitionId).subscribe({
      next: () => this.patchTimes(m.memberId, times => times.filter(t => t.competitionId !== competitionId)),
      error: err => this.messages.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się usunąć.' }),
    });
  }

  private patchTimes(memberId: string, fn: (t: ClubMember['memberTimes']) => ClubMember['memberTimes']) {
    this.members.update(list => list.map(x => x.memberId === memberId ? { ...x, memberTimes: fn(x.memberTimes) } : x));
    this.timesOf.set(this.members().find(x => x.memberId === memberId) ?? null);
  }
}
