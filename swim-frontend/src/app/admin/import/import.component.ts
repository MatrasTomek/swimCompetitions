import { Component, inject, signal, OnInit, OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Steps } from 'primeng/steps';
import { InputText } from 'primeng/inputtext';
import { SelectButton } from 'primeng/selectbutton';
import { Button } from 'primeng/button';
import { Message } from 'primeng/message';
import { Card } from 'primeng/card';
import { Toast } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { Competition, StartlistPreviewResponse, LtContest, LtCacheStatus } from '../../core/models';

@Component({
  selector: 'app-import',
  imports: [FormsModule, RouterLink, Steps, InputText, SelectButton, Button, Message, Card, Toast, HeaderComponent],
  providers: [MessageService],
  template: `
    <app-header [isAdmin]="true" />
    <div class="swim-page">
      <a routerLink="/admin/zawody" class="back">← Lista zawodów</a>
      <h1 class="swim-page-title">Import listy startowej z PDF</h1>

      <p-steps [model]="steps" [activeIndex]="activeStep" styleClass="import-steps" />

      <p-card styleClass="import-card">
        @if (activeStep === 0) {
          <div class="step-content">

            <!-- Cache status strip -->
            <div class="cache-strip">
              <span class="cache-status-label">
                Cache livetiming.pl:
                @if (cacheStatus()) {
                  <span [style.color]="cacheStatus()!.is_fresh ? '#4caf50' : '#fa8030'">
                    {{ cacheStatus()!.is_fresh ? 'aktualny' : 'nieaktualny' }}
                  </span>
                  &nbsp;·&nbsp; {{ cacheStatus()!.count ?? 0 }} zawodów
                  @if (cacheStatus()!.age_hours !== undefined) {
                    &nbsp;·&nbsp; {{ cacheStatus()!.age_hours }}h temu
                  }
                } @else {
                  <span style="color:#555">sprawdzanie…</span>
                }
              </span>
              @if (cacheRefreshing()) {
                <span class="cache-refreshing"><span class="lt-spinner"></span>…Trwa łączenie z LiveTiming</span>
              } @else if (cacheStatus() && !cacheStatus()!.is_fresh) {
                <p-button
                  label="↺ Odśwież"
                  size="small"
                  severity="secondary"
                  (onClick)="doRefreshCache()"
                  styleClass="cache-btn" />
              }
            </div>

            <!-- Search input -->
            <div class="field">
              <label for="search-input">Wyszukaj zawody na livetiming.pl</label>
              <input
                id="search-input"
                pInputText
                [(ngModel)]="searchQuery"
                (ngModelChange)="onSearchChange()"
                placeholder="Wpisz nazwę miasta lub zawodów..."
                class="w-full"
                autocomplete="off" />
              <small class="hint">Wpisz min. 2 znaki — kliknij wynik, żeby wypełnić URL.</small>
            </div>

            @if (searchLoading()) {
              <div class="search-hint">Wyszukiwanie…</div>
            }

            @if (searchResults().length > 0) {
              <div class="search-results">
                @for (c of searchResults(); track c.uuid) {
                  <div class="result-card" (click)="selectContest(c)">
                    <div class="result-info">
                      <div class="result-name">{{ c.name }}</div>
                      <div class="result-meta">
                        {{ c.city }}{{ c.city && c.date ? ' · ' : '' }}{{ c.date }}
                      </div>
                    </div>
                    @if (c.category) {
                      <span class="cat-badge" [style.color]="CAT_COLOR[c.category] || '#888'">
                        {{ CAT_LABEL[c.category] || c.category }}
                      </span>
                    }
                  </div>
                }
              </div>
            }

            @if (searchQuery.length >= 2 && !searchLoading() && searchResults().length === 0) {
              <div class="search-hint">
                Nie znaleziono. Spróbuj innej frazy lub
                <button type="button" class="link-btn" (click)="doRefreshCache()">odśwież cache</button>.
              </div>
            }

            <div class="divider-or">
              <div class="divider-line"></div>
              <span>lub podaj URL bezpośrednio</span>
              <div class="divider-line"></div>
            </div>

            <!-- URL + remaining fields -->
            <div class="field">
              <label>URL zawodów (livetiming.pl) *</label>
              <input pInputText [(ngModel)]="contestUrl"
                placeholder="https://livetiming.pl/contest/..." class="w-full" />
            </div>
            <div class="field">
              <label>Filtr klubu *</label>
              <input pInputText [(ngModel)]="klub" placeholder="Olimpijczyk" class="w-full" />
            </div>
            <div class="field">
              <label>Długość basenu</label>
              <p-selectbutton [options]="basenOpts" [(ngModel)]="basen"
                optionLabel="label" optionValue="value" />
            </div>
            @if (previewError()) { <p-message severity="error" [text]="previewError()!" /> }
            <p-button label="Podgląd →" [loading]="previewLoading()" (onClick)="doPreview()" />
          </div>
        }

        @if (activeStep === 1 && preview()) {
          <div class="step-content">
            <div class="stats-row">
              <div class="stat"><strong>{{ preview()!.stats.starts }}</strong><span>startów</span></div>
              <div class="stat"><strong>{{ preview()!.stats.athletes }}</strong><span>zawodników</span></div>
              <div class="stat"><strong>{{ preview()!.stats.blocks }}</strong><span>bloków</span></div>
            </div>
            <div class="meta-fields">
              <div class="field">
                <label>Nazwa zawodów</label>
                <input pInputText [(ngModel)]="preview()!.zawody.nazwa" class="w-full" />
              </div>
              <div class="field-row">
                <div class="field">
                  <label>Miejsce</label>
                  <input pInputText [(ngModel)]="preview()!.zawody.miejsce" class="w-full" />
                </div>
                <div class="field">
                  <label>Data</label>
                  <input pInputText [(ngModel)]="preview()!.zawody.data" class="w-full" />
                </div>
              </div>
              <div class="field">
                <label>Klub</label>
                <input pInputText [(ngModel)]="preview()!.zawody.klub" class="w-full" />
              </div>
            </div>
            @if (saveError()) { <p-message severity="error" [text]="saveError()!" /> }
            <div class="step-btns">
              <p-button label="← Wróć" severity="secondary" (onClick)="activeStep=0" />
              <p-button label="Zapisz →" [loading]="saveLoading()" (onClick)="doSave()" />
            </div>

            @if (preview()!.raw_text) {
              <details class="raw-details">
                <summary>Pokaż surowy tekst PDF</summary>
                <pre class="raw-text">{{ preview()!.raw_text }}</pre>
              </details>
            }
          </div>
        }
      </p-card>
    </div>
    <p-toast />
  `,
  styles: [`
    .back           { display: inline-block; margin-bottom: 1rem; color: var(--swim-muted); text-decoration: none; font-size: .85rem; }
    .import-card    { max-width: 650px; background: var(--swim-card) !important; }
    .import-steps   { margin-bottom: 1.5rem; }
    .step-content   { display: flex; flex-direction: column; gap: 1.25rem; }
    .field          { display: flex; flex-direction: column; gap: .4rem; }
    .field label    { font-size: .85rem; color: var(--swim-muted); }
    .field-row      { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .w-full         { width: 100%; }
    .hint           { font-size: .78rem; color: var(--swim-muted); }

    /* Cache strip */
    .cache-strip      { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; background: #0a0a0a; border: 1px solid #1e1e1e; border-radius: 5px; padding: .4rem .75rem; font-size: .78rem; color: #555; }
    .cache-status-label { flex: 1; }
    :host ::ng-deep .cache-btn .p-button { padding: .2rem .6rem; font-size: .72rem; }
    .cache-refreshing { display: inline-flex; align-items: center; gap: .45rem; color: #f0a800; white-space: nowrap; }
    .lt-spinner       { display: inline-block; width: 12px; height: 12px; border: 2px solid #f0a800; border-top-color: transparent; border-radius: 50%; animation: lt-spin .7s linear infinite; flex-shrink: 0; }
    @keyframes lt-spin { to { transform: rotate(360deg); } }

    /* Search results */
    .search-results { display: flex; flex-direction: column; gap: .35rem; }
    .result-card    { background: #161616; border: 1px solid #252525; border-radius: 6px; padding: .55rem .85rem; cursor: pointer; display: flex; align-items: center; gap: .7rem; transition: border-color .12s; }
    .result-card:hover { border-color: #f0a800; }
    .result-info    { flex: 1; min-width: 0; }
    .result-name    { color: #e8e8e8; font-size: .85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .result-meta    { color: #555; font-size: .74rem; margin-top: .1rem; }
    .cat-badge      { font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 2px 6px; border-radius: 3px; background: #111; flex-shrink: 0; }
    .search-hint    { color: #555; font-size: .82rem; }
    .link-btn       { background: none; border: none; color: #f0a800; cursor: pointer; padding: 0; font-size: .82rem; text-decoration: underline; }

    /* Divider */
    .divider-or     { display: flex; align-items: center; gap: .6rem; color: #333; font-size: .78rem; }
    .divider-line   { flex: 1; height: 1px; background: #1e1e1e; }

    /* Step 1 */
    .stats-row      { display: flex; gap: 2rem; background: #1a2a1a; border-radius: 6px; padding: 1rem; }
    .stat           { display: flex; flex-direction: column; align-items: center; }
    .stat strong    { font-size: 1.5rem; color: var(--swim-green); }
    .stat span      { font-size: .75rem; color: var(--swim-muted); }
    .meta-fields    { display: flex; flex-direction: column; gap: 1rem; }
    .step-btns      { display: flex; gap: 1rem; }
    .raw-details    { margin-top: .5rem; }
    .raw-details summary { cursor: pointer; color: var(--swim-muted); font-size: .85rem; }
    .raw-text       { font-size: .7rem; background: #111; padding: .75rem; border-radius: 4px; overflow: auto; max-height: 300px; white-space: pre-wrap; color: #aaa; }
  `]
})
export class ImportComponent implements OnInit, OnDestroy {
  private api    = inject(ApiService);
  private router = inject(Router);
  private msg    = inject(MessageService);

