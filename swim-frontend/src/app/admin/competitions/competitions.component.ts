import { Component, inject, signal, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Table, TableModule } from 'primeng/table';
import { Button } from 'primeng/button';
import { Tag } from 'primeng/tag';
import { Dialog } from 'primeng/dialog';
import { InputText } from 'primeng/inputtext';
import { ConfirmDialog } from 'primeng/confirmdialog';
import { Toast } from 'primeng/toast';
import { ProgressSpinner } from 'primeng/progressspinner';
import { MessageService, ConfirmationService } from 'primeng/api';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { Competition, AthleteRow, ResultFetchResponse } from '../../core/models';

@Component({
  selector: 'app-competitions',
  imports: [RouterLink, FormsModule, TableModule, Button, Tag, Dialog, InputText, ConfirmDialog, Toast, ProgressSpinner, HeaderComponent],
  providers: [MessageService, ConfirmationService],
  template: `
    <app-header [isAdmin]="true" />
    <div class="swim-page">
      <div class="page-toolbar">
        <h1 class="swim-page-title">Zawody</h1>
        <div class="toolbar-actions">
          <p-button label="Zawodnicy" icon="pi pi-users" severity="secondary" (onClick)="openAthletes()" />
          <a routerLink="/admin/zawody/dodaj">
            <p-button label="+ Dodaj zawody" />
          </a>
        </div>
      </div>

      @if (loading()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else {
        <p-table [value]="competitions()" [tableStyle]="{'min-width':'600px'}" styleClass="swim-datatable">
          <ng-template pTemplate="header">
            <tr>
              <th>Nazwa</th><th>Klub</th><th>Miejsce</th><th>Data</th><th>Plik</th><th>Akcje</th>
            </tr>
          </ng-template>
          <ng-template pTemplate="body" let-c>
            <tr>
              <td>{{ c.nazwa }}</td>
              <td>{{ c.klub }}</td>
              <td>{{ c.miejsce }}</td>
              <td>{{ c.data }}</td>
              <td>
                @if (c.has_file) { <code>{{ c.file }}</code> }
                @else { <p-tag value="zapowiedź" severity="warn" /> }
              </td>
              <td class="action-cell">
                @if (c.has_file) {
                  <a [routerLink]="['/zawody', slug(c), 'lista']" target="_blank" rel="noopener">
                    <p-button label="Starty" size="small" severity="secondary" />
                  </a>
                  @if (c.has_results) {
                    <a [routerLink]="['/zawody', slug(c), 'wyniki']" target="_blank" rel="noopener">
                      <p-button label="Wyniki" size="small" severity="secondary" />
                    </a>
                  }
                  <p-button label="Pobierz wyniki" size="small" severity="secondary" (onClick)="openLenex(c)" />
                  <a [routerLink]="['/admin/zawody', slug(c), 'edytuj']">
                    <p-button label="Edytuj" size="small" severity="secondary" />
                  </a>
                }
                <p-button label="Usuń" size="small" severity="danger" (onClick)="confirmDelete(c)" />
              </td>
            </tr>
          </ng-template>
        </p-table>
      }
    </div>

    <!-- LENEX Dialog -->
    <p-dialog header="Pobierz wyniki LENEX" [(visible)]="lenexVisible" [style]="{width:'500px'}" [modal]="true">
      <div class="dialog-content">
        <p class="field-label">URL zawodów (livetiming.pl)</p>
        <input pInputText [(ngModel)]="lenexUrl" placeholder="https://livetiming.pl/contest/..." class="w-full" />
        @if (lenexResult()) {
          <div class="lenex-result">
            <strong>Zaktualizowano: {{ lenexResult()!.updated }} / {{ lenexResult()!.total }}</strong>
            @if (lenexResult()!.errors.length) {
              <p class="error-text">Błędy: {{ lenexResult()!.errors.join(', ') }}</p>
            }
          </div>
        }
      </div>
      <ng-template pTemplate="footer">
        <p-button label="Anuluj" severity="secondary" (onClick)="lenexVisible=false" />
        <p-button label="Pobierz" [loading]="lenexLoading()" (onClick)="fetchLenex()" />
      </ng-template>
    </p-dialog>

    <!-- Athletes Dialog -->
    <p-dialog header="Zawodnicy" [(visible)]="athletesVisible" [style]="{width:'700px'}" [modal]="true">
      <div class="athletes-search">
        <input pInputText [(ngModel)]="athleteQ" (input)="loadAthletes()" placeholder="Szukaj..." />
        <a [href]="api.getAthletesExportUrl()" class="export-link">⬇ Eksportuj wszystkich</a>
      </div>
      <p-table [value]="athletes()" styleClass="swim-datatable" [loading]="athletesLoading()">
        <ng-template pTemplate="header">
          <tr><th>Nazwisko</th><th>Imię</th><th>Rok ur.</th><th>Klub</th><th>Startów</th><th></th></tr>
        </ng-template>
        <ng-template pTemplate="body" let-a>
          <tr>
            <td>{{ a.nazwisko }}</td>
            <td>{{ a.imie }}</td>
            <td>{{ a.rok_urodzenia }}</td>
            <td>{{ a.klub }}</td>
            <td>{{ a.starty }}</td>
            <td><a [href]="athleteUrl(a.file)" class="small-link">⬇</a></td>
          </tr>
        </ng-template>
      </p-table>
    </p-dialog>

    <p-confirmDialog />
    <p-toast />
  `,
  styles: [`
    .page-toolbar   { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
    .toolbar-actions{ display: flex; gap: .75rem; align-items: center; }
    .action-cell    { display: flex; gap: .5rem; flex-wrap: wrap; }
    .center-spin    { display: flex; justify-content: center; padding: 3rem; }
    code            { font-size: .75rem; background: #222; padding: .1rem .3rem; border-radius: 3px; color: #ccc; }
    .dialog-content { display: flex; flex-direction: column; gap: 1rem; padding: .5rem 0; }
    .field-label    { margin: 0; color: var(--swim-muted); font-size: .85rem; }
    .w-full         { width: 100%; }
    .lenex-result   { background: #1a2a1a; border: 1px solid var(--swim-green); border-radius: 6px; padding: .75rem; color: var(--swim-green); }
    .error-text     { color: var(--swim-red); font-size: .8rem; }
    .athletes-search{ display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; }
    .export-link    { color: var(--swim-gold); font-size: .85rem; }
    .small-link     { color: var(--swim-gold); }
  `]
})
export class CompetitionsComponent implements OnInit {
  api     = inject(ApiService);
  private msg    = inject(MessageService);
  private confirm= inject(ConfirmationService);

