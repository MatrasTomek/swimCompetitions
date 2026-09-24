import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { InputText } from 'primeng/inputtext';
import { SelectButton } from 'primeng/selectbutton';
import { Tag } from 'primeng/tag';
import { ProgressSpinner } from 'primeng/progressspinner';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { LocalCompetitionsService, LocalCompetition } from '../../core/services/local-competitions.service';
import { ConfirmDeleteService } from '../../core/services/confirm-delete.service';
import { Competition } from '../../core/models';

@Component({
  selector: 'app-home',
  imports: [RouterLink, FormsModule, InputText, SelectButton, Tag, ProgressSpinner, HeaderComponent],
  template: `
    <app-header />
    <div class="swim-page">
      <div class="home-toolbar">
        <input pInputText placeholder="Szukaj zawodów..." [ngModel]="query()" (ngModelChange)="query.set($event)" class="search-input" />
        <p-selectbutton [options]="scopeOpts" [ngModel]="scope()" (ngModelChange)="setScope($event)" [allowEmpty]="false" optionLabel="label" optionValue="value" />
        <p-selectbutton [options]="viewOpts" [ngModel]="view()" (ngModelChange)="setView($event)" [allowEmpty]="false" optionLabel="label" optionValue="value" />
        <a routerLink="/import" class="card-link gold import-link">⇪ Listy Startowe</a>
      </div>

      @if (localFiltered().length > 0) {
        <section class="local-section">
          <h2 class="local-title">Moje listy <small>(tylko w tej przeglądarce)</small></h2>
          @if (view() === 'grid') {
            <div class="competition-grid">
              @for (c of localFiltered(); track c.id) {
                <div class="competition-card">
                  <div class="competition-card__name">{{ c.nazwa }}</div>
                  <div class="competition-card__meta">
                    @if (c.data)    { <span>📅 {{ c.data }}</span> }
                    @if (c.miejsce) { <span>📍 {{ c.miejsce }}</span> }
                    @if (c.klub)    { <span>🏊 {{ c.klub }}</span> }
                  </div>
                  <div class="competition-card__actions">
                    <a [routerLink]="['/moje', c.id, 'lista']" class="card-link">Lista startowa</a>
                    <button type="button" class="card-link remove-btn" (click)="removeLocal(c)">Usuń</button>
                  </div>
                </div>
              }
            </div>
          } @else {
            <table class="swim-table">
              <thead><tr><th>Nazwa</th><th>Data</th><th>Miejsce</th><th>Klub</th><th></th></tr></thead>
              <tbody>
                @for (c of localFiltered(); track c.id) {
                  <tr>
                    <td>{{ c.nazwa }}</td>
                    <td>{{ c.data }}</td>
                    <td>{{ c.miejsce }}</td>
                    <td>{{ c.klub }}</td>
                    <td class="actions">
                      <a [routerLink]="['/moje', c.id, 'lista']">Lista</a>
                      <button type="button" class="remove-link" (click)="removeLocal(c)">Usuń</button>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          }
        </section>
      }

      @if (loadError()) {
        <p class="empty error-text">⚠ {{ loadError() }}</p>
      }
      @if (loading()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else if (filtered().length === 0) {
        @if (localFiltered().length === 0) {
          <p class="empty">Nie znaleziono zawodów.</p>
        }
      } @else if (view() === 'grid') {
        <div class="competition-grid">
          @for (c of filtered(); track c.file || c.id) {
            <div class="competition-card" [class.announcement]="!c.has_file">
              <div class="competition-card__name">{{ c.nazwa }}</div>
              <div class="competition-card__meta">
                @if (c.data)    { <span>📅 {{ c.data }}</span> }
                @if (c.miejsce) { <span>📍 {{ c.miejsce }}</span> }
                @if (c.klub)    { <span>🏊 {{ c.klub }}</span> }
              </div>
              <div class="competition-card__actions">
                @if (c.has_file) {
                  <a [routerLink]="['/zawody', slug(c), 'lista']" class="card-link">Lista startowa</a>
                  @if (c.has_results) {
                    <a [routerLink]="['/zawody', slug(c), 'wyniki']" class="card-link gold">Wyniki</a>
                  }
                } @else {
                  <p-tag value="Zapowiedź" severity="warn" />
                }
              </div>
            </div>
          }
        </div>
      } @else {
        <table class="swim-table">
          <thead><tr><th>Nazwa</th><th>Data</th><th>Miejsce</th><th>Klub</th><th></th></tr></thead>
          <tbody>
            @for (c of filtered(); track c.file || c.id) {
              <tr>
                <td>{{ c.nazwa }}</td>
                <td>{{ c.data }}</td>
                <td>{{ c.miejsce }}</td>
                <td>{{ c.klub }}</td>
                <td class="actions">
                  @if (c.has_file) {
                    <a [routerLink]="['/zawody', slug(c), 'lista']">Lista</a>
                    @if (c.has_results) { <a [routerLink]="['/zawody', slug(c), 'wyniki']">Wyniki</a> }
                  } @else {
                    <p-tag value="Zapowiedź" severity="warn" />
                  }
                </td>
              </tr>
            }
          </tbody>
        </table>
      }
    </div>
  `,
  styles: [`
    .home-toolbar { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .search-input { flex: 1; min-width: 200px; background: #1c1c1c; border-color: #333; color: #fff; }
    .center-spin  { display: flex; justify-content: center; padding: 3rem; }
    .empty        { color: var(--swim-muted); text-align: center; padding: 2rem; }
    .error-text   { color: var(--swim-red); }
    .card-link    { color: #ccc; font-size: .8rem; text-decoration: none; border: 1px solid #333; border-radius: 4px; padding: .2rem .5rem; }
    .card-link:hover { border-color: var(--swim-gold); color: var(--swim-gold); }
    .card-link.gold  { color: var(--swim-gold); border-color: var(--swim-gold); }
    .announcement { opacity: .7; }
    .import-link  { padding: .45rem .8rem; font-size: .85rem; }
    .local-section { margin-bottom: 2rem; }
    .local-title  { color: var(--swim-gold); font-size: 1.05rem; margin: 0 0 .75rem; }
    .local-title small { color: var(--swim-muted); font-weight: 400; font-size: .8rem; }
    .remove-btn   { background: none; cursor: pointer; font-family: inherit; }
    .remove-btn:hover { border-color: var(--swim-red); color: var(--swim-red); }
    .swim-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    .swim-table th, .swim-table td { padding: .6rem .8rem; border-bottom: 1px solid var(--swim-border); text-align: left; }
    .swim-table th { color: var(--swim-gold); font-weight: 600; }
    .swim-table .actions { display: flex; gap: .5rem; }
    .swim-table .actions a { color: var(--swim-gold); text-decoration: none; font-size: .8rem; }
    .swim-table .remove-link { background: none; border: none; padding: 0; cursor: pointer; font: inherit; font-size: .8rem; color: #ccc; }
    .swim-table .remove-link:hover { color: var(--swim-red); }
  `]
})
export class HomeComponent implements OnInit {
  private api = inject(ApiService);
  readonly local = inject(LocalCompetitionsService);
  private deletion = inject(ConfirmDeleteService);