  // ── Search state ─────────────────────────────────────────────────────
  searchQuery   = '';
  searchResults = signal<LtContest[]>([]);
  searchLoading = signal(false);
  private searchTimer: ReturnType<typeof setTimeout> | null = null;

  cacheStatus     = signal<LtCacheStatus | null>(null);
  cacheRefreshing = signal(false);

  readonly CAT_LABEL: Record<string, string> = {
    regional: 'okręgowe', national: 'centralne',
    calendar: 'kalendarz', international: 'międzynarodowe',
  };
  readonly CAT_COLOR: Record<string, string> = {
    regional: '#6ab0ee', national: '#a07eee',
    calendar: '#6dcfa0', international: '#e8a060',
  };

  // ── Import state ─────────────────────────────────────────────────────
  contestUrl = '';
  klub       = '';
  basen      = '25m';
  basenOpts  = [{ label: '25m', value: '25m' }, { label: '50m', value: '50m' }];

  activeStep = 0;
  steps = [{ label: 'Dane wejściowe' }, { label: 'Podgląd i zapis' }];

  previewLoading = signal(false);
  previewError   = signal<string | null>(null);
  preview        = signal<StartlistPreviewResponse | null>(null);

  saveLoading = signal(false);
  saveError   = signal<string | null>(null);

