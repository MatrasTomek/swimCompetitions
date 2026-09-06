import { Component, inject, signal, computed, OnInit, OnDestroy } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ProgressBar } from 'primeng/progressbar';
import { ProgressSpinner } from 'primeng/progressspinner';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { Competition, Start } from '../../core/models';
import { interval, Subscription, switchMap, catchError, EMPTY } from 'rxjs';

function timeToSeconds(t?: string): number {
  if (!t) return 0;
  const parts = t.split(':');
  if (parts.length === 2) return parseFloat(parts[0]) * 60 + parseFloat(parts[1]);
  return parseFloat(t);
}

@Component({
  selector: 'app-results',
  imports: [RouterLink, ProgressBar, ProgressSpinner, HeaderComponent],
  template: `
    <app-header />
    <div class="swim-page">
      @if (loading()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else if (competition()) {
        <div class="res-header">
          <a routerLink="/" class="back">← Zawody</a>
          <h1 class="swim-page-title">{{ competition()!.nazwa }} — Wyniki</h1>
          <div class="res-meta">
            @if (competition()!.data)    { <span>📅 {{ competition()!.data }}</span> }
            @if (competition()!.miejsce) { <span>📍 {{ competition()!.miejsce }}</span> }
          </div>
          <div class="res-progress">
            <span class="progress-label">Wyniki: {{ fetchedCount() }} / {{ totalCount() }}</span>
            <p-progressBar [value]="progressPct()" [showValue]="false" styleClass="slim-bar" />
            @if (pollError()) {
              <span class="poll-error">⚠ Nie udało się odświeżyć wyników — ponawiam za chwilę.</span>
            }
          </div>
          <div class="res-actions">
            @if (slug) {
              <a [href]="pdfUrl" target="_blank" rel="noopener" class="pdf-btn">⬇ Pobierz PDF</a>
            }
          </div>
        </div>

        @for (blok of competition()!.bloki ?? []; track blok.blok) {
          <div class="blok">
            <div class="blok-header">
              <span class="blok-nr">Blok {{ blok.blok }}</span>
              <span class="blok-meta">{{ blok.data }} &nbsp;⏰ {{ blok.godz_start }}</span>
            </div>
            <table class="swim-table">
              <thead><tr><th>Zawodnik</th><th>Konkurencja</th><th>Tor</th><th>Czas bazowy</th><th>Wynik</th><th>Punkty</th></tr></thead>
              <tbody>
                @for (s of blok.starty; track s.imie + s.konkurencja_nr) {
                  <tr>
                    <td>{{ s.imie }}</td>
                    <td>{{ s.konkurencja }}</td>
                    <td>{{ s.tor }}</td>
                    <td>{{ s.czas }}</td>
                    <td [class]="resultClass(s)">{{ s.czas_result ?? '—' }}</td>
                    <td>{{ s.punkty ?? '—' }}</td>
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
    .res-header    { margin-bottom: 1.5rem; }
    .back          { color: var(--swim-muted); font-size: .85rem; text-decoration: none; }
    .back:hover    { color: var(--swim-gold); }
    .res-meta      { display: flex; gap: 1rem; color: var(--swim-muted); font-size: .9rem; margin: .5rem 0 .75rem; }
    .res-progress  { margin-bottom: .75rem; }
    .progress-label{ font-size: .8rem; color: var(--swim-muted); margin-bottom: .25rem; }
    ::ng-deep .slim-bar .p-progressbar { height: 6px; }
    .res-actions   { margin-top: .5rem; }
    .pdf-btn       { color: var(--swim-gold); border: 1px solid var(--swim-gold); border-radius: 4px; padding: .4rem .8rem; text-decoration: none; font-size: .85rem; }
    .poll-error    { display: block; margin-top: .35rem; font-size: .8rem; color: #e0a030; }
    .center-spin   { display: flex; justify-content: center; padding: 3rem; }
    .empty         { color: var(--swim-muted); text-align: center; padding: 2rem; }
    .empty.error-text { color: var(--swim-red); }
    .blok          { margin-bottom: 2rem; }
    .blok-header   { display: flex; align-items: center; gap: 1rem; margin-bottom: .75rem; }
    .blok-nr       { background: var(--swim-gold); color: #111; font-weight: 700; border-radius: 4px; padding: .2rem .6rem; }
    .blok-meta     { color: var(--swim-muted); font-size: .85rem; }
    .swim-table    { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .swim-table th, .swim-table td { padding: .5rem .7rem; border-bottom: 1px solid var(--swim-border); text-align: left; }
    .swim-table th { color: var(--swim-gold); font-weight: 600; }
    .swim-table tbody tr:hover { background: rgba(255,215,0,.05); }
  `]
})
export class ResultsComponent implements OnInit, OnDestroy {
  private api   = inject(ApiService);
  private route = inject(ActivatedRoute);

  loading    = signal(true);
  competition = signal<Competition | null>(null);
  loadError  = signal<string | null>(null);
  pollError  = signal(false);
  slug = '';
  private pollSub?: Subscription;

  get pdfUrl(): string { return this.api.getCompetitionPdfUrl(this.slug); }

  totalCount   = computed(() => this.competition()?.bloki?.flatMap(b => b.starty).length ?? 0);
  fetchedCount = computed(() => this.competition()?.bloki?.flatMap(b => b.starty).filter(s => s.result_fetched).length ?? 0);
  progressPct  = computed(() => this.totalCount() ? Math.round(this.fetchedCount() / this.totalCount() * 100) : 0);

  resultClass(s: Start): string {
    if (!s.czas_result) return 'result-none';
    return timeToSeconds(s.czas_result) < timeToSeconds(s.czas) ? 'result-better' : '';
  }

  private loadData() {
    return this.api.getCompetition(this.slug).subscribe({
      next: data => { this.competition.set(data); this.loading.set(false); },
      error: err => {
        this.loading.set(false);
        this.loadError.set(err.error?.error ?? 'Nie udało się wczytać zawodów.');
      },
    });
  }

  ngOnInit() {
    this.slug = this.route.snapshot.paramMap.get('slug') ?? '';
    this.loadData();
    // Poll every 60 s — errors are swallowed here (not rethrown) so a single
    // failed poll doesn't permanently kill the subscription.
    this.pollSub = interval(60_000).pipe(
      switchMap(() => this.api.getCompetition(this.slug).pipe(
        catchError(() => { this.pollError.set(true); return EMPTY; })
      ))
    ).subscribe(data => { this.pollError.set(false); this.competition.set(data); });
  }

  ngOnDestroy() { this.pollSub?.unsubscribe(); }
}