  loading   = signal(true);
  loadError = signal<string | null>(null);
  private all = signal<Competition[]>([]);

  query = signal('');
  scope = signal<'latest' | 'all'>(readPref('swim-scope', ['latest', 'all'], 'latest'));
  view  = signal<'grid' | 'list'>(readPref('swim-view', ['grid', 'list'], 'grid'));

  scopeOpts = [{ label: 'Najnowsze', value: 'latest' }, { label: 'Wszystko', value: 'all' }];
  viewOpts  = [{ label: '⊞ Karty', value: 'grid' },  { label: '☰ Tabela', value: 'list' }];

  filtered = computed(() => {
    const q = this.query().toLowerCase();
    let list = matchesQuery(this.all() ?? [], q);
    if (this.scope() === 'latest' && !q) list = list.slice(0, 4);
    return list;
  });

  /** Visitor's own imported lists — searched too, but never cut by the "latest" scope. */
  localFiltered = computed(() => matchesQuery(this.local.items(), this.query().toLowerCase()));

  slug(c: Competition): string {
    return c.file ? c.file.replace(/\.json$/, '') : '';
  }

  ngOnInit() {
    this.api.getCompetitions().subscribe({
      next: data => { this.all.set(data ?? []); this.loading.set(false); },
      error: err => {
        this.loading.set(false);
        this.loadError.set(err.error?.error ?? 'Nie udało się wczytać listy zawodów.');
      },
    });
  }

  setScope(value: 'latest' | 'all') { this.scope.set(value); writePref('swim-scope', value); }
  setView(value: 'grid' | 'list')   { this.view.set(value);  writePref('swim-view',  value); }

  async removeLocal(c: LocalCompetition) {
    if (await this.deletion.confirm({ name: c.nazwa })) this.local.remove(c.id);
  }
}

function matchesQuery<T extends Competition>(list: T[], q: string): T[] {
  if (!q) return list;
  return list.filter(c => [c.nazwa, c.data, c.miejsce, c.klub].some(f => f?.toLowerCase().includes(q)));
}

function readPref<T extends string>(key: string, allowed: readonly T[], fallback: T): T {
  try {
    const v = localStorage.getItem(key);
    return allowed.includes(v as T) ? (v as T) : fallback;
  } catch {
    return fallback;
  }
}

function writePref(key: string, value: string): void {
  try { localStorage.setItem(key, value); } catch { /* storage unavailable — keep in-memory only */ }
}
