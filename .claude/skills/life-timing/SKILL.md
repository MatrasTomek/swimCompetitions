# livetiming.pl — Kompleksowa Dokumentacja

## Czym jest livetiming.pl

Livetiming.pl to polski portal obsługi live timingu zawodów pływackich. Platforma jest zbudowana na oprogramowaniu **SPLASH Meet Manager** i obsługuje zawody od poziomu okręgowego po mistrzostwa Polski i zawody międzynarodowe.

Dwa główne hosty:
- `https://livetiming.pl` — portal główny (lista zawodów, strony zawodów, aktualności)
- `https://live.livetiming.pl` — serwer plików z dokumentami zawodów (PDFy, LENEX, live timing)

---

## Struktura URL portalu

### Strona główna
```
https://livetiming.pl/
```

### Kategorie zawodów (paginacja)
```
https://livetiming.pl/contests/international/{strona}   # Zawody Międzynarodowe
https://livetiming.pl/contests/national/{strona}        # Zawody Centralne
https://livetiming.pl/contests/regional/{strona}        # Zawody Okręgowe
https://livetiming.pl/contests/calendar/{strona}        # Kalendarz Imprez
```

Każda strona wyświetla ~24 zawody. Strony numerowane od 1 (np. `/contests/regional/123`).

### Strona pojedynczych zawodów
```
https://livetiming.pl/contest/{UUID}
```

UUID jest w formacie v4 (36 znaków, np. `d5d47c81-93f8-4fe0-bc84-3cd4046f750f`).
Na tej stronie wyświetlane są linki do wszystkich dostępnych plików dla danej imprezy.

---

## Serwer plików: live.livetiming.pl

### Struktura katalogów
```
https://live.livetiming.pl/{organizator}/{rok}/{MM_DD_miasto}/
```

Przykłady:
```
https://live.livetiming.pl/bachorz/2024/12_22_bydgoszcz/
https://live.livetiming.pl/bujak/2025/05_01_lublin/
https://live.livetiming.pl/zak/2026/07_25_krakow/
```

- `{organizator}` — kod operatora obsługującego zawody (np. `bachorz`, `bujak`, `zak`)
- `{rok}` — rok zawodów (4 cyfry)
- `{MM_DD_miasto}` — data i lokalizacja (miesiąc_dzień_miasto, np. `12_22_bydgoszcz`)

**Uwaga:** Serwer `live.livetiming.pl` zwraca HTTP 403 przy próbie listowania katalogów — pliki dostępne są tylko przez bezpośredni URL.

### Dostępne pliki

| Plik | Opis |
|------|------|
| `komunikat.pdf` | Komunikat organizacyjny |
| `harmonogram.pdf` | Harmonogram zawodów |
| `zawodnicy.pdf` | Lista zawodników |
| `kluby.pdf` | Lista klubów |
| `startowa.pdf` | Pełna lista startowa |
| `wyniki.pdf` | Pełne wyniki zbiorcze |
| `medale.pdf` | Tabela medalowa |
| `mlodziezowcy.pdf` | Wyniki kategorii młodzieżowej |
| `najlepsi.pdf` | Najlepsze wyniki |
| `rekordy.pdf` | Rekordy pobite podczas zawodów |
| `pkt_klubowa.pdf` | Punktacja klubowa |
| `BestPerformance.pdf` | Najlepsze czasy |
| `ProgressionDetails.pdf` | Szczegóły kwalifikacji |
| `ProgressionSummary.pdf` | Podsumowanie kwalifikacji |
| `results.lxf` | **Plik wyników LENEX** (ZIP+XML, najważniejszy dla integracji) |
| `index.html` | Portal live timingu (SPLASH Meet Manager) |

### Pliki per-konkurencja

W katalogu zawodów dostępne są pliki dla każdej konkurencji:

```
StartList_{N}.pdf      # lista startowa eliminacji
StartList_{N}F.pdf     # lista startowa finałów
StartList_{N}X.pdf     # lista startowa repasażu
StartList_{N}A.pdf     # lista startowa (dłuższe dystanse, np. 800m/1500m)
ResultList_{N}.pdf     # wyniki eliminacji
ResultList_{N}F.pdf    # wyniki finałów
```

`{N}` = numer konkurencji (liczba całkowita, np. `1`, `2`, `36F`, `29X`).

---

## Portal live timingu (index.html)

Plik `index.html` hostowany na `live.livetiming.pl` to interaktywny portal SPLASH Meet Manager z:

- Nawigacją po dniach zawodów i konkurencjach
- Listami startowymi i wynikami w formacie HTML
- Linkami do PDF każdej konkurencji
- Pobieraniem pliku `results.lxf`
- Integracją z **aplikacją mobilną SplashMe** (iOS/Android) do powiadomień live
- Informacją o czasie ostatniej aktualizacji

Strona ta jest podlinkowana z `livetiming.pl/contest/{UUID}` jako główny interfejs śledzenia zawodów na żywo.

---

## Format LENEX (results.lxf)

