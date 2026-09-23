import { Component, signal } from '@angular/core';
import { Button } from 'primeng/button';

const CONSENT_KEY = 'swim-cookie-info-accepted';

/** One-time notice about browser storage (localStorage/sessionStorage) and where athlete data comes from. */
@Component({
  selector: 'app-cookie-banner',
  imports: [Button],
  template: `
    @if (visible()) {
      <aside class="cookie-banner" role="region" aria-label="Informacja o plikach cookie">
        <div class="cookie-banner__text">
          <strong>Pliki cookie i pamięć przeglądarki</strong>
          <p>
            Ta strona nie używa plików cookie śledzących ani reklamowych. W pamięci Twojej przeglądarki
            (localStorage / sessionStorage) zapisujemy wyłącznie pliki z danymi potrzebnymi do działania
            serwisu: zaimportowane listy startowe, ustawienia widoku i stan formularzy.
          </p>
          <p>
            Dane zawodników z importowanych list startowych są przechowywane wyłącznie w pamięci Twojej
            przeglądarki — nie zapisujemy ich na serwerze. Pochodzą one z serwisu
            <a href="https://livetiming.pl" target="_blank" rel="noopener">livetiming.pl</a>.
          </p>
        </div>
        <p-button label="Rozumiem" (onClick)="accept()" />
      </aside>
    }
  `,
  styles: [`
    .cookie-banner {
      position: fixed;
      left: 1rem;
      right: 1rem;
      bottom: 1rem;
      z-index: 1000;
      max-width: 900px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      gap: 1.25rem;
      padding: 1rem 1.25rem;
      background: var(--swim-card);
      border: 1px solid var(--swim-border);
      border-left: 4px solid var(--swim-gold);
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, .5);
      color: var(--swim-text);
      font-size: .85rem;
      line-height: 1.45;
    }
    .cookie-banner__text { flex: 1; }
    .cookie-banner__text strong { color: var(--swim-gold); }
    .cookie-banner__text p { margin: .35rem 0 0; }
    .cookie-banner__text a { color: var(--swim-gold); }
    @media (max-width: 600px) {
      .cookie-banner { flex-direction: column; align-items: stretch; }
    }
  `]
})
export class CookieBannerComponent {
  visible = signal(!CookieBannerComponent.isAccepted());

  accept() {
    try { localStorage.setItem(CONSENT_KEY, '1'); } catch { /* storage unavailable — hide for this session only */ }
    this.visible.set(false);
  }

  private static isAccepted(): boolean {
    try { return localStorage.getItem(CONSENT_KEY) === '1'; } catch { return false; }
  }
}
