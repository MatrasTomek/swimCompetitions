# Wsparcie ND-Soft (stopka + `/wsparcie`) — plan wdrożenia

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stopka na każdej stronie z linkiem do podstrony `/wsparcie`, która opisuje projekt i prowadzi do zewnętrznego serwisu wpłat BLIK.

**Architecture:** Wyłącznie frontend. Adres wpłat to stała `supportUrl` w `src/environments/`, przepuszczana przez czystą funkcję `supportLink()` (tylko `https:`). Stopka jest renderowana raz w komponencie głównym `App`; podstrona jest leniwie ładowanym komponentem standalone wzorowanym na `public/rodo/`.

**Tech Stack:** Angular (komponenty standalone, szablony inline, control flow `@if`), PrimeNG `Card`, testy `node --test` nad `src/**/*.spec.ts`.

**Spec:** `docs/superpowers/specs/2026-09-30-wsparcie-ndsoft-design.md`

## Global Constraints

- Bez zmian w API, MongoDB i plikach JSON.
- Bez nowych zależności npm.
- **Bez commitów.** Właściciel repo commituje sam po przeglądzie; żaden krok nie wykonuje `git add` ani `git commit`. Praca na `main`.
- Wszystkie polecenia uruchamiać z katalogu `swim-frontend/`.
- Teksty widoczne dla użytkownika po polsku; komentarze w kodzie po angielsku (jak w reszcie `swim-frontend/src`).
- Adres podstrony: `/wsparcie`. Kontakt: `info@nd-soft.pl`. Nazwa firmy w treści: `NDSOFT Sp. z o.o.`; w stopce: `ND-Soft`.
- Adres wpłat trafia do szablonu wyłącznie przez `supportLink()`; dopuszczony protokół to tylko `https:`.
- Link zewnętrzny: `target="_blank"` i `rel="noopener noreferrer"`.
- Kolory tylko z istniejących zmiennych CSS (`--swim-gold`, `--swim-dark`, `--swim-card`, `--swim-border`, `--swim-muted`).
- W plikach `*.spec.ts` importy lokalne mają rozszerzenie `.ts` (wymóg `node --experimental-strip-types`), a testowany plik nie może importować Angulara.

## Review Focus

1. **`supportUrl` z białymi znakami lub wielkimi literami** (` HTTPS://Buycoffee.to/ndsoft `) — przycisk ma działać, adres ma być znormalizowany. Test w Zadaniu 1.
2. **`supportUrl` bez hosta lub bez protokołu** (`https://`, `//buycoffee.to/x`, `buycoffee.to/x`) — brak przycisku, komunikat zastępczy. Test w Zadaniu 1.
3. **Wąski ekran (360 px)** — stopka zawija się i nie powoduje poziomego przewijania strony. Sprawdzenie w Zadaniu 2.
4. **Drukowanie listy startowej** — stopka nie pojawia się na wydruku (`@media print`). Sprawdzenie w Zadaniu 2.
5. **Pusty `supportUrl` na produkcji** (stan startowy) — `/wsparcie` pokazuje „Wpłaty uruchomimy wkrótce", bez martwego przycisku. Sprawdzenie w Zadaniu 3.

## Struktura plików

| Plik | Akcja | Odpowiedzialność |
|------|-------|------------------|
| `swim-frontend/src/app/shared/support-link.ts` | nowy | Walidacja adresu wpłat |
| `swim-frontend/src/app/shared/support-link.spec.ts` | nowy | Testy walidacji |
| `swim-frontend/src/environments/environment.ts` | zmiana | Pole `supportUrl` |
| `swim-frontend/src/environments/environment.prod.ts` | zmiana | Pole `supportUrl` |
| `swim-frontend/src/app/shared/footer/footer.component.ts` | nowy | Stopka |
| `swim-frontend/src/app/app.ts` | zmiana | Osadzenie stopki |
| `swim-frontend/src/app/public/support/support.component.ts` | nowy | Podstrona `/wsparcie` |
| `swim-frontend/src/app/app.routes.ts` | zmiana | Trasa `wsparcie` |
| `swim-frontend/src/app/public/rodo/rodo.component.ts` | zmiana | Link powrotny |
| `CLAUDE.md` | zmiana | Dokumentacja |

