---
name: ovh-databases
description: 'OVHcloud Web Cloud Databases documentation (PL). Use when setting up, configuring, or connecting a Web Cloud Databases instance for the OVH web hosting plan behind this app.'
user-invocable: true
---

# Pierwsze kroki z usługą Web Cloud Databases

> Źródło: https://docs.ovhcloud.com/pl/guides/web-cloud/databases/db-getting-started (ostatnia aktualizacja: 2026-06-08)

## Wprowadzenie

Rozwiązanie Web Cloud Databases udostępnia instancję baz danych z dedykowanymi i gwarantowanymi zasobami, zapewniając wydajność i elastyczność.
Domyślnie rozwiązanie Web Cloud Databases jest powiązane z siecią hostingów WWW OVHcloud. Możesz je również powiązać z dowolną inną siecią za pomocą listy autoryzowanych adresów IP.

**Dowiedz się, jak rozpocząć korzystanie z rozwiązania Web Cloud Databases.**

## Wymagania początkowe

- [Instancja Web Cloud Databases](https://www.ovhcloud.com/pl/web-cloud/databases/) (zawarta w ofercie [hostingu www](https://www.ovhcloud.com/pl/web-hosting/) Performance, Agency, Agency Plus lub Agency Max).

***

### Dostęp do Panelu klienta OVHcloud

- **Link bezpośredni:** Web Cloud Databases
- **Ścieżka nawigacji:** `Web Cloud` > `Web Cloud Databases` > Wybierz usługę bazy danych

***

## W praktyce

### Aktywacja serwera Web Cloud Databases zawartego w ofercie hostingu WWW

Jeśli Twoja oferta hostingu obejmuje opcję Web Cloud Databases, wykonaj kolejne **3** kroki.

**Krok 1**

Przejdź na stronę Hosting, następnie wybierz odpowiedni hosting WWW.

**Krok 2**

Na karcie `Informacje ogólne`, w sekcji `Konfiguracja`, kliknij przycisk `...` po prawej stronie **Web Cloud Databases**. Następnie kliknij `Włącz`, aby rozpocząć proces aktywacji.

**Krok 3**

Postępuj zgodnie z wyświetlonymi instrukcjami, aby określić typ i wersję serwera Web Cloud Databases. Będzie on następnie dostępny w lewej kolumnie, w sekcji `Web Cloud Databases`.

### Wyświetlanie informacji ogólnych instancji

**Krok 1**

Przejdź na stronę Web Cloud Databases, następnie wybierz odpowiednie rozwiązanie.

> Nazwa usługi Web Cloud Databases w Panelu klienta OVHcloud zawiera część identyfikatora klienta i kończy się trzema cyframi (001 dla pierwszej zainstalowanej usługi Web Cloud Databases, 002 dla drugiej itd.).

**Krok 2**

Upewnij się, że jesteś na karcie `Informacje ogólne`. Sprawdź, czy wyświetlane informacje są poprawne lub odpowiadają poniższym wskazówkom.

| Informacja     | Szczegóły                                                                                                                                                                                                                                                                                             |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Stan usługi    | Wskazuje, czy instancja jest uruchomiona, w trakcie restartu lub zawieszona. Instancja musi być uruchomiona, aby można było na niej wykonywać operacje.                                                                                                                                              |
| Typ            | Wskazuje system baz danych używany przez serwer.                                                                                                                                                                                                                                                     |
| Wersja         | Wskazuje wersję systemu baz danych używaną przez serwer. Upewnij się, że Twoja strona WWW jest kompatybilna z wybraną wersją.                                                                                                                                                                        |
| Nasycenie CPU  | Wskazuje czas CPU spędzony w nasyceniu. Instancja Web Cloud Databases nie jest ograniczona pod względem CPU, ale należy uważać, aby jej nie przeciążyć.                                                                                                                                              |
| RAM            | Wskazuje pamięć RAM dostępną dla instancji oraz ewentualne przekroczenia pamięci. Instancja Web Cloud Databases dysponuje dedykowanymi i gwarantowanymi zasobami: pamięcią RAM. W razie potrzeby można ją rozszerzyć i otrzymywać powiadomienia o wykorzystaniu wszystkich zasobów pamięci instancji. |
| Infrastruktura | Wskazuje infrastrukturę używaną przez instancję. Jest to informacja związana z infrastrukturą OVHcloud.                                                                                                                                                                                              |
| Centrum danych | Wskazuje centrum danych, w którym instancja została utworzona.                                                                                                                                                                                                                                       |
| Host           | Wskazuje serwer OVHcloud, na którym instancja została utworzona. Jest to informacja związana z infrastrukturą OVHcloud i może być używana w komunikatach dotyczących [awarii OVHcloud](https://www.status-ovhcloud.com/).                                                                            |

### Tworzenie bazy danych

> Ten krok nie dotyczy systemu baz danych Redis.

**Krok 1**

Przejdź na stronę Web Cloud Databases, następnie wybierz odpowiednie rozwiązanie.

**Krok 2**

Kliknij kartę `Bazy danych`.

**Krok 3**

Kliknij `Dodaj bazę danych`.

> Tworzenie schematów PostgreSQL jest obecnie niedostępne na serwerach Web Cloud Databases.

**Krok 4**

Wypełnij pola zgodnie z podanymi kryteriami. Możesz bezpośrednio utworzyć użytkownika, zaznaczając pole **"Utwórz użytkownika"**:

- **Nazwa bazy danych** (wymagane): to nazwa przyszłej bazy danych.
- **Nazwa użytkownika** (tylko jeśli pole `Utwórz użytkownika` jest zaznaczone): użytkownik, który będzie mógł się łączyć z bazą danych i wykonywać zapytania.
- **Uprawnienia** (tylko jeśli pole `Utwórz użytkownika` jest zaznaczone): uprawnienia przypisane do użytkownika w bazie danych. W przypadku standardowego użycia wybierz `Administrator`. Uprawnienia można zmienić w późniejszym czasie.
- **Hasło**/**Potwierdź hasło** (tylko jeśli pole `Utwórz użytkownika` jest zaznaczone): wybierz hasło, a następnie potwierdź je.

Kliknij `Zatwierdź`.

### Tworzenie użytkownika

> Ten krok nie dotyczy systemu baz danych Redis.

Jeśli użytkownik został utworzony jednocześnie z bazą danych w poprzednim kroku, ten krok jest opcjonalny. Projekt może jednak wymagać kilku użytkowników z różnymi uprawnieniami (na przykład odczyt/zapis dla jednego i tylko odczyt dla drugiego).

**Krok 1**

Przejdź na stronę Web Cloud Databases, następnie wybierz odpowiednie rozwiązanie.

**Krok 2**

Kliknij kartę `Użytkownicy i uprawnienia`.

**Krok 3**

Kliknij `Dodaj użytkownika`.

**Krok 4**

Wpisz "nazwę użytkownika" i "hasło", następnie kliknij `Zatwierdź`.

### Import bazy danych

> Ten krok dotyczy sytuacji, gdy chcesz zaimportować kopię zapasową istniejącej bazy danych.

Aby zaimportować bazę danych, skorzystaj z przewodnika OVHcloud "Przywracanie i importowanie bazy danych na serwer baz danych".

### Autoryzacja adresu IP

Aby instancja Web Cloud Databases działała, konieczne jest wskazanie adresów IP lub zakresów IP, które mogą łączyć się z bazami danych.

**Krok 1**

Przejdź na stronę Web Cloud Databases, następnie wybierz odpowiednie rozwiązanie.

**Krok 2**

Na wyświetlonej stronie kliknij kartę `Autoryzowane adresy IP`.

**Krok 3**

Kliknij przycisk `Dodaj adres IP / maskę` nad tabelą.

> Jeśli chcesz zmienić już autoryzowany adres IP lub zakres adresów IP, kliknij przycisk `...` po prawej stronie odpowiedniego wiersza w tabeli, a następnie `Edytuj whitelist`.

**Krok 4**

W oknie, które się otworzy, należy wypełnić kilka pól:

- `IP / maska *`: Wpisz adres IP (np. `203.0.113.44`) lub zakres adresów IP (np. `203.0.113.0/24` obejmujący wszystkie adresy IP od `203.0.113.0` do `203.0.113.255`), które chcesz autoryzować w rozwiązaniu Web Cloud Databases.
- `Opis` (opcjonalnie): Możesz dodać informacje o roli danego adresu IP lub zakresu adresów IP.
- `Bazy danych`: Zaznacz to pole, aby adres IP lub zakres adresów IP mógł uzyskać dostęp do baz danych Twojego rozwiązania Web Cloud Databases.
- `SFTP`: Zaznacz to pole, aby adres IP lub zakres adresów IP mógł uzyskać dostęp do logów Twojego rozwiązania Web Cloud Databases.

> **Uwaga:** Zdecydowanie odradza się zaznaczanie pola `Bazy danych` w celu autoryzacji zakresu adresów IP `0.0.0.0/0` do dostępu do baz danych — umożliwiłoby to dostęp do baz danych dla wszystkich istniejących adresów IPv4.

Po uzupełnieniu informacji kliknij przycisk `Zatwierdź`.

### Autoryzacja połączeń z hostingu WWW OVHcloud

Domyślnie rozwiązanie Web Cloud Databases jest automatycznie powiązane z hostingami WWW OVHcloud. Jeśli chcesz, możesz wyłączyć dostęp hostingów WWW OVHcloud do Web Cloud Databases (patrz przewodnik OVHcloud "Web Cloud Databases - Jak autoryzować adres IP?").

### Powiązanie strony WWW z bazą danych

Aby powiązać stronę WWW z bazą danych, potrzebne są następujące informacje:

| Informacja          | Opis                                                                                              |
| ------------------- | ------------------------------------------------------------------------------------------------- |
| Nazwa bazy danych   | Nazwa zdefiniowana podczas tworzenia bazy danych.                                                 |
| Nazwa użytkownika   | Nazwa użytkownika zdefiniowana podczas tworzenia bazy danych lub ewentualny dodatkowy użytkownik. |
| Hasło użytkownika   | Hasło powiązane z użytkownikiem, zdefiniowane w poprzednich krokach.                              |
| Nazwa hosta serwera | Serwer, który należy podać, aby strona WWW mogła połączyć się z bazą danych.                      |
| Port serwera        | Port połączenia z instancją Web Cloud Databases, aby strona WWW mogła połączyć się z bazą danych. |

Aby je znaleźć:

**Krok 1**

Przejdź na stronę Web Cloud Databases, następnie wybierz odpowiednie rozwiązanie.

**Krok 2**

Pobierz następujące informacje o połączeniu:

- **Serwer (nazwa hosta) i port:** widoczne na karcie `Informacje ogólne`, w sekcji `Informacje o połączeniu`.
- **Nazwa użytkownika:** widoczna na karcie `Użytkownicy i uprawnienia`.
- **Hasło:** hasło powiązane z użytkownikiem. Jeśli je zapomniałeś, przejdź do karty `Użytkownicy i uprawnienia`, kliknij `...` po prawej stronie odpowiedniego użytkownika, a następnie `Zmień hasło`.

> **Uwaga:** W przypadku zmiany hasła użytkownika bazy danych wszystkie aplikacje/strony WWW, które korzystają z tej bazy danych, muszą zostać odpowiednio zaktualizowane.

> **Uwaga:** Pole `port` może nie być dostępne w konfiguracji Twojej strony WWW. Należy dodać to pole po nazwie hosta serwera, oddzielając je znakiem `:`.
> Na przykład, dla nazwy hosta `aaXXXXX-XXX.eu.clouddb.ovh.net` z portem SQL `12345` należy wpisać `aaXXXXX-XXX.eu.clouddb.ovh.net:12345` w polu "Host" / "Nazwa hosta".

### Pobieranie logów serwera Web Cloud Databases

Aby uzyskać dostęp do logów rozwiązania Web Cloud Databases, skorzystaj z przewodnika OVHcloud "Web Cloud Databases - Jak pobrać logi?".

## Sprawdź również

- Tworzenie baz danych i użytkowników na serwerze baz danych
- Logowanie do bazy danych serwera baz danych
- Tworzenie kopii zapasowej i eksport bazy danych na serwerze baz danych
- Przywracanie i importowanie bazy danych na serwer baz danych
- Konfiguracja serwera baz danych

Wsparcie: https://www.ovhcloud.com/pl/support-levels/ · Community: https://community.ovhcloud.com/