  ngOnInit() {
    this.api.getCacheStatus().subscribe({
      next: s => this.cacheStatus.set(s),
      error: () => {},
    });
  }

  ngOnDestroy() {
    if (this.searchTimer) clearTimeout(this.searchTimer);
  }

  // ── Search ───────────────────────────────────────────────────────────
  onSearchChange() {
    if (this.searchTimer) clearTimeout(this.searchTimer);
    const q = this.searchQuery.trim();
    if (q.length < 2) { this.searchResults.set([]); return; }
    this.searchTimer = setTimeout(() => {
      this.searchLoading.set(true);
      this.api.searchContests(q).subscribe({
        next: res => { this.searchResults.set(res); this.searchLoading.set(false); },
        error: () => { this.searchLoading.set(false); },
      });
    }, 300);
  }

  selectContest(c: LtContest) {
    this.contestUrl   = `https://livetiming.pl/contest/${c.uuid}`;
    this.searchQuery  = '';
    this.searchResults.set([]);
  }

  doRefreshCache() {
    this.cacheRefreshing.set(true);
    this.api.refreshContestCache().subscribe({
      next: res => {
        this.cacheStatus.set(res.status);
        this.cacheRefreshing.set(false);
        if (this.searchQuery.trim().length >= 2) this.onSearchChange();
      },
      error: () => this.cacheRefreshing.set(false),
    });
  }

  // ── Preview / Save ────────────────────────────────────────────────────
  doPreview() {
    if (!this.contestUrl || !this.klub) { this.previewError.set('Uzupełnij URL i nazwę klubu.'); return; }
    this.previewLoading.set(true);
    this.previewError.set(null);
    this.api.previewStartlist(this.contestUrl, this.klub, this.basen).subscribe({
      next: res => {
        if (!res.ok) { this.previewError.set(res.error ?? 'Błąd parsowania.'); this.previewLoading.set(false); return; }
        this.preview.set(res);
        this.activeStep = 1;
        this.previewLoading.set(false);
      },
      error: err => {
        this.previewError.set(err.error?.error ?? 'Błąd połączenia.');
        this.previewLoading.set(false);
      },
    });
  }

  doSave() {
    const zawody = this.preview()?.zawody;
    if (!zawody) return;
    this.saveLoading.set(true);
    this.saveError.set(null);
    this.api.saveStartlist(zawody).subscribe({
      next: res => {
        this.msg.add({ severity: 'success', summary: 'Zapisano!', detail: res.filename });
        const slug = res.filename.replace(/\.json$/, '');
        this.router.navigate(['/admin/zawody', slug, 'edytuj']);
      },
      error: err => {
        this.saveLoading.set(false);
        this.saveError.set(err.error?.error ?? 'Błąd zapisu.');
      },
    });
  }
}