---

### Task 1: Walidacja adresu wpłat i konfiguracja

**Files:**
- Create: `swim-frontend/src/app/shared/support-link.ts`
- Test: `swim-frontend/src/app/shared/support-link.spec.ts`
- Modify: `swim-frontend/src/environments/environment.ts`
- Modify: `swim-frontend/src/environments/environment.prod.ts`

**Interfaces:**
- Produces: `supportLink(raw: string): string | null` (eksport z `shared/support-link.ts`); `environment.supportUrl: string`.

- [ ] **Step 1: Napisz test, który nie przechodzi**

`swim-frontend/src/app/shared/support-link.spec.ts`:

```ts
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { supportLink } from './support-link.ts';

test('supportLink: valid https address', () => {
  assert.equal(supportLink('https://buycoffee.to/ndsoft'), 'https://buycoffee.to/ndsoft');
});

test('supportLink: trims and normalizes', () => {
  assert.equal(supportLink('  https://buycoffee.to/ndsoft \n'), 'https://buycoffee.to/ndsoft');
  assert.equal(supportLink('HTTPS://Buycoffee.to/ndsoft'), 'https://buycoffee.to/ndsoft');
});

test('supportLink: empty → null', () => {
  assert.equal(supportLink(''), null);
  assert.equal(supportLink('   '), null);
});

test('supportLink: other protocols → null', () => {
  assert.equal(supportLink('http://buycoffee.to/ndsoft'), null);
  assert.equal(supportLink('javascript:alert(1)'), null);
  assert.equal(supportLink('data:text/html,<b>x</b>'), null);
  assert.equal(supportLink('mailto:info@nd-soft.pl'), null);
});

test('supportLink: not an absolute address → null', () => {
  assert.equal(supportLink('buycoffee.to/ndsoft'), null);
  assert.equal(supportLink('//buycoffee.to/ndsoft'), null);
  assert.equal(supportLink('https://'), null);
  assert.equal(supportLink('wpłaty wkrótce'), null);
});
```

- [ ] **Step 2: Uruchom test i potwierdź, że nie przechodzi**

Run: `npm test`
Expected: FAIL — błąd importu, bo `support-link.ts` nie istnieje (`ERR_MODULE_NOT_FOUND`). Pozostałe pliki spec przechodzą.

- [ ] **Step 3: Napisz implementację**

`swim-frontend/src/app/shared/support-link.ts`:

```ts
/**
 * The donation page address from the build configuration, or null when it is not a usable
 * absolute `https:` address — the support page then shows no payment button.
 */
export function supportLink(raw: string): string | null {
  const value = raw.trim();
  if (!value) return null;
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    return null;
  }
  return url.protocol === 'https:' ? url.href : null;
}
```

- [ ] **Step 4: Uruchom test i potwierdź, że przechodzi**

Run: `npm test`
Expected: PASS — wszystkie testy, w tym pięć nowych `supportLink: …`.

- [ ] **Step 5: Dodaj pole konfiguracji**

`swim-frontend/src/environments/environment.ts` — cały plik:

```ts
export const environment = {
  production: false,
  apiUrl: '/api/v1/index.php',
  // Donation page (https only) behind the "Wesprzyj przez BLIK" button; empty = no button yet
  supportUrl: '',
};
```

`swim-frontend/src/environments/environment.prod.ts` — cały plik:

```ts
export const environment = {
  production: true,
  apiUrl: '/api/v1/index.php',
  // Donation page (https only) behind the "Wesprzyj przez BLIK" button; empty = no button yet
  supportUrl: '',
};
```

- [ ] **Step 6: Sprawdź typy**

Run: `npx tsc --noEmit -p tsconfig.app.json`
Expected: brak błędów.

---

### Task 2: Stopka na każdej stronie

**Files:**
- Create: `swim-frontend/src/app/shared/footer/footer.component.ts`
- Modify: `swim-frontend/src/app/app.ts`

**Interfaces:**
- Produces: `FooterComponent` (selektor `app-footer`), linki do `/wsparcie` i `/rodo`.
- Trasa `/wsparcie` powstaje w Zadaniu 3; do tego czasu link trafia w trasę `**` i przekierowuje na stronę główną.

