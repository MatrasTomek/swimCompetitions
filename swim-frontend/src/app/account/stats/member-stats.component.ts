import { Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Select } from 'primeng/select';
import { Message } from 'primeng/message';
import { ProgressSpinner } from 'primeng/progressspinner';
import { combineLatest, distinctUntilChanged, forkJoin, map } from 'rxjs';
import type { EChartsCoreOption } from 'echarts/core';
import { HeaderComponent } from '../../shared/header/header.component';
import { ChartComponent, chartTheme } from '../../shared/chart/chart.component';
import { resultTooltip } from '../../shared/chart-tooltip';
import { latestRequest } from '../../shared/latest-request';
import { plural } from '../../shared/plural';
import { eventLabel, eventOptions, formatSwimTime, progression, seasonBests, sortedBests } from '../../shared/swim-time';
import { ApiService } from '../../core/services/api.service';
import { ClubMember, MemberResult } from '../../core/models';
import { ResultsTableComponent } from './results-table.component';
import { parseSeason, seasonYears } from './season';

/** /konto/statystyki/:memberId — one club member's season: progression in an event, season bests and every start. */
@Component({
  selector: 'app-member-stats',
  imports: [FormsModule, DatePipe, RouterLink, Select, Message, ProgressSpinner, HeaderComponent, ChartComponent, ResultsTableComponent],
  template: `
    <app-header />
    <div class="swim-page">
      <a routerLink="/konto/statystyki" [queryParams]="{ rok: season() }" class="back">← Statystyki klubu</a>

      @if (notFound()) {
        <p class="empty">Nie znaleziono zawodnika. <a routerLink="/konto/zawodnicy">Wróć do listy zawodników</a>.</p>
      } @else if (loadError()) {
        <p-message severity="error" [text]="loadError()!" />
      } @else if (!loaded()) {
        <div class="center-spin"><p-progressSpinner /></div>
      } @else if (member(); as m) {
        <div class="page-toolbar">
          <div>
            <h1 class="swim-page-title">{{ m.memberName }}</h1>
            <p class="sub">rocznik {{ m.memberBirthYear }} · {{ m.memberSex === 'K' ? 'kobieta' : 'mężczyzna' }}</p>
          </div>
          <label class="season">Sezon
            <p-select [options]="years" [ngModel]="season()" (ngModelChange)="changeSeason($event)" ariaLabel="Sezon" />
          </label>
        </div>

        @if (!results().length) {
          <p class="empty">
            Brak wyników w sezonie {{ season() }}. Pobierz je przyciskiem „Pobierz wyniki na konto” na stronie
            <a routerLink="/">zaimportowanej listy startowej</a>.
          </p>
        } @else {
          <div class="tiles">
            <div class="tile"><b>{{ results().length }}</b><span>{{ plural(results().length, 'start', 'starty', 'startów') }}</span></div>
            <div class="tile"><b>{{ contests() }}</b><span>{{ plural(contests(), 'zawody', 'zawody', 'zawodów') }}</span></div>
            <div class="tile"><b>{{ events().length }}</b><span>{{ plural(events().length, 'konkurencja', 'konkurencje', 'konkurencji') }}</span></div>
          </div>

          <section class="card">
            <div class="card-head">
              <h2>Progresja czasu</h2>
              <p-select [options]="events()" [ngModel]="event()" (ngModelChange)="chosenEvent.set($event)"
                optionLabel="label" optionValue="value" ariaLabel="Konkurencja" />
            </div>
            <p class="card-note">Krótszy czas jest wyżej. Czasy z basenu 25 m i 50 m to osobne linie — nie porównuje się ich ze sobą.</p>
            <app-chart [options]="progressChart()" height="300px" [label]="'Progresja czasu: ' + eventName()" />
          </section>

          <section class="card">
            <h2>Najlepsze czasy w sezonie</h2>
            <div class="table-scroll">
              <table class="pb-table">
                <thead><tr><th>Konkurencja</th><th>Basen</th><th>Czas</th><th>Data</th><th>Zawody</th></tr></thead>
                <tbody>
                  @for (b of bests(); track b.distance + '|' + b.stroke + '|' + b.poolLength) {
                    <tr>
                      <td class="nowrap">{{ label(b) }}</td>
                      <td class="nowrap">{{ b.poolLength }} m</td>
                      <td class="time">{{ b.time }}</td>
                      <td class="nowrap">{{ b.date | date:'dd.MM.yyyy' }}</td>
                      <td>{{ b.contestName }}</td>
                    </tr>
                  }
                </tbody>
              </table>
            </div>
          </section>

          <h2 class="section-title">Wszystkie starty</h2>
          <app-results-table [rows]="results()" [best]="best()" />
        }
      }
    </div>
  `,
  styles: [`
    .back { color: var(--swim-muted); font-size: .85rem; text-decoration: none; }
    .back:hover { color: var(--swim-gold); }
    .page-toolbar { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin: .5rem 0 1rem; }
    .page-toolbar .swim-page-title { margin: 0; }
    .sub    { color: var(--swim-muted); margin: .2rem 0 0; font-size: .9rem; }
    .season { display: flex; align-items: center; gap: .5rem; color: var(--swim-muted); font-size: .85rem; }
    .empty  { color: var(--swim-muted); text-align: center; padding: 2rem; }
    .empty a { color: var(--swim-gold); }
    .center-spin { display: flex; justify-content: center; padding: 3rem; }
    .tiles  { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: .75rem; margin-bottom: 1.25rem; }
    .tile   { background: var(--swim-card); border: 1px solid var(--swim-border); border-radius: 8px; padding: .9rem 1rem; display: flex; flex-direction: column; gap: .2rem; }
    .tile b { font-size: 1.7rem; line-height: 1.1; color: var(--swim-text); font-variant-numeric: tabular-nums; }
    .tile span { color: var(--swim-muted); font-size: .85rem; }
    .card   { background: var(--swim-card); border: 1px solid var(--swim-border); border-radius: 8px; padding: .85rem 1rem; margin-bottom: 1.25rem; min-width: 0; }
    .card h2, .section-title { margin: 0 0 .5rem; font-size: .95rem; font-weight: 600; color: var(--swim-text); }
    .card-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
    .card-head h2 { margin: 0; }
    .card-note { color: var(--swim-muted); font-size: .8rem; margin: .4rem 0 .5rem; }
    .table-scroll { overflow-x: auto; }
    .pb-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .pb-table th, .pb-table td { padding: .45rem .6rem; border-bottom: 1px solid var(--swim-border); text-align: left; }
    .pb-table th { color: var(--swim-muted); font-weight: 600; }
    .time   { font-variant-numeric: tabular-nums; font-weight: 700; white-space: nowrap; }
    .nowrap { white-space: nowrap; }
  `],
})
export class MemberStatsComponent {
  private api    = inject(ApiService);
  private route  = inject(ActivatedRoute);
  private router = inject(Router);

