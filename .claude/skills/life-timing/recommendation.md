# Rekomendacja: integracja ze SPLASH Meet Manager 11 / SplashMe

Data rozeznania: 2026-07-10

## Wniosek główny

**SPLASH Meet Manager 11 nie udostępnia żadnego publicznego API** (REST, WebSocket, GraphQL).
To desktopowa aplikacja Windows z lokalną bazą danych. Jedyne "interfejsy" to pliki,
które sam publikuje na zewnątrz. Nie da się połączyć z działającą instancją MM zdalnie.

## Dostępne kanały danych

### 1. LENEX (`results.lxf`) — ustrukturyzowane źródło ✅ już zaimplementowane

- Oficjalny format wymiany danych: archiwum ZIP z plikiem XML (`.lef`) w środku (LENEX 3.0).
- Hostowany przez livetiming.pl w katalogu zawodów: `https://live.livetiming.pl/{operator}/{rok}/{MM_DD_miasto}/results.lxf`
- W projekcie obsługują go `includes/lenex_fetch.php` i `resolve_lenex_url()` w `includes/result_fetch.php`.
- **Ograniczenie:** podczas trwania zawodów plik bywa niepełny lub chwilowo niedostępny
  (serwer może zwrócić HTML zamiast ZIP); pełny jest po sesji/zawodach.

### 2. Statyczne pliki live (HTML/PDF) przez FTP — kanał livetiming.pl ✅ już używane (PDF)

- Meet Manager w trybie "live results" generuje dokumenty lokalnie i wgrywa je
  **zwykłym FTP-em** na serwer wskazany przez operatora pomiaru czasu.
- `index.html`, `StartList_{N}.pdf`, `ResultList_{N}.pdf` to statyczne pliki,
  odświeżane w tle po każdej zmianie w programie (zmiana wyników serii, rozstawienie itd.).
- Projekt konsumuje ten kanał parsując `ResultList_{N}.pdf` (`includes/result_fetch.php`,
  `includes/pdf_extract.php`).
- Portal `index.html` zawiera też strony HTML per konkurencja — potencjalnie łatwiejsze
  do parsowania niż PDF. **Do zweryfikowania** (nie potwierdzono struktury tych stron).

### 3. Kanał SplashMe / swimrankings — zamknięty ❌

- MM wysyła dane live na serwer SplashMe (swimrankings.net) używając globalnego ID
  zawodów (np. `E6-896`) i hasła organizatora.
- Aplikacja mobilna SplashMe (iOS/Android, dev: GeoLogix AG) czyta te dane
  z **niepublicznego, nieudokumentowanego API**.
- Brak oficjalnej dokumentacji i dostępu dla zewnętrznych deweloperów.
- Jedyna droga: kontakt mailowy **splashme@swimrankings.net** z pytaniem o dostęp do danych.
- Odtwarzanie API aplikacji na własną rękę = rozwiązanie nieoficjalne i kruche
  (może się zmienić bez ostrzeżenia); niezalecane.

### 4. Współpraca z operatorem pomiaru czasu — realnie najlepsza opcja na dane live ⭐

Skoro MM publikuje przez zwykły FTP, a upload można skierować na **dowolny serwer FTP**
(uploady na livetiming.pl i SplashMe działają niezależnie i można dodać kolejne cele),
najprostsza "bezpośrednia integracja" to umowa z operatorem obsługującym zawody
(firmy widoczne w URL-ach livetiming.pl, np. `zak`, `bachorz`, `czarnecki`), żeby:

- dodał nasz serwer FTP jako drugi cel uploadu live results w Meet Managerze, **albo**
- udostępniał `results.lxf` częściej / po każdej sesji.

Efekt: te same dane co livetiming.pl, w czasie rzeczywistym, bez scrapingu i bez PDF-ów.

## Rekomendacja dla projektu swimCompetitions

| Scenariusz | Podejście |
|-----------|-----------|
| Zawody zewnętrzne (brak wpływu na organizatora) | Zostać przy obecnym rozwiązaniu: LENEX + parsowanie PDF z livetiming.pl |
| Zawody "swoje" (np. Brzesko / Proszówki) | Dogadać z operatorem upload FTP na własny serwer — jedyne prawdziwie bezpośrednie połączenie z Meet Managerem |
| Chęć użycia danych SplashMe | Napisać na splashme@swimrankings.net; nie liczyć na publiczne API |

## Kontekst: czym jest SplashMe

- Mobilna aplikacja (iOS/Android) do śledzenia wyników zawodów pływackich na żywo;
  dev: GeoLogix AG, powiązana ze swimrankings.net.
- Funkcje: wyniki live, listy startowe, serie/tory, punktacje, śledzenie zawodników
  z powiadomieniami push, historia wyników z bazy swimrankings.
- Obsługuje **wyłącznie** zawody prowadzone w Splash Meet Manager, i tylko gdy organizator
  włączy upload (sama publikacja na livetiming.pl nie wystarcza — zawody pojawiają się
  w aplikacji dopiero po pierwszym uploadzie live).
- Model: podstawowe funkcje darmowe + opcjonalna subskrypcja.

## Źródła

- https://wiki.swimrankings.net/index.php/Meet_Manager:SplashMe
- https://wiki.swimrankings.net/images/4/45/Meet_Manager_FAQ.pdf
- https://www.swimrankings.net/files/MeetManager.pdf
- https://splash-software.ch/en/
- https://wiki.swimrankings.net/index.php/Meet_Manager:Export_and_Import_Formats
- https://splashme.app/en
