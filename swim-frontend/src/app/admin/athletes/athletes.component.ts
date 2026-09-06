import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TableModule } from 'primeng/table';
import { InputText } from 'primeng/inputtext';
import { ProgressSpinner } from 'primeng/progressspinner';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { AthleteRow } from '../../core/models';

@Component({
  selector: 'app-athletes',
  imports: [FormsModule, TableModule, InputText, ProgressSpinner, HeaderComponent],
  template: `
    <app-header [isAdmin]="true" />
    <div class="swim-page">
      <div class="page-toolbar">
        <h1 class="swim-page-title">Zawodnicy</h1>
        <a [href]="api.getAthletesExportUrl()" class="export-btn">⬇ Eksportuj wszystkich</a>
      </div>

      <div class="search-row">
        <input pInputText [(ngModel)]="query" (input)="search()" placeholder="Szukaj po imieniu, nazwisku, klubie..." class="search-input" />
        <span class="count">{{ total() }} zawodników</span>
      </div>

      @if (loadError()) {
        <p class="load-error">⚠ {{ loadError() }}</p>
      }
      @if (loading()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else {
        <p-table [value]="athletes()" styleClass="swim-datatable"
          [paginator]="true" [rows]="50" [totalRecords]="total()" [lazy]="true"
          (onPage)="onPage($event)">
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
              <td><a [href]="athleteUrl(a.file)" class="download-link" download>⬇</a></td>
            </tr>
          </ng-template>
        </p-table>
      }
    </div>
  `,
  styles: [`
    .page-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    .export-btn   { color: var(--swim-gold); border: 1px solid var(--swim-gold); border-radius: 4px; padding: .4rem .8rem; text-decoration: none; font-size: .85rem; }
    .search-row   { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
    .search-input { flex: 1; background: #1c1c1c; border-color: #333; color: #fff; }
    .count        { color: var(--swim-muted); font-size: .85rem; white-space: nowrap; }
    .center-spin  { display: flex; justify-content: center; padding: 3rem; }
    .download-link{ color: var(--swim-gold); }
    .load-error   { color: var(--swim-red); margin-bottom: 1rem; }
  `]
})
export class AthletesComponent implements OnInit {
  api       = inject(ApiService);
  loading   = signal(true);
  athletes  = signal<AthleteRow[]>([]);
  total     = signal(0);
  loadError = signal<string | null>(null);
  query     = '';
  page      = 1;

  athleteUrl(file: string): string {
    return this.api.getAthleteFileUrl(file);
  }

  ngOnInit() { this.load(); }

  search() { this.page = 1; this.load(); }

  onPage(event: any) { this.page = Math.floor(event.first / event.rows) + 1; this.load(); }

  private load() {
    this.loading.set(true);
    this.loadError.set(null);
    this.api.getAthletes(this.query, this.page).subscribe({
      next: res => { this.athletes.set(res.athletes); this.total.set(res.total); this.loading.set(false); },
      error: err => {
        this.loading.set(false);
        this.loadError.set(err.error?.error ?? 'Nie udało się wczytać zawodników.');
      },
    });
  }
}