### Czym jest
LENEX (ang. *League of Nations Exchange format*) to standardowy format wymiany danych dla zawodów pływackich, używany przez Splash Meet Manager, OMEGA, Colorado itp.

Plik `.lxf` to **archiwum ZIP** zawierające wewnątrz plik XML z rozszerzeniem `.lef`.

### Struktura XML (LENEX 3.0)

```xml
<LENEX>
  <MEETS>
    <MEET name="..." city="..." course="LCM|SCM">
      <SESSIONS>
        <SESSION>
          <EVENTS>
            <EVENT eventid="E1" number="1" .../>   <!-- eventid -> numer konkurencji -->
          </EVENTS>
        </SESSION>
      </SESSIONS>
      <CLUBS>
        <CLUB name="..." shortname="...">
          <ATHLETES>
            <ATHLETE athleteid="A1" lastname="KOWALSKI" firstname="JAN" birthdate="2005-01-01">
              <RESULTS>
                <RESULT eventid="E1" swimtime="1:02.34" points="512" status=""/>
                <!-- status puste = prawidlowy wynik; DNS/DNF/DSQ/WDR = brak czasu -->
              </RESULTS>
            </ATHLETE>
          </ATHLETES>
        </CLUB>
      </CLUBS>
    </MEET>
  </MEETS>
</LENEX>
```

### Kluczowe pola

| Pole | Opis |
|------|------|
| `EVENT.number` | Numer konkurencji (1, 2, 3...) |
| `EVENT.eventid` | Wewnętrzny ID (klucz do mapowania na numer) |
| `ATHLETE.athleteid` | ID zawodnika (klucz do RESULT) |
| `ATHLETE.lastname` | Nazwisko (zwykle CAPS) |
| `ATHLETE.firstname` | Imię |
| `ATHLETE.birthdate` | Data urodzenia `YYYY-MM-DD` |
| `RESULT.swimtime` | Czas w formacie `MM:SS.HH` (np. `0:27.34`, `1:02.45`) |
| `RESULT.points` | Punkty FINA (liczba całkowita) |
| `RESULT.status` | Puste = OK; `DNS`/`DNF`/`DSQ`/`WDR` = bez wyniku |

### LENEX 2.x (starszy format — fallback)

W starszych plikach wyniki mogą być pod `EVENT > RESULTS > RESULT` z atrybutem `swimmerid` zamiast pod `ATHLETE > RESULTS`.

### Format czasu

Normalizacja `swimtime`:
- `0:27.34` → `27.34` (wyścigi poniżej minuty)
- `1:02.45` → `1:02.45` (pozostałe bez zmian)

---

## Jak działa integracja w tym projekcie

### Przepływ danych

```
Admin wkleja URL zawodów:
  https://livetiming.pl/contest/{UUID}
         |
         v
resolve_lenex_url() pobiera HTML strony zawodów
i szuka linków *.lxf (preferuje te z "result" w nazwie)
         |
         v  (fallback gdy brak linka)
{contest_url}/results.lxf
         |
         v
lenex_download() pobiera ZIP, rozpakowuje .lef XML
         |
         v
lenex_parse_xml() buduje dwie mapy:
  athletes: {athleteid -> {lastname, firstname, birthdate}}
  results:  {event_nr -> {athleteid -> {czas, punkty}}}
         |
         v
lenex_find_athlete() szuka zawodnika po numerze konkurencji + imieniu
(dopasowanie case-insensitive, bez polskich znakow)
         |
         v
Wyniki zapisane do JSON zawodow + profil zawodnika w zawodnicy/
```

### Konfiguracja (live_config.json)

```json
{
  "contest_url": "https://livetiming.pl/contest/{UUID}",
  "json_file": "nazwa_pliku_bez_json",
  "nazwa": "Nazwa zawodow",
  "ostatnia_aktualizacja": "2025-06-15T14:30:00+00:00"
}
```

### Dopasowanie zawodnika

Funkcja `lenex_find_athlete(lenex, event_nr, athlete_name)`:
1. Filtruje wyniki dla danego numeru konkurencji
2. Normalizuje imię i nazwisko: małe litery + transliteracja polskich znaków (iconv `ASCII//TRANSLIT`)
3. Szuka dopasowania `NAZWISKO IMIE` z LENEX ↔ `imie` z listy startowej JSON

Przykład: `"WĄS AMELIA"` w LENEX = `"was amelia"` = `"Wąs Amelia"` z listy startowej.

### Kod PHP — kluczowe funkcje

| Funkcja | Plik | Opis |
|---------|------|------|
| `resolve_lenex_url(string $contest_url)` | `includes/result_fetch.php` | Pobiera stronę zawodów, ekstrahuje URL .lxf |
| `fetch_and_apply_lenex(...)` | `includes/result_fetch.php` | Główna funkcja — pobiera i aplikuje wyniki do JSON |
| `lenex_download(string $lxf_url)` | `includes/lenex_fetch.php` | Pobiera ZIP, rozpakowuje XML |
| `lenex_parse_xml(string $xml)` | `includes/lenex_fetch.php` | Parsuje LENEX -> mapy athletes/results |
| `lenex_find_athlete(array $lenex, int $nr, string $name)` | `includes/lenex_fetch.php` | Szuka wyniku zawodnika |
| `lenex_normalize_name(string $name)` | `includes/lenex_fetch.php` | ASCII lowercase dla porównania nazw |
| `lenex_normalize_time(string $t)` | `includes/lenex_fetch.php` | Normalizuje `0:SS.HH` -> `SS.HH` |

