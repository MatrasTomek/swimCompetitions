# Wsparcie ND-Soft: stopka i podstrona `/wsparcie` — projekt

Data: 2026-09-30
Status: zaakceptowany w rozmowie, czeka na przegląd pisemnej wersji

## Cel

Odwiedzający aplikację może dowiedzieć się, kto ją tworzy, i dobrowolnie wesprzeć ND-Soft wpłatą BLIK. Wejście
to dyskretny link w stopce każdej strony, prowadzący do podstrony z opisem projektu i przyciskiem wpłaty.

Sukces: z dowolnej strony aplikacji da się w dwóch kliknięciach dojść do zewnętrznej strony wpłat obsługującej
BLIK, a zmiana serwisu wpłat wymaga zmiany jednej stałej.

## Decyzje podjęte w rozmowie

| Temat | Decyzja |
|-------|---------|
| Sposób płatności | Gotowy link do zewnętrznego serwisu wpłat. Aplikacja nie przetwarza płatności. |
| Serwis | Na start serwis typu „postaw kawę" (np. buycoffee.to, Suppi); docelowo możliwy link płatniczy operatora. Adres nie jest jeszcze znany. |
| Adres wpłat | Jedna stała w konfiguracji frontendu. Bez poprawnego adresu przycisk wpłaty się nie pokazuje. |
| Wejście | Nowa cienka stopka na każdej stronie (także konto i admin). Bez banera i bez pozycji w nagłówku. |
| Treść podstrony | Szkic na podstawie repo, miejsca nieznane oznaczone do uzupełnienia przez właściciela. |
| Adres podstrony | `/wsparcie` |
| Kontakt | `info@nd-soft.pl` |

Założenia: wpłata jest dobrowolna i niczego nie odblokowuje; wpłacać może każdy, bez logowania; odbiorcą jest
NDSOFT Sp. z o.o.

## Zakres

Wyłącznie frontend (`swim-frontend/`). Bez zmian w API, MongoDB i plikach JSON.

### 1. Konfiguracja adresu wpłat

- `src/environments/environment.ts` i `environment.prod.ts`: nowe pole `supportUrl: ''`.
- Nowy plik `src/app/shared/support-link.ts` z czystą funkcją `supportLink(raw: string): string | null`:
  - zwraca adres tylko wtedy, gdy po przycięciu białych znaków parsuje się przez `new URL()` i ma protokół
    `https:`;
  - w każdym innym przypadku (pusty tekst, `http:`, `javascript:`, `data:`, tekst niebędący adresem) zwraca
    `null`.
- Funkcja jest jedynym miejscem, przez które adres trafia do szablonu.

### 2. Stopka `src/app/shared/footer/footer.component.ts`

- Komponent standalone `app-footer`, renderowany raz w `src/app/app.ts` pod `<router-outlet />`.
- Treść: „Aplikację tworzy ND-Soft", link „Wesprzyj projekt" (`routerLink="/wsparcie"`), link „RODO"
  (`routerLink="/rodo"`).
- Link „Wesprzyj projekt" jest widoczny zawsze, niezależnie od `supportUrl`.
- Wygląd: jedna linia na desktopie, zawijanie na wąskim ekranie; kolory i typografia z istniejących zmiennych
  (`--swim-gold` itd.), bez nowych zależności.
- Ta sama stopka na stronach publicznych, konta i admina.

### 3. Podstrona `src/app/public/support/support.component.ts`

- Trasa `wsparcie` w `app.routes.ts`, ładowana leniwie jak pozostałe strony publiczne, bez guardów.
- Układ wzorowany na `public/rodo/`: `<app-header />`, `.swim-page`, tytuł, karta PrimeNG.
- Sekcje:
  1. Czym jest aplikacja: listy startowe, wyniki na żywo, konta klubów ze statystykami zawodników.
  2. Kto ją tworzy: NDSOFT Sp. z o.o.
  3. Listy startowe i wyniki zawodów są bezpłatne i bez logowania (konta klubów ze statystykami to płatna
     opcja); wpłata jest dobrowolna, nie jest opłatą za konto klubu i niczego nie odblokowuje.
  4. Na co idą środki — treść do uzupełnienia przez właściciela (w kodzie oznaczona komentarzem `TODO(treść)`
     i neutralnym zdaniem zastępczym, które nie zawiera twierdzeń o firmie).
  5. Przycisk „Wesprzyj przez BLIK": `<a>` z `href` z `supportLink()`, `target="_blank"`,
     `rel="noopener noreferrer"`; pod nim dopisek, że płatność obsługuje zewnętrzny serwis i odbywa się na
     jego stronie.
  6. Kontakt: `info@nd-soft.pl`.
- Gdy `supportLink()` zwraca `null`: zamiast przycisku zdanie „Wpłaty uruchomimy wkrótce" i kontakt mailowy.

### 4. Poprawka strony RODO

`public/rodo/rodo.component.ts` ma link powrotny „← Rejestracja". Po dodaniu linku w stopce strona będzie
otwierana także spoza rejestracji, więc link zmienia się na powrót do strony głównej (`routerLink="/"`).
Formularz rejestracji nadal otwiera RODO w nowej karcie, więc nie traci kontekstu.

### 5. Dokumentacja

`CLAUDE.md` (w repo): `supportUrl` w environments, `shared/support-link.ts`, stopka w `App`, trasa `/wsparcie`.

## Bezpieczeństwo

- Adres wpłat pochodzi z konfiguracji wbudowanej przy kompilacji, nie z danych użytkownika ani API.
- `supportLink()` dopuszcza wyłącznie `https:`, więc błędna konfiguracja nie da aktywnego linku.
- Link zewnętrzny otwiera się z `rel="noopener noreferrer"`.
- Aplikacja nie zbiera ani nie przesyła danych płatniczych.

## Znane ograniczenia

- Baner cookies (`position: fixed; bottom: 1rem`) do czasu zamknięcia może zasłaniać stopkę. Bez zmian.
- `.swim-page` ma `min-height: calc(100vh - 60px)`, więc stopka jest pod treścią, poniżej pierwszego ekranu.
- Aplikacja nie wie, czy i ile wpłacono.
- Zmiana adresu wpłat wymaga przebudowy i wdrożenia frontendu.

## Testy i weryfikacja

- `src/app/shared/support-link.spec.ts` (uruchamiany przez `npm test`): pusty tekst, same spacje, `http://`,
  `javascript:`, `data:`, tekst niebędący adresem, poprawny `https://` (także ze spacjami wokół).
- `npm run build` bez błędów.
- Przeglądarka, szerokość telefonu i desktopu:
  - stopka na stronie głównej, na `/konto` i na `/admin/zawody`;
  - `/wsparcie` z pustym `supportUrl` (komunikat zastępczy) i z przykładowym adresem `https://` (przycisk,
    nowa karta);
  - `/rodo` otwarte ze stopki, link powrotny prowadzi na stronę główną.

## Poza zakresem

Bramka płatnicza i kod BLIK na stronie, webhooki, zapis wpłat, baner na stronie głównej, pozycja w nagłówku,
regulamin płatności (zapewnia go serwis wpłat), pobieranie adresu wpłat z API.

## Do zrobienia przez właściciela (poza kodem)

- Wybrać serwis wpłat i założyć konto; sprawdzić aktualny cennik prowizji oraz to, czy serwis przyjmuje
  wpłaty na firmę.
- Potwierdzić z księgową sposób rozliczania wpłat jako przychodu spółki.
- Uzupełnić sekcję „Na co idą środki" i wpisać adres w `supportUrl`.
