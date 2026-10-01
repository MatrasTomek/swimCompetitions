import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Select } from 'primeng/select';
import { Button } from 'primeng/button';
import { Message } from 'primeng/message';
import { ProgressSpinner } from 'primeng/progressspinner';
import { forkJoin } from 'rxjs';
import type { EChartsCoreOption } from 'echarts/core';
import { HeaderComponent } from '../../shared/header/header.component';
import { SearchInputComponent } from '../../shared/search-input/search-input.component';
import { ChartComponent, chartTheme } from '../../shared/chart/chart.component';
import { plural } from '../../shared/plural';
import { seasonBests } from '../../shared/swim-time';
import { rankingTooltip } from '../../shared/chart-tooltip';
import { latestRequest } from '../../shared/latest-request';
import { ApiService } from '../../core/services/api.service';
import { ClubMember, MemberResult, SwimKind } from '../../core/models';
import { ResultsTableComponent } from './results-table.component';
import { parseSeason, seasonYears } from './season';
import { MemberTotal, countWith, filterResults, memberTotals, seasonSummary, topBy } from './stats-data';

type Row = MemberResult & { memberName: string };

const STROKES: SwimKind[] = ['dowolny', 'grzbietowy', 'klasyczny', 'motylkowy', 'zmienny'];
/** Bars per chart — a club may have hundreds of members; the table below lists everyone. */
const CHART_MEMBERS = 15;

/** /konto/statystyki — one season's results of all the club's members: tiles, two rankings and the full table. */
@Component({
  selector: 'app-stats',
  imports: [FormsModule, RouterLink, Select, Button, Message, ProgressSpinner, HeaderComponent, SearchInputComponent,
            ChartComponent, ResultsTableComponent],
  template: `
    <app-header />
    <div class="swim-page">
      <div class="page-toolbar">
        <h1 class="swim-page-title">Statystyki klubu</h1>
        <label class="season">Sezon
          <p-select [options]="years" [ngModel]="season()" (ngModelChange)="changeSeason($event)" ariaLabel="Sezon" />
        </label>
      </div>

      @if (loadError()) {
        <div class="load-error">
          <p-message severity="error" [text]="loadError()!" />
          <p-button label="Spróbuj ponownie" icon="pi pi-refresh" severity="secondary" (onClick)="retry()" />
        </div>
      } @else if (!loaded()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else if (!rows().length) {
        <p class="empty">
          Brak wyników w sezonie {{ season() }}. Pobierz je przyciskiem „Pobierz wyniki na konto” na stronie
          <a routerLink="/">zaimportowanej listy startowej</a>.
        </p>
      } @else {
        <div class="tiles">
          <div class="tile"><b>{{ summary().members }}</b><span>{{ plural(summary().members, 'zawodnik z wynikami', 'zawodników z wynikami', 'zawodników z wynikami') }}</span></div>
          <div class="tile"><b>{{ summary().starts }}</b><span>{{ plural(summary().starts, 'start', 'starty', 'startów') }}</span></div>
          <div class="tile"><b>{{ summary().contests }}</b><span>{{ plural(summary().contests, 'zawody', 'zawody', 'zawodów') }}</span></div>
          <div class="tile" title="Starty, w których zawodnik pobił swój wcześniejszy najlepszy czas sezonu w tej samej konkurencji i na tym samym basenie"><b>{{ summary().improvements }}</b><span>{{ plural(summary().improvements, 'poprawa czasu', 'poprawy czasu', 'popraw czasu') }}</span></div>
        </div>

        <div class="charts">
          <section class="chart-card">
            <h2>Najwięcej startów{{ chartNote(byStarts().length, 'starts') }}</h2>
            <app-chart [options]="startsChart()" [height]="barHeight(byStarts().length)" label="Liczba startów w sezonie według zawodnika" />
          </section>
          <section class="chart-card">
            <h2>Najlepsze punkty FINA{{ chartNote(byPoints().length, 'bestPoints') }}</h2>
            @if (byPoints().length) {
              <app-chart [options]="pointsChart()" [height]="barHeight(byPoints().length)" label="Najlepszy wynik punktowy w sezonie według zawodnika" />
            } @else {
              <p class="chart-empty">Wyniki z tego sezonu nie mają punktów FINA.</p>
            }
          </section>
        </div>

        <div class="filters">
          <app-search-input class="search-box" placeholder="Szukaj zawodnika..." [(value)]="query"
            [badge]="filtered().length + ' ' + plural(filtered().length, 'wynik', 'wyniki', 'wyników')" />
          <p-select [options]="strokeOptions" [(ngModel)]="stroke" [showClear]="true" placeholder="Styl" ariaLabel="Styl" />
          <p-select [options]="distanceOptions()" [(ngModel)]="distance" [showClear]="true" placeholder="Dystans" ariaLabel="Dystans"
            optionLabel="label" optionValue="value" />
          <p-select [options]="poolOptions" [(ngModel)]="pool" [showClear]="true" placeholder="Basen" ariaLabel="Basen"
            optionLabel="label" optionValue="value" />
        </div>

        <app-results-table [rows]="filtered()" [names]="names()" [season]="season()" [best]="best()" emptyText="Brak wyników pasujących do filtrów." />
      }
    </div>
  `,
  styles: [`
    .page-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
    .page-toolbar .swim-page-title { margin: 0; }
    .season { display: flex; align-items: center; gap: .5rem; color: var(--swim-muted); font-size: .85rem; }
    .center-spin { display: flex; justify-content: center; padding: 3rem; }
    .load-error { display: flex; flex-direction: column; align-items: flex-start; gap: .75rem; }
    .empty  { color: var(--swim-muted); text-align: center; padding: 2rem; }
    .empty a { color: var(--swim-gold); }
    .tiles  { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .75rem; margin-bottom: 1.25rem; }
    .tile   { background: var(--swim-card); border: 1px solid var(--swim-border); border-radius: 8px; padding: .9rem 1rem; display: flex; flex-direction: column; gap: .2rem; }
    .tile b { font-size: 1.7rem; line-height: 1.1; color: var(--swim-text); font-variant-numeric: tabular-nums; }
    .tile span { color: var(--swim-muted); font-size: .85rem; }
    .charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(420px, 100%), 1fr)); gap: .75rem; margin-bottom: 1.5rem; align-items: start; }
    .chart-card { background: var(--swim-card); border: 1px solid var(--swim-border); border-radius: 8px; padding: .85rem 1rem; min-width: 0; }
    .chart-card h2 { margin: 0 0 .5rem; font-size: .95rem; font-weight: 600; color: var(--swim-text); }
    .chart-empty { color: var(--swim-muted); font-size: .85rem; margin: 1rem 0; }
    .filters { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; }
    .search-box { flex: 1; min-width: min(220px, 100%); max-width: 420px; }
  `],
})
export class StatsComponent implements OnInit {
  private api    = inject(ApiService);
  private route  = inject(ActivatedRoute);
  private router = inject(Router);