- [ ] **Step 1: Utwórz komponent stopki**

`swim-frontend/src/app/shared/footer/footer.component.ts`:

```ts
import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

/** Site-wide footer: who builds the app, the support page and the GDPR clause. */
@Component({
  selector: 'app-footer',
  imports: [RouterLink],
  template: `
    <footer class="swim-footer">
      <span>Aplikację tworzy ND-Soft</span>
      <a routerLink="/wsparcie">Wesprzyj projekt</a>
      <a routerLink="/rodo">RODO</a>
    </footer>
  `,
  styles: [`
    .swim-footer {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: .35rem 1.25rem;
      padding: .9rem 1rem;
      border-top: 1px solid var(--swim-border);
      color: var(--swim-muted);
      font-size: .8rem;
    }
    .swim-footer a { color: var(--swim-gold); text-decoration: none; }
    .swim-footer a:hover { text-decoration: underline; }

    @media print {
      .swim-footer { display: none; }
    }
  `]
})
export class FooterComponent {}
```

- [ ] **Step 2: Osadź stopkę w komponencie głównym**

`swim-frontend/src/app/app.ts` — cały plik:

```ts
import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { CookieBannerComponent } from './shared/cookie-banner/cookie-banner.component';
import { ConfirmDeleteComponent } from './shared/confirm-delete/confirm-delete.component';
import { FooterComponent } from './shared/footer/footer.component';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, CookieBannerComponent, ConfirmDeleteComponent, FooterComponent],
  template: '<router-outlet /><app-footer /><app-cookie-banner /><app-confirm-delete />',
})
export class App {}
```

- [ ] **Step 3: Zbuduj**

Run: `npm run build`
Expected: build kończy się bez błędów.

- [ ] **Step 4: Sprawdź w przeglądarce**

Run: `npm start` i otwórz `http://localhost:4200`. Uwaga: `npm start` proxuje API do `API_TARGET` z `.env`, które może wskazywać produkcję — tu tylko oglądamy strony, niczego nie zapisujemy.

Expected:
- Strona główna: po przewinięciu na dół widać jedną linię „Aplikację tworzy ND-Soft · Wesprzyj projekt · RODO" (bez kropek rozdzielających, odstępy z `gap`), oddzieloną cienką linią.
- Szerokość 360 px (DevTools): elementy zawijają się do dwóch linii, strona nie przewija się w poziomie.
- `/logowanie` oraz, po zalogowaniu, `/konto` i `/admin/zawody`: ta sama stopka.
- Podgląd wydruku (Ctrl+P) na stronie listy startowej: stopki nie ma.
- Klik „RODO" otwiera `/rodo`.

---

### Task 3: Podstrona `/wsparcie`

**Files:**
- Create: `swim-frontend/src/app/public/support/support.component.ts`
- Modify: `swim-frontend/src/app/app.routes.ts` (po trasie `rodo`)

**Interfaces:**
- Consumes: `supportLink(raw: string): string | null` z `shared/support-link.ts`; `environment.supportUrl: string` z `environments/environment.ts`.
- Produces: `SupportComponent`, trasa `wsparcie`.

- [ ] **Step 1: Utwórz komponent podstrony**

`swim-frontend/src/app/public/support/support.component.ts`:

