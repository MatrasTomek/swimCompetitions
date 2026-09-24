import { Component, inject, signal, computed, OnInit, OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { InputText } from 'primeng/inputtext';
import { Button } from 'primeng/button';
import { Message } from 'primeng/message';
import { Card } from 'primeng/card';
import { Toast } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { HeaderComponent } from '../../shared/header/header.component';
import { SearchInputComponent } from '../../shared/search-input/search-input.component';
import { ApiService } from '../../core/services/api.service';
import { AuthService } from '../../core/services/auth.service';
import { LocalCompetitionsService } from '../../core/services/local-competitions.service';
import { Competition, LtContest, LtCacheStatus } from '../../core/models';

const FORM_STORAGE_KEY = 'swim_import_form';

/** Start lists are imported only from a livetiming.pl contest page (mirrors `sl_contest_uuid()` on the server). */
const CONTEST_URL_RE =
  /^https?:\/\/(www\.)?livetiming\.pl\/contest\/[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}\/?([?#].*)?$/i;

/** Import form fields remembered in this browser session. */
interface ImportFormState {
  searchQuery: string;
  contest: LtContest | null;
  manualUrl: string;
  klub: string;
}

@Component({
  selector: 'app-import',
  imports: [FormsModule, RouterLink, InputText, Button, Message, Card, Toast, HeaderComponent, SearchInputComponent],
  providers: [MessageService],
  template: `
    <app-header />
    <div class="swim-page">
      <a routerLink="/" class="back">← Zawody</a>
      <h1 class="swim-page-title">Pobierz listę startową</h1>

      <p-card styleClass="import-card">
        <div class="form">

          <!-- 1. livetiming.pl cache -->
          <div class="field">
            <label>Połączenie z livetiming.pl</label>
            <div class="cache-strip">
              <span class="cache-status-label">
                @if (cacheRefreshing()) {
                  <span class="cache-refreshing"><span class="lt-spinner"></span>Trwa łączenie z livetiming.pl…</span>
                } @else if (cacheError()) {
                  <span class="warn">brak połączenia</span>
                } @else if (!cacheStatus()) {
                  <span class="muted">sprawdzanie…</span>
                } @else if (!cacheStatus()!.exists) {
                  <span class="warn">brak listy zawodów</span>
                } @else {
                  <span [class]="cacheStatus()!.is_fresh ? 'ok' : 'warn'">
                    {{ cacheStatus()!.is_fresh ? 'połączono' : 'lista nieaktualna' }}
                  </span>
                  &nbsp;·&nbsp; {{ cacheStatus()!.count ?? 0 }} zawodów
                  @if (cacheStatus()!.age_hours !== undefined) {
                    &nbsp;·&nbsp; {{ cacheStatus()!.age_hours }}h temu
                  }
                }
              </span>
              @if (!cacheRefreshing() && (cacheError() || (cacheStatus() && (!connected() || auth.isLoggedIn())))) {
                <p-button
                  [label]="connected() ? '↺ Odśwież' : 'Połącz'"
                  size="small"
                  severity="secondary"
                  (onClick)="doRefreshCache()"
                  styleClass="cache-btn" />
              }
            </div>
          </div>

          @if (!connected()) {
            @if (cacheStatus() || cacheError()) {
              <small class="hint">Połącz się z livetiming.pl, aby wyszukać zawody i pobrać listę startową.</small>
            }
          } @else {

          <!-- 2. Competition -->
          <div class="field">
            <label for="search-input">Zawody — nazwa lub miasto *</label>
            @if (contest(); as c) {
              <div class="result-card selected">
                <div class="result-info">
                  <div class="result-name">{{ c.name }}</div>
                  <div class="result-meta">{{ c.city }}{{ c.city && c.date ? ' · ' : '' }}{{ c.date }}</div>
                </div>
                <button type="button" class="link-btn" (click)="clearContest()">zmień</button>
              </div>
            } @else {
              <app-search-input class="w-full" inputId="search-input" [highlight]="false"
                placeholder="np. Kraków, Mistrzostwa Małopolski…"
                [value]="searchQuery" (valueChange)="searchQuery = $event; onSearchChange()" />

              @if (searchLoading()) {
                <div class="search-hint">Wyszukiwanie…</div>
              }

              @if (searchResults().length > 0) {
                <div class="search-results">
                  @for (r of searchResults(); track r.uuid) {
                    <div class="result-card" (click)="selectContest(r)">
                      <div class="result-info">
                        <div class="result-name">{{ r.name }}</div>
                        <div class="result-meta">{{ r.city }}{{ r.city && r.date ? ' · ' : '' }}{{ r.date }}</div>
                      </div>
                      @if (r.category) {
                        <span class="cat-badge" [style.color]="CAT_COLOR[r.category] || '#888'">
                          {{ CAT_LABEL[r.category] || r.category }}
                        </span>
                      }
                    </div>
                  }
                </div>
              }

              @if (searchQuery.trim().length >= 2 && !searchLoading() && searchResults().length === 0) {
                <div class="search-hint">Nie znaleziono. Spróbuj innej frazy lub połącz się ponownie z livetiming.pl.</div>
              }

              <details class="manual-url" [open]="!!manualUrl">
                <summary>Masz link do strony zawodów na livetiming.pl? Wklej go</summary>
                <input pInputText [(ngModel)]="manualUrl" (ngModelChange)="saveForm()"
                  placeholder="https://livetiming.pl/contest/..." class="w-full" />
              </details>
            }
          </div>

          <!-- 3. Club -->
          <div class="field">
            <label for="klub-input">Klub *</label>
            <input id="klub-input" pInputText [(ngModel)]="klub" (ngModelChange)="saveForm()"
              placeholder="np. Olimpijczyk" class="w-full" />
          </div>

          @if (error()) { <p-message severity="error" [text]="error()!" /> }

          <div class="actions">
            <p-button label="Pobierz listę startową" [loading]="loading()"
              [disabled]="loading()" (onClick)="doImport()" />
          </div>
          <small class="hint">
            Lista zostanie zapisana tylko w tej przeglądarce — nie trafia na serwer i nie jest publikowana na stronie.
          </small>
          }
        </div>
      </p-card>
    </div>
    <p-toast />
  `,
  styles: [`
    .back           { display: inline-block; margin-bottom: 1rem; color: var(--swim-muted); text-decoration: none; font-size: .85rem; }
    .import-card    { max-width: 650px; background: var(--swim-card) !important; }
    .form           { display: flex; flex-direction: column; gap: 1.25rem; }
    .field          { display: flex; flex-direction: column; gap: .4rem; min-width: 0; }
    .field label    { font-size: .85rem; color: var(--swim-muted); }
    .w-full         { width: 100%; }
    .hint           { font-size: .78rem; color: var(--swim-muted); }
    .actions        { display: flex; gap: 1rem; flex-wrap: wrap; }

    /* Cache strip */
    .cache-strip      { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; background: #0a0a0a; border: 1px solid #1e1e1e; border-radius: 5px; padding: .4rem .75rem; font-size: .78rem; color: #777; }
    .cache-status-label { flex: 1; }
    .ok   { color: #4caf50; }
    .warn { color: #fa8030; }
    .muted { color: #555; }
    :host ::ng-deep .cache-btn .p-button { padding: .2rem .6rem; font-size: .72rem; }
    .cache-refreshing { display: inline-flex; align-items: center; gap: .45rem; color: #f0a800; white-space: nowrap; }
    .lt-spinner       { display: inline-block; width: 12px; height: 12px; border: 2px solid #f0a800; border-top-color: transparent; border-radius: 50%; animation: lt-spin .7s linear infinite; flex-shrink: 0; }
    @keyframes lt-spin { to { transform: rotate(360deg); } }

    /* Search results */
    .search-results { display: flex; flex-direction: column; gap: .35rem; max-height: 320px; overflow-y: auto; }
    .result-card    { background: #161616; border: 1px solid #252525; border-radius: 6px; padding: .55rem .85rem; cursor: pointer; display: flex; align-items: center; gap: .7rem; transition: border-color .12s; }
    .result-card:hover { border-color: #f0a800; }
    .result-card.selected { cursor: default; border-color: #f0a800; }
    .result-info    { flex: 1; min-width: 0; }
    .result-name    { color: #e8e8e8; font-size: .85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .result-meta    { color: #555; font-size: .74rem; margin-top: .1rem; }
    .cat-badge      { font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 2px 6px; border-radius: 3px; background: #111; flex-shrink: 0; }
    .search-hint    { color: #555; font-size: .82rem; }
    .link-btn       { background: none; border: none; color: #f0a800; cursor: pointer; padding: 0; font-size: .82rem; text-decoration: underline; }

    .manual-url         { font-size: .8rem; }
    .manual-url summary { cursor: pointer; color: var(--swim-muted); margin-bottom: .4rem; }
  `]
})
export class ImportComponent implements OnInit, OnDestroy {
  private api    = inject(ApiService);
  private router = inject(Router);
  private msg    = inject(MessageService);
  private local  = inject(LocalCompetitionsService);
  readonly auth  = inject(AuthService);

  // ── Form state (persisted in sessionStorage) ─────────────────────────
  searchQuery = '';
  contest     = signal<LtContest | null>(null);
  manualUrl   = '';
  klub        = '';

  // ── Search state ─────────────────────────────────────────────────────
  searchResults = signal<LtContest[]>([]);
  searchLoading = signal(false);
  private searchTimer: ReturnType<typeof setTimeout> | null = null;

  cacheStatus     = signal<LtCacheStatus | null>(null);
  cacheRefreshing = signal(false);
  cacheError      = signal(false);
  /** Search and import fields are shown only with an up-to-date contest list from livetiming.pl. */
  connected       = computed(() => !!this.cacheStatus()?.is_fresh);

  readonly CAT_LABEL: Record<string, string> = {
    regional: 'okręgowe', national: 'centralne',
    calendar: 'kalendarz', international: 'międzynarodowe',
  };
  readonly CAT_COLOR: Record<string, string> = {
    regional: '#6ab0ee', national: '#a07eee',
    calendar: '#6dcfa0', international: '#e8a060',
  };

  // ── Import state ─────────────────────────────────────────────────────
  loading = signal(false);
  error   = signal<string | null>(null);

  ngOnInit() {
    this.restoreForm();
    if (!this.contest() && this.searchQuery.trim().length >= 2) this.onSearchChange();
    this.api.getCacheStatus().subscribe({
      next: s => this.cacheStatus.set(s),
      error: () => this.cacheError.set(true),
    });
  }

  ngOnDestroy() {
    if (this.searchTimer) clearTimeout(this.searchTimer);
  }

  // ── Session persistence ──────────────────────────────────────────────
  saveForm() {
    const state: ImportFormState = {
      searchQuery: this.searchQuery,
      contest:     this.contest(),
      manualUrl:   this.manualUrl,
      klub:        this.klub,
    };
    try {
      sessionStorage.setItem(FORM_STORAGE_KEY, JSON.stringify(state));
    } catch {
      // Storage unavailable — the form still works, it just won't be remembered.
    }
  }

  private restoreForm() {
    try {
      const s = JSON.parse(sessionStorage.getItem(FORM_STORAGE_KEY) ?? 'null') as Partial<ImportFormState> | null;
      if (!s) return;
      this.searchQuery = s.searchQuery ?? '';
      this.contest.set(s.contest?.uuid ? s.contest : null);
      this.manualUrl   = s.manualUrl ?? '';
      this.klub        = s.klub ?? '';
    } catch {
      // Corrupt or unavailable storage — start with an empty form.
    }
  }

  // ── Search ───────────────────────────────────────────────────────────
  onSearchChange() {
    this.saveForm();
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
    this.contest.set(c);
    this.searchResults.set([]);
    this.error.set(null);
    this.saveForm();
  }

  clearContest() {
    this.contest.set(null);
    this.saveForm();
    if (this.searchQuery.trim().length >= 2) this.onSearchChange();
  }

  doRefreshCache() {
    this.cacheRefreshing.set(true);
    this.api.refreshContestCache().subscribe({
      next: res => {
        this.cacheStatus.set(res.status);
        this.cacheError.set(false);
        this.cacheRefreshing.set(false);
        if (!res.ok || !res.status.is_fresh) {
          this.msg.add({ severity: 'error', summary: 'Błąd', detail: 'Nie udało się połączyć z livetiming.pl.' });
          return;
        }
        if (!this.contest() && this.searchQuery.trim().length >= 2) this.onSearchChange();
      },
      error: () => {
        this.cacheRefreshing.set(false);
        this.msg.add({ severity: 'error', summary: 'Błąd', detail: 'Nie udało się połączyć z livetiming.pl.' });
      },
    });
  }

  // ── Import ───────────────────────────────────────────────────────────
  private get contestUrl(): string {
    const c = this.contest();
    return c ? `https://livetiming.pl/contest/${c.uuid}` : this.manualUrl.trim();
  }

  doImport() {
    const url  = this.contestUrl;
    const klub = this.klub.trim();
    if (!url)  { this.error.set('Wybierz zawody z listy lub wklej link.'); return; }
    if (!CONTEST_URL_RE.test(url)) {
      this.error.set('Wklej link do strony zawodów z livetiming.pl (https://livetiming.pl/contest/…), nie do pliku PDF.');
      return;
    }
    if (!klub) { this.error.set('Wpisz nazwę klubu.'); return; }

    this.loading.set(true);
    this.error.set(null);
    this.api.previewStartlist(url, klub).subscribe({
      next: res => {
        if (!res.ok) { this.fail(res.error ?? 'Błąd parsowania.'); return; }
        const item = this.local.add(this.withContestFallbacks(res.zawody));
        this.router.navigate(['/moje', item.id, 'lista']);
      },
      error: err => this.fail(err.error?.error ?? 'Błąd połączenia.'),
    });
  }

  /**
   * Names the competition exactly as the contest search showed it, and fills place/date the server
   * couldn't read with what livetiming.pl listed for the chosen contest.
   */
  private withContestFallbacks(zawody: Competition): Competition {
    const c = this.contest();
    if (!c) return zawody;
    return {
      ...zawody,
      nazwa:   c.name         || zawody.nazwa,
      miejsce: zawody.miejsce || c.city,
      data:    zawody.data    || c.date,
    };
  }

  private fail(message: string) {
    this.error.set(message);
    this.loading.set(false);
  }
}