  loading      = signal(true);
  competitions = signal<Competition[]>([]);

  lenexVisible  = false;
  lenexUrl      = '';
  lenexLoading  = signal(false);
  lenexResult   = signal<ResultFetchResponse | null>(null);
  activeComp: Competition | null = null;

  athletesVisible  = false;
  athletesLoading  = signal(false);
  athletes         = signal<AthleteRow[]>([]);
  athleteQ         = '';

  slug(c: Competition): string { return c.file?.replace(/\.json$/, '') ?? ''; }

  athleteUrl(file: string): string {
    return this.api.getAthleteFileUrl(file);
  }

  ngOnInit() {
    this.api.getCompetitions().subscribe({
      next: d => { this.competitions.set(d); this.loading.set(false); },
      error: err => {
        this.loading.set(false);
        this.msg.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się wczytać listy zawodów.' });
      },
    });
  }

  openLenex(c: Competition) {
    this.activeComp = c;
    this.lenexUrl = '';
    this.lenexResult.set(null);
    this.lenexVisible = true;
  }

  fetchLenex() {
    if (!this.lenexUrl || !this.activeComp?.file) return;
    this.lenexLoading.set(true);
    this.api.fetchResults(this.lenexUrl, this.activeComp.file).subscribe({
      next: res => {
        this.lenexResult.set(res);
        this.lenexLoading.set(false);
        this.msg.add({ severity: 'success', summary: 'Gotowe', detail: `Zaktualizowano ${res.updated} z ${res.total}` });
        this.reload();
      },
      error: err => {
        this.lenexLoading.set(false);
        this.msg.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Błąd pobierania wyników' });
      },
    });
  }

  openAthletes() {
    this.athleteQ = '';
    this.athletesVisible = true;
    this.loadAthletes();
  }

  loadAthletes() {
    this.athletesLoading.set(true);
    this.api.getAthletes(this.athleteQ).subscribe({
      next: res => { this.athletes.set(res.athletes); this.athletesLoading.set(false); },
      error: err => {
        this.athletesLoading.set(false);
        this.msg.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się wczytać zawodników.' });
      },
    });
  }

  confirmDelete(c: Competition) {
    this.confirm.confirm({
      message: `Usunąć "${c.nazwa}"?`,
      header: 'Potwierdzenie',
      icon: 'pi pi-trash',
      accept: () => this.deleteCompetition(c),
    });
  }

  deleteCompetition(c: Competition) {
    const req = c.has_file
      ? this.api.deleteCompetition(this.slug(c))
      : this.api.deleteAnnouncement(c.id!);
    req.subscribe({
      next: () => {
        this.msg.add({ severity: 'success', summary: 'Usunięto' });
        this.reload();
      },
      error: () => this.msg.add({ severity: 'error', summary: 'Błąd usuwania' }),
    });
  }

  private reload() {
    this.api.getCompetitions().subscribe(d => this.competitions.set(d));
  }
}