```ts
import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Card } from 'primeng/card';
import { HeaderComponent } from '../../shared/header/header.component';
import { supportLink } from '../../shared/support-link';
import { environment } from '../../../environments/environment';

/** About the project and its authors, with a link to the external donation page (BLIK). */
@Component({
  selector: 'app-support',
  imports: [RouterLink, Card, HeaderComponent],
  template: `
    <app-header />
    <div class="swim-page">
      <a routerLink="/" class="back">← Strona główna</a>
      <h1 class="swim-page-title">Wesprzyj projekt</h1>

      <p-card styleClass="support-card">
        <div class="support">
          <h2>O aplikacji</h2>
          <p>
            Ta aplikacja pomaga śledzić zawody pływackie: pokazuje listy startowe, wyniki na żywo,
            a klubom pozwala prowadzić konta ze statystykami zawodników.
          </p>

          <h2>Kto ją tworzy</h2>
          <p>
            Aplikację tworzy i utrzymuje <strong>NDSOFT Sp. z o.o.</strong>
          </p>

          <h2>Wsparcie jest dobrowolne</h2>
          <p>
            Korzystanie z aplikacji jest bezpłatne. Wpłata jest dobrowolna i nie odblokowuje żadnych
            dodatkowych funkcji.
          </p>

          <!-- TODO(treść): właściciel uzupełnia, na co konkretnie idą środki -->
          <h2>Na co idą środki</h2>
          <p>Wpłaty pomagają nam utrzymywać i rozwijać aplikację.</p>

          <h2>Jak wesprzeć</h2>
          @if (link; as url) {
            <p>
              <a class="support-button" [href]="url" target="_blank" rel="noopener noreferrer">Wesprzyj przez BLIK</a>
            </p>
            <p class="support-note">
              Płatność obsługuje zewnętrzny serwis i odbywa się na jego stronie, która otworzy się
              w nowej karcie.
            </p>
          } @else {
            <p>Wpłaty uruchomimy wkrótce.</p>
          }

          <h2>Kontakt</h2>
          <p>
            Pytania i uwagi: <a href="mailto:info@nd-soft.pl">info&#64;nd-soft.pl</a>
          </p>
        </div>
      </p-card>
    </div>
  `,
  styles: [`
    .back            { display: inline-block; margin-bottom: 1rem; color: var(--swim-muted); text-decoration: none; font-size: .85rem; }
    .support-card    { max-width: 800px; background: var(--swim-card) !important; }
    .support         { font-size: .88rem; line-height: 1.6; color: var(--swim-muted); }
    .support h2      { color: var(--swim-gold); font-size: 1rem; margin: 1.5rem 0 .5rem; }
    .support h2:first-child { margin-top: 0; }
    .support p       { margin: 0 0 .75rem; }
    .support strong  { color: #e8e8e8; }
    .support a       { color: var(--swim-gold); }
    .support a.support-button {
      display: inline-block;
      padding: .6rem 1.25rem;
      border-radius: 6px;
      background: var(--swim-gold);
      color: #111;
      font-weight: 600;
      text-decoration: none;
    }
    .support a.support-button:hover { background: #e6c200; }
    .support-note    { font-size: .8rem; }
  `]
})
export class SupportComponent {
  readonly link = supportLink(environment.supportUrl);
}
```

Uwagi dla wykonawcy:
- Komentarz `TODO(treść)` jest wymagany przez spec — to znacznik dla właściciela, nie brak w planie. Zdanie pod nagłówkiem zostaje dokładnie takie jak wyżej.
- `info&#64;nd-soft.pl` to zapis znaku `@` w szablonie Angulara (tak samo jak w `rodo.component.ts`).

- [ ] **Step 2: Dodaj trasę**

W `swim-frontend/src/app/app.routes.ts`, bezpośrednio po bloku trasy `rodo`:

```ts
  {
    path: 'wsparcie',
    loadComponent: () => import('./public/support/support.component').then(m => m.SupportComponent),
  },
```

- [ ] **Step 3: Zbuduj**

Run: `npm run build`
Expected: build bez błędów; na liście leniwych chunków przybywa jeden (podstrona wsparcia).

- [ ] **Step 4: Sprawdź w przeglądarce stan bez adresu wpłat**

Run: `npm start`, otwórz `http://localhost:4200/wsparcie`.

Expected: nagłówek, link „← Strona główna", tytuł „Wesprzyj projekt", karta z sześcioma sekcjami; w sekcji „Jak wesprzeć" zdanie „Wpłaty uruchomimy wkrótce." i żadnego przycisku. Link „Wesprzyj projekt" w stopce prowadzi na tę stronę.

- [ ] **Step 5: Sprawdź w przeglądarce stan z adresem wpłat**

Tymczasowo ustaw w `src/environments/environment.ts`: `supportUrl: 'https://example.com/wplata'` i odśwież `/wsparcie`.

Expected: złoty przycisk „Wesprzyj przez BLIK" i dopisek o zewnętrznym serwisie; klik otwiera `https://example.com/wplata` w nowej karcie. Na szerokości 360 px przycisk i tekst mieszczą się w karcie.

Następnie tymczasowo ustaw `supportUrl: 'http://example.com/wplata'` i odśwież.

Expected: znowu „Wpłaty uruchomimy wkrótce.", bez przycisku.

- [ ] **Step 6: Przywróć konfigurację**

Ustaw z powrotem `supportUrl: ''` w `src/environments/environment.ts`.

Run: `git diff --stat -- src/environments/`
Expected: w obu plikach environments jedyną zmianą względem repo są dwie dodane linie z Zadania 1 (komentarz i `supportUrl: ''`).

---

### Task 4: Link powrotny RODO, dokumentacja i weryfikacja końcowa

**Files:**
- Modify: `swim-frontend/src/app/public/rodo/rodo.component.ts:13`
- Modify: `CLAUDE.md` (sekcja „Frontend — Angular SPA")

**Interfaces:**
- Consumes: stopka z Zadania 2 (link do `/rodo`), podstrona z Zadania 3.

- [ ] **Step 1: Zmień link powrotny na stronie RODO**

W `swim-frontend/src/app/public/rodo/rodo.component.ts` zastąp linię:

```html
      <a routerLink="/rejestracja" class="back">← Rejestracja</a>
```

linią:

```html
      <a routerLink="/" class="back">← Strona główna</a>
```

Komentarz JSDoc nad komponentem zmień z:

```ts
/** GDPR (RODO) information clause for the registration form (art. 13 RODO). */
```

na:

```ts
/** GDPR (RODO) information clause (art. 13 RODO) — linked from the registration form and the footer. */
```

- [ ] **Step 2: Uzupełnij `CLAUDE.md`**

W sekcji „### Frontend — Angular SPA (`swim-frontend/`)", po punkcie o `src/app/public/register/`, dodaj punkt:

```markdown
- `src/app/public/support/` — `/wsparcie`: about the project and ND-Soft, with a "Wesprzyj przez BLIK" button to an external donation page (the app processes no payments and does not know about donations). The address is `supportUrl` in `src/environments/environment*.ts` (empty by default → "Wpłaty uruchomimy wkrótce" instead of the button) and reaches the template only through `supportLink()` in `shared/support-link.ts`, which accepts absolute `https:` addresses only. `<app-footer>` (`src/app/shared/footer/`, rendered once in `app.ts`, hidden in print) links to `/wsparcie` and `/rodo` from every page
```

W tym samym pliku, w punkcie o `src/app/public/register/`, zamień fragment:

```
(`public/rodo/`, opens in a new tab)
```

na:

```
(`public/rodo/`, opens in a new tab; also linked from the footer)
```

- [ ] **Step 3: Uruchom testy i build**

Run: `npm test`
Expected: PASS, wszystkie testy.

Run: `npm run build`
Expected: build bez błędów i bez nowych ostrzeżeń o budżetach.

- [ ] **Step 4: Przejście końcowe w przeglądarce**

Run: `npm start`.

Expected:
- `/` → stopka → „RODO" → strona RODO z linkiem „← Strona główna", który wraca na `/`.
- `/rejestracja` → link do informacji RODO nadal otwiera `/rodo` w nowej karcie.
- `/` → stopka → „Wesprzyj projekt" → `/wsparcie` z komunikatem „Wpłaty uruchomimy wkrótce.".
- Konsola przeglądarki bez błędów na `/`, `/wsparcie`, `/rodo`.

- [ ] **Step 5: Podsumuj zmiany dla właściciela**

Run: `git status --short`
Expected: zmienione `CLAUDE.md`, `swim-frontend/src/app/app.ts`, `app.routes.ts`, `public/rodo/rodo.component.ts`, oba pliki `environments/`; nowe `shared/support-link.ts`, `shared/support-link.spec.ts`, `shared/footer/`, `public/support/`, `docs/superpowers/`. Żadnych commitów. (W `git status` będą też wcześniejsze, niezwiązane zmiany właściciela w plikach PHP — nie ruszać.)

Przekaż właścicielowi listę rzeczy po jego stronie ze speca: wybór serwisu wpłat i konto, cennik prowizji i wpłaty na firmę, rozliczenie z księgową, treść sekcji „Na co idą środki", wpisanie adresu w `supportUrl` w `environment.prod.ts`.
