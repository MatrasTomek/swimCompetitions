import { Component, inject, signal, OnInit, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Subject, timer, switchMap, catchError, of } from 'rxjs';
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
        <span class="swim-search search-box" [class.swim-search--active]="query.trim()">
          <input #searchInput pInputText [(ngModel)]="query" (ngModelChange)="search()" (keydown.escape)="clearSearch()"
            placeholder="Szukaj po imieniu, nazwisku, klubie..." class="search-input" />
          @if (query) {
            <button type="button" class="swim-search__clear" title="Wyczyść wyszukiwanie" aria-label="Wyczyść wyszukiwanie"
              (click)="clearSearch(); searchInput.focus()"><i class="pi pi-times"></i></button>
          }
        </span>
        @if (query.trim()) {
          <span class="swim-filter-badge">🔍 Filtr aktywny: {{ total() }} zawodników</span>
        } @else {
          <span class="count">{{ total() }} zawodników</span>
        }
      </div>

      @if (loadError()) {
        <p class="load-error">⚠ {{ loadError() }}</p>
      }
      @if (loading() && !loaded) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else {
        <p-table [value]="athletes()" styleClass="swim-datatable" [loading]="loading()"
          [paginator]="true" [rows]="50" [first]="(page - 1) * 50" [totalRecords]="total()" [lazy]="true"
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
    .search-box   { flex: 1; }
    .search-input { background: #1c1c1c; border-color: #333; color: #fff; }
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
  loaded    = false;

  private readonly requests = new Subject<number>();  // debounce delay in ms
  private readonly destroyRef = inject(DestroyRef);

  athleteUrl(file: string): string {
    return this.api.getAthleteFileUrl(file);
  }

  ngOnInit() {
    // switchMap cancels the in-flight request, so a slow earlier query can't overwrite newer results.
    this.requests.pipe(
      switchMap(delay => timer(delay).pipe(
        switchMap(() => {
          this.loading.set(true);
          this.loadError.set(null);
          return this.api.getAthletes(this.query.trim(), this.page).pipe(
            catchError(err => {
              this.loadError.set(err.error?.error ?? 'Nie udało się wczytać zawodników.');
              return of(null);
            }),
          );
        }),
      )),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe(res => {
      if (res) { this.athletes.set(res.athletes); this.total.set(res.total); }
      this.loading.set(false);
      this.loaded = true;
    });
    this.requests.next(0);
  }

  search() { this.page = 1; this.requests.next(300); }

  clearSearch() {
    if (!this.query) return;
    this.query = '';
    this.page = 1;
    this.requests.next(0);
  }

  onPage(event: any) { this.page = Math.floor(event.first / event.rows) + 1; this.requests.next(0); }
}