  readonly plural   = plural;
  readonly years    = seasonYears();

  /** Both come from the URL: the component is reused when only :memberId or ?rok= changes, so both are followed, not read once. */
  memberId  = signal('');
  season    = signal(parseSeason(null));
  member    = signal<ClubMember | null>(null);
  results   = signal<MemberResult[]>([]);
  loaded    = signal(false);
  notFound  = signal(false);
  loadError = signal<string | null>(null);

  best     = computed(() => seasonBests(this.results()));
  bests    = computed(() => sortedBests(this.results()));
  contests = computed(() => new Set(this.results().map(r => r.contestUuid)).size);
  events   = computed(() => eventOptions(this.results()));

  /** The user's pick, kept across seasons while the member still has that event; otherwise the most swum one. */
  chosenEvent = signal('');
  event = computed(() => this.events().some(e => e.value === this.chosenEvent()) ? this.chosenEvent() : (this.events()[0]?.value ?? ''));
  eventName = computed(() => this.events().find(e => e.value === this.event())?.label ?? '');

  progressChart = computed<EChartsCoreOption>(() => {
    const theme  = chartTheme();
    const series = progression(this.results(), this.event());
    return {
      ...theme.base,
      grid: { ...theme.base.grid, top: series.length > 1 ? 34 : 12 },
      // Identity is never colour-alone: a legend whenever both pools have swims
      legend: { show: series.length > 1, top: 0, left: 0, textStyle: { color: theme.text }, icon: 'roundRect', itemWidth: 14, itemHeight: 4 },
      tooltip: {
        ...theme.base.tooltip, trigger: 'item',
        formatter: (p: { data: { row: MemberResult } }) => resultTooltip(p.data.row),
      },
      // Padding on both axes keeps the first and last markers inside the plot
      xAxis: {
        type: 'time', boundaryGap: ['4%', '4%'], ...theme.labelAxis,
        axisLabel: { ...theme.labelAxis.axisLabel, color: theme.muted, fontSize: 12, hideOverlap: true, formatter: '{dd}.{MM}' },
      },
      yAxis: {
        type: 'value', inverse: true, scale: true, boundaryGap: ['8%', '8%'], ...theme.valueAxis,
        axisLabel: { ...theme.valueAxis.axisLabel, formatter: (ms: number) => formatSwimTime(ms) },
      },
      series: series.map(s => ({
        type: 'line', name: `Basen ${s.pool} m`, lineStyle: { width: 2 }, symbol: 'circle', symbolSize: 9, showSymbol: true,
        // Colour follows the pool, not the series position — a lone 50 m line keeps the 50 m colour
        color: theme.base.color[s.pool === 25 ? 0 : 1],
        data: s.rows.map(row => ({ value: [row.date, row.timeMs], row })),
      })),
    };
  });

