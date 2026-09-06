import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { InputText } from 'primeng/inputtext';
import { ProgressSpinner } from 'primeng/progressspinner';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { Competition, Blok, Start } from '../../core/models';

@Component({
  selector: 'app-start-list',
  imports: [RouterLink, FormsModule, InputText, ProgressSpinner, HeaderComponent],
  template: `
    <app-header />
    <div class="swim-page">
      @if (loading()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else if (competition()) {
        <div class="sl-header">
          <a routerLink="/" class="back">← Zawody</a>
          <h1 class="swim-page-title">{{ competition()!.nazwa }}</h1>
          <div class="sl-meta">
            @if (competition()!.data)    { <span>📅 {{ competition()!.data }}</span> }
            @if (competition()!.miejsce) { <span>📍 {{ competition()!.miejsce }}</span> }
            @if (competition()!.basen)   { <span>🏊 Basen {{ competition()!.basen }}</span> }
          </div>
          <div class="sl-actions">
            <input pInputText placeholder="Szukaj zawodnika..." [(ngModel)]="query" class="search-input" />
            @if (slug) {
              <a [href]="pdfUrl" target="_blank" rel="noopener" class="pdf-btn">⬇ PDF wyniki</a>
            }
          </div>
        </div>

        @for (blok of filteredBloki(); track blok.blok) {
          <div class="blok">
            <div class="blok-header">
              <span class="blok-nr">Blok {{ blok.blok }}</span>
              <span class="blok-meta">{{ blok.data }} &nbsp;⏰ {{ blok.godz_start }}</span>
            </div>
            <table class="swim-table">
              <thead><tr><th>Zawodnik</th><th>Konkurencja</th><th>Seria</th><th>Godz.</th><th>Tor</th><th>Czas</th></tr></thead>
              <tbody>
                @for (s of blok.starty; track s.imie + s.konkurencja_nr) {
                  <tr>
                    <td>{{ s.imie }}</td>
                    <td>{{ s.konkurencja }}</td>
                    <td>{{ s.seria }}</td>
                    <td>{{ s.godz }}</td>
                    <td>{{ s.tor }}</td>
                    <td>{{ s.czas }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        }
      } @else if (loadError()) {
        <p class="empty error-text">⚠ {{ loadError() }}</p>
      } @else {
        <p class="empty">Nie znaleziono zawodów.</p>
      }
    </div>
  `,
  styles: [`
    .sl-header    { margin-bottom: 1.5rem; }
    .back         { color: var(--swim-muted); font-size: .85rem; text-decoration: none; }
    .back:hover   { color: var(--swim-gold); }
    .sl-meta      { display: flex; gap: 1rem; color: var(--swim-muted); font-size: .9rem; margin: .5rem 0 1rem; }
    .sl-actions   { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
    .search-input { background: #1c1c1c; border-color: #333; color: #fff; min-width: 220px; }
    .pdf-btn      { color: var(--swim-gold); border: 1px solid var(--swim-gold); border-radius: 4px; padding: .4rem .8rem; text-decoration: none; font-size: .85rem; }
    .center-spin  { display: flex; justify-content: center; padding: 3rem; }
    .empty        { color: var(--swim-muted); text-align: center; padding: 2rem; }
    .error-text   { color: var(--swim-red); }
    .blok         { margin-bottom: 2rem; }
    .blok-header  { display: flex; align-items: center; gap: 1rem; margin-bottom: .75rem; }
    .blok-nr      { background: var(--swim-gold); color: #111; font-weight: 700; border-radius: 4px; padding: .2rem .6rem; }
    .blok-meta    { color: var(--swim-muted); font-size: .85rem; }
    .swim-table   { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .swim-table th, .swim-table td { padding: .5rem .7rem; border-bottom: 1px solid var(--swim-border); text-align: left; }
    .swim-table th { color: var(--swim-gold); font-weight: 600; }
    .swim-table tbody tr:hover { background: rgba(255,215,0,.05); }
  `]
})
export class StartListComponent implements OnInit {
  private api    = inject(ApiService);
  private route  = inject(ActivatedRoute);

  loading = signal(true);
  competition = signal<Competition | null>(null);
  loadError = signal<string | null>(null);
  query = '';
  slug = '';

  get pdfUrl(): string {
    return this.api.getCompetitionPdfUrl(this.slug);
  }

  filteredBloki = computed(() => {
    const comp = this.competition();
    if (!comp?.bloki) return [];
    const q = this.query.toLowerCase();
    if (!q) return comp.bloki;
    return comp.bloki.map(blok => ({
      ...blok,
      starty: blok.starty.filter(s =>
        s.imie.toLowerCase().includes(q) || s.konkurencja.toLowerCase().includes(q)
      )
    })).filter(b => b.starty.length > 0);
  });

  ngOnInit() {
    this.slug = this.route.snapshot.paramMap.get('slug') ?? '';
    this.api.getCompetition(this.slug).subscribe({
      next: data => { this.competition.set(data); this.loading.set(false); },
      error: err => {
        this.loading.set(false);
        this.loadError.set(err.error?.error ?? 'Nie udało się wczytać zawodów.');
      },
    });
  }
}
