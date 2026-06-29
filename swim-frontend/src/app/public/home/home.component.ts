import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { InputText } from 'primeng/inputtext';
import { SelectButton } from 'primeng/selectbutton';
import { Tag } from 'primeng/tag';
import { ProgressSpinner } from 'primeng/progressspinner';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { Competition } from '../../core/models';

@Component({
  selector: 'app-home',
  imports: [RouterLink, FormsModule, InputText, SelectButton, Tag, ProgressSpinner, HeaderComponent],
  template: `
    <app-header />
    <div class="swim-page">
      <div class="home-toolbar">
        <input pInputText placeholder="Szukaj zawodów..." [(ngModel)]="query" class="search-input" />
        <p-selectbutton [options]="scopeOpts" [(ngModel)]="scope" optionLabel="label" optionValue="value" />
        <p-selectbutton [options]="viewOpts" [(ngModel)]="view" optionLabel="label" optionValue="value" />
      </div>

      @if (loading()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else if (filtered().length === 0) {
        <p class="empty">Nie znaleziono zawodów.</p>
      } @else if (view === 'grid') {
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
    .card-link    { color: #ccc; font-size: .8rem; text-decoration: none; border: 1px solid #333; border-radius: 4px; padding: .2rem .5rem; }
    .card-link:hover { border-color: var(--swim-gold); color: var(--swim-gold); }
    .card-link.gold  { color: var(--swim-gold); border-color: var(--swim-gold); }
    .announcement { opacity: .7; }
    .swim-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    .swim-table th, .swim-table td { padding: .6rem .8rem; border-bottom: 1px solid var(--swim-border); text-align: left; }
    .swim-table th { color: var(--swim-gold); font-weight: 600; }
    .swim-table .actions { display: flex; gap: .5rem; }
    .swim-table .actions a { color: var(--swim-gold); text-decoration: none; font-size: .8rem; }
  `]
})
export class HomeComponent implements OnInit {
  private api = inject(ApiService);

  loading = signal(true);
  private all = signal<Competition[]>([]);

  query = '';
  scope: 'latest' | 'all' = (localStorage.getItem('swim-scope') as any) ?? 'latest';
  view: 'grid' | 'list' = (localStorage.getItem('swim-view') as any) ?? 'grid';

  scopeOpts = [{ label: 'Najnowsze', value: 'latest' }, { label: 'Wszystko', value: 'all' }];
  viewOpts  = [{ label: '⊞ Karty', value: 'grid' },  { label: '☰ Tabela', value: 'list' }];

  filtered = computed(() => {
    const q = this.query.toLowerCase();
    let list = this.all() ?? [];
    if (q) {
      list = list.filter(c =>
        [c.nazwa, c.data, c.miejsce, c.klub].some(f => f?.toLowerCase().includes(q))
      );
    }
    if (this.scope === 'latest' && !q) list = list.slice(0, 4);
    return list;
  });

  slug(c: Competition): string {
    return c.file ? c.file.replace(/\.json$/, '') : '';
  }

  ngOnInit() {
    this.api.getCompetitions().subscribe({
      next: data => { this.all.set(data ?? []); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  onScopeChange() { localStorage.setItem('swim-scope', this.scope); }
  onViewChange()  { localStorage.setItem('swim-view',  this.view);  }
}