  /** Only the answer for the member and season chosen last is applied — a new choice cancels the request in flight. */
  private request = latestRequest(({ memberId, season }: { memberId: string; season: number }) =>
    forkJoin({ account: this.api.getAccount(), results: this.api.getAccountResults(season, memberId) }));

  constructor() {
    this.request.outcome$.pipe(takeUntilDestroyed()).subscribe(outcome => {
      if ('error' in outcome) {
        const err = outcome.error as { status?: number; error?: { error?: string } };
        if (err.status === 404) this.notFound.set(true);
        else this.loadError.set(err.error?.error ?? 'Nie udało się wczytać wyników.');
        return;
      }
      const member = outcome.data.account.clubItems.clubMembers.find(m => m.memberId === outcome.key.memberId) ?? null;
      if (!member) { this.notFound.set(true); return; }
      this.member.set(member);
      this.results.set(outcome.data.results);
      this.loaded.set(true);
    });

    // After outcome$ is subscribed: the route emits synchronously and latestRequest does not replay
    combineLatest([this.route.paramMap, this.route.queryParamMap]).pipe(
      map(([params, query]) => ({ memberId: params.get('memberId') ?? '', season: parseSeason(query.get('rok')) })),
      distinctUntilChanged((a, b) => a.memberId === b.memberId && a.season === b.season),
      takeUntilDestroyed(),
    ).subscribe(key => {
      if (key.memberId !== this.memberId()) {
        // Another member: nothing of the previous one may stay on screen
        this.member.set(null);
        this.results.set([]);
        this.chosenEvent.set('');
      }
      this.memberId.set(key.memberId);
      this.season.set(key.season);
      this.load();
    });
  }

  label(r: MemberResult): string { return eventLabel(r.distance, r.stroke); }

  changeSeason(year: number) {
    if (year === this.season()) return;
    // The query param subscription loads the season
    this.router.navigate([], { relativeTo: this.route, queryParams: { rok: year }, replaceUrl: true });
  }

  private load() {
    this.loaded.set(false);
    this.notFound.set(false);
    this.loadError.set(null);
    this.request.load({ memberId: this.memberId(), season: this.season() });
  }
}