---

## Jak wyszukać zawody

### Przez przeglądarkę
1. Wejdź na `https://livetiming.pl/`
2. Wybierz kategorię: Zawody Międzynarodowe / Centralne / Okręgowe / Kalendarz Imprez
3. Strony paginowane — okręgowe ~123 stron, centralne ~28 stron
4. Kliknij nazwę zawodów → strona `/contest/{UUID}`

### Przez URL (bezpośrednio)
Jeśli znasz UUID:
```
https://livetiming.pl/contest/d5d47c81-93f8-4fe0-bc84-3cd4046f750f
```

### Brak publicznego API
Livetiming.pl **nie udostępnia publicznego API REST ani GraphQL**. Jedyny sposób pobrania danych:
- Parsowanie HTML strony `/contest/{UUID}` (scraping — stosowane w projekcie)
- Bezpośrednie pobieranie `results.lxf` gdy znamy URL katalogowy

---

## Przykładowe pełne adresy URL (produkcyjne)

### Mistrzostwa Polski w Pływaniu Lublin 2025
```
Strona zawodow:         https://livetiming.pl/contest/d5d47c81-93f8-4fe0-bc84-3cd4046f750f
Live timing (portal):   https://live.livetiming.pl/bujak/2025/05_01_lublin/index.html
LENEX (wyniki):         https://live.livetiming.pl/bujak/2025/05_01_lublin/results.lxf
Lista startowa:         https://live.livetiming.pl/bujak/2025/05_01_lublin/startowa.pdf
Wyniki zbiorcze:        https://live.livetiming.pl/bujak/2025/05_01_lublin/wyniki.pdf
Konk. 1 lista start.:   https://live.livetiming.pl/bujak/2025/05_01_lublin/StartList_1.pdf
Konk. 1 wyniki:         https://live.livetiming.pl/bujak/2025/05_01_lublin/ResultList_1.pdf
Konk. 1 final wyniki:   https://live.livetiming.pl/bujak/2025/05_01_lublin/ResultList_1F.pdf
```

### Zimowe Mistrzostwa Polski Bydgoszcz 2024
```
Strona zawodow:   https://livetiming.pl/contest/3b59fd97-2780-4bc0-8399-5894fc0fe9b1
Live timing:      https://live.livetiming.pl/bachorz/2024/12_22_bydgoszcz/index.html
LENEX (wyniki):   https://live.livetiming.pl/bachorz/2024/12_22_bydgoszcz/results.lxf
```

---

## Ograniczenia i uwagi

- `live.livetiming.pl` jest zabezpieczony przed listowaniem katalogów (HTTP 403 na `/`)
- Wyniki LENEX stają się dostępne po zakończeniu zawodów (lub poszczególnych sesji)
- Podczas trwania zawodów `results.lxf` może być niepełny lub niedostępny — portal zwróci HTML zamiast pliku ZIP
- URL katalogowy (`{organizer}/{rok}/{MM_DD_miasto}/`) jest ustalany przez operatora i **nie wynika automatycznie z UUID** — jedyny sposób jego uzyskania to pobranie strony `/contest/{UUID}` i wyciągnięcie linków
- Zawodnicy mogą mieć różne wersje imion/nazwisk w LENEX vs. listach startowych — stąd normalizacja znaków diakrytycznych
- `EVENT.number` (numer w programie) != `EVENT.eventid` (wewnętrzny klucz XML)

---

## Oprogramowanie powiązane

| Oprogramowanie | Opis |
|----------------|------|
| **SPLASH Meet Manager** | Zarządzanie zawodami pływackimi — generuje LENEX, listy startowe, wyniki |
| **LiveTiming 7** | Wizualizacja wyników na tablicach na żywo (Windows, obsługuje Colorado, Alge, Seiko, Ares, Quantum) |
| **SplashMe** | Mobilna aplikacja iOS/Android — powiadomienia live z zawodów |
| **mBim** | Oprogramowanie do zarządzania danymi pływackimi |

---

## Powiązane zasoby zewnętrzne

- [Polski Związek Pływacki](http://www.polswim.pl)
- [System Ewidencji i Licencji](https://l2.polswim.pl/user) — rejestracja zawodników PZP
- [Rekordy Polski](http://porabik.pl/komitet/)
- [Ranking europejski](http://www.swimrankings.net/) — swimrankings.net

---

## Dokumenty dodatkowe

- `recommendation.md` — rozeznanie i rekomendacja integracji ze SPLASH Meet Manager 11 / SplashMe (dostępne kanały danych, brak publicznego API, opcje na dane live)