  readonly plural = plural;
  readonly years  = seasonYears();
  readonly strokeOptions = STROKES;
  readonly poolOptions   = [{ label: '25 m', value: 25 }, { label: '50 m', value: 50 }];

  season    = signal(parseSeason(this.route.snapshot.queryParamMap.get('rok')));
  members   = signal<ClubMember[]>([]);
  results   = signal<MemberResult[]>([]);
  loaded    = signal(false);
  loadError = signal<string | null>(null);

  query    = signal('');
  stroke   = signal<SwimKind | null>(null);
  distance = signal<number | null>(null);
  pool     = signal<number | null>(null);

  names   = computed(() => new Map(this.members().map(m => [m.memberId, m.memberName])));
  rows    = computed<Row[]>(() => this.results().map(r => ({ ...r, memberName: this.names().get(r.memberId) ?? '—' })));
  best    = computed(() => seasonBests(this.rows()));
  summary = computed(() => seasonSummary(this.rows()));

  distanceOptions = computed(() =>
    [...new Set(this.rows().map(r => r.distance))].sort((a, b) => a - b).map(d => ({ label: `${d} m`, value: d })));

  filtered = computed(() => filterResults(this.rows(), {
    query: this.query(), stroke: this.stroke(), distance: this.distance(), pool: this.pool(),
  }));

  private totals = computed(() => memberTotals(this.rows(), this.names()));
  byStarts = computed(() => topBy(this.totals(), 'starts', CHART_MEMBERS));
  byPoints = computed(() => topBy(this.totals(), 'bestPoints', CHART_MEMBERS));

  startsChart = computed(() => this.ranking(this.byStarts(), t => t.starts, n => `${n} ${plural(n, 'start', 'starty', 'startów')}`, true));
  pointsChart = computed(() => this.ranking(this.byPoints(), t => t.bestPoints ?? 0, n => `${n} pkt`, false));

  /** Only the answer for the season chosen last is applied — switching seasons cancels the request in flight. */
  private request = latestRequest((season: number) =>
    forkJoin({ account: this.api.getAccount(), results: this.api.getAccountResults(season) }));

  constructor() {
    this.request.outcome$.pipe(takeUntilDestroyed()).subscribe(outcome => {
      if ('error' in outcome) {
        const err = outcome.error as { error?: { error?: string } };
        this.loadError.set(err.error?.error ?? 'Nie udało się wczytać wyników.');
        return;
      }
      this.members.set(outcome.data.account.clubItems.clubMembers);
      this.results.set(outcome.data.results);
      this.loaded.set(true);
    });
  }

  ngOnInit() { this.load(); }

  /** " (15 z 40 zawodników)" when the ranking shows only the top of the members that have the value. */
  chartNote(shown: number, key: 'starts' | 'bestPoints'): string {
    const all = countWith(this.totals(), key);
    return shown < all ? ` (${shown} z ${all} zawodników)` : '';
  }

  barHeight(bars: number): string {
    return `${bars * 28 + 36}px`;
  }

  changeSeason(year: number) {
    if (year === this.season()) return;
    this.season.set(year);
    this.router.navigate([], { relativeTo: this.route, queryParams: { rok: year }, replaceUrl: true });
    this.load();
  }

  /** Choosing the same season again loads nothing, so a retry loads directly. */
  retry() { this.load(); }

  /** Horizontal bars, highest on top; one series — the card heading names it, so no legend. */
  private ranking(list: MemberTotal[], value: (t: MemberTotal) => number, format: (n: number) => string, integers: boolean): EChartsCoreOption {
    const theme = chartTheme();
    return {
      ...theme.base,
      tooltip: {
        ...theme.base.tooltip, trigger: 'axis', axisPointer: { type: 'shadow' },
        formatter: (p: { name: string; value: number }[]) => rankingTooltip(p[0].name, format(p[0].value)),
      },
      xAxis: { type: 'value', ...theme.valueAxis, ...(integers ? { minInterval: 1 } : {}) },
      yAxis: { type: 'category', inverse: true, data: list.map(t => t.name), ...theme.labelAxis },
      series: [{ type: 'bar', data: list.map(value), barMaxWidth: 14, itemStyle: { borderRadius: [0, 4, 4, 0] } }],
    };
  }

  private load() {
    this.loaded.set(false);
    this.loadError.set(null);
    this.request.load(this.season());
  }
}
