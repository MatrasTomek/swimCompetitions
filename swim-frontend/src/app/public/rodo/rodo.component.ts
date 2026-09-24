import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Card } from 'primeng/card';
import { HeaderComponent } from '../../shared/header/header.component';

/** GDPR (RODO) information clause for the registration form (art. 13 RODO). */
@Component({
  selector: 'app-rodo',
  imports: [RouterLink, Card, HeaderComponent],
  template: `
    <app-header />
    <div class="swim-page">
      <a routerLink="/rejestracja" class="back">← Rejestracja</a>
      <h1 class="swim-page-title">Informacja o przetwarzaniu danych osobowych</h1>

      <p-card styleClass="rodo-card">
        <div class="rodo">
          <p>
            Zgodnie z art. 13 ust. 1 i 2 Rozporządzenia Parlamentu Europejskiego i Rady (UE) 2016/679 z dnia
            27 kwietnia 2016 r. w sprawie ochrony osób fizycznych w związku z przetwarzaniem danych osobowych
            i w sprawie swobodnego przepływu takich danych oraz uchylenia dyrektywy 95/46/WE (RODO) informujemy, że:
          </p>

          <h2>1. Administrator danych</h2>
          <p>
            Administratorem Twoich danych osobowych jest <strong>NDSOFT Sp. z o.o.</strong> z siedzibą
            w Mokrzyskach, Trakt Królewski 118, 32-800 Mokrzyska.
          </p>

          <h2>2. Kontakt</h2>
          <p>
            We wszystkich sprawach dotyczących przetwarzania danych osobowych oraz korzystania z przysługujących
            Ci praw możesz skontaktować się z administratorem pod adresem e-mail
            <a href="mailto:info@nd-soft.pl">info&#64;nd-soft.pl</a> lub pisemnie na adres siedziby.
          </p>

          <h2>3. Cele i podstawy prawne przetwarzania</h2>
          <p>Twoje dane osobowe przetwarzamy w celu:</p>
          <ul>
            <li>
              obsługi zgłoszenia przesłanego formularzem rejestracji i kontaktu z Tobą w sprawie założenia konta
              — na podstawie Twojej zgody (art. 6 ust. 1 lit. a RODO) oraz w celu podjęcia działań na Twoje
              żądanie przed zawarciem umowy (art. 6 ust. 1 lit. b RODO);
            </li>
            <li>
              zapewnienia bezpieczeństwa serwisu i ochrony przed nadużyciami (np. spamem), w tym przez czasowe
              zapisanie adresu IP oraz dołączenie go do wiadomości e-mail ze zgłoszeniem — na podstawie prawnie uzasadnionego interesu administratora
              (art. 6 ust. 1 lit. f RODO);
            </li>
            <li>
              ewentualnego ustalenia, dochodzenia lub obrony roszczeń — na podstawie prawnie uzasadnionego
              interesu administratora (art. 6 ust. 1 lit. f RODO).
            </li>
          </ul>

          <h2>4. Zakres danych</h2>
          <p>
            Przetwarzamy dane podane w formularzu: imię i nazwisko, adres e-mail, numer telefonu, nazwę klubu,
            dane zawodników wskazanych w zgłoszeniu i treść wiadomości, a także adres IP, z którego wysłano
            formularz.
          </p>

          <h2>5. Odbiorcy danych</h2>
          <p>
            Dane mogą być przekazywane wyłącznie podmiotom przetwarzającym je na zlecenie administratora
            i świadczącym na jego rzecz usługi hostingu oraz poczty elektronicznej (OVH sp. z o.o.), na podstawie
            umów powierzenia przetwarzania danych, a także organom uprawnionym na podstawie przepisów prawa.
          </p>

          <h2>6. Przekazywanie danych poza EOG</h2>
          <p>
            Twoje dane nie są przekazywane do państw spoza Europejskiego Obszaru Gospodarczego ani do organizacji
            międzynarodowych.
          </p>

          <h2>7. Okres przechowywania danych</h2>
          <p>
            Dane przechowujemy przez czas niezbędny do obsługi zgłoszenia, nie dłużej niż 12 miesięcy od
            ostatniego kontaktu — chyba że zawrzemy umowę; wówczas przez okres jej obowiązywania oraz do upływu
            terminów przedawnienia roszczeń. Dane przetwarzane na podstawie zgody przechowujemy do czasu jej
            wycofania. Adres IP zapisany w celu ochrony przed nadużyciami usuwamy automatycznie po 1 godzinie;
            adres IP dołączony do wiadomości e-mail ze zgłoszeniem przechowujemy razem z tym zgłoszeniem, przez
            okres wskazany powyżej.
          </p>

          <h2>8. Twoje prawa</h2>
          <p>Przysługuje Ci prawo do:</p>
          <ul>
            <li>dostępu do swoich danych oraz otrzymania ich kopii;</li>
            <li>sprostowania (poprawiania) danych;</li>
            <li>usunięcia danych;</li>
            <li>ograniczenia przetwarzania danych;</li>
            <li>przenoszenia danych;</li>
            <li>wniesienia sprzeciwu wobec przetwarzania danych na podstawie prawnie uzasadnionego interesu;</li>
            <li>
              wycofania zgody w dowolnym momencie — bez wpływu na zgodność z prawem przetwarzania, którego
              dokonano na podstawie zgody przed jej wycofaniem.
            </li>
          </ul>
          <p>Aby skorzystać z tych praw, napisz na <a href="mailto:info@nd-soft.pl">info&#64;nd-soft.pl</a>.</p>

          <h2>9. Skarga do organu nadzorczego</h2>
          <p>
            Masz prawo wnieść skargę do Prezesa Urzędu Ochrony Danych Osobowych (ul. Stawki 2, 00-193 Warszawa),
            jeśli uznasz, że przetwarzanie Twoich danych narusza przepisy RODO.
          </p>

          <h2>10. Dobrowolność podania danych</h2>
          <p>
            Podanie danych jest dobrowolne, ale niezbędne do obsługi zgłoszenia — bez imienia i nazwiska, adresu
            e-mail i zgody na przetwarzanie danych nie będziemy mogli skontaktować się z Tobą w sprawie rejestracji.
          </p>

          <h2>11. Zautomatyzowane podejmowanie decyzji</h2>
          <p>
            Twoje dane nie będą wykorzystywane do zautomatyzowanego podejmowania decyzji, w tym profilowania.
          </p>
        </div>
      </p-card>
    </div>
  `,
  styles: [`
    .back       { display: inline-block; margin-bottom: 1rem; color: var(--swim-muted); text-decoration: none; font-size: .85rem; }
    .rodo-card  { max-width: 800px; background: var(--swim-card) !important; }
    .rodo       { font-size: .88rem; line-height: 1.6; color: var(--swim-muted); }
    .rodo h2    { color: var(--swim-gold); font-size: 1rem; margin: 1.5rem 0 .5rem; }
    .rodo p     { margin: 0 0 .75rem; }
    .rodo ul    { margin: 0 0 .75rem; padding-left: 1.25rem; }
    .rodo li    { margin-bottom: .35rem; }
    .rodo strong { color: #e8e8e8; }
    .rodo a     { color: var(--swim-gold); }
  `]
})
export class RodoComponent {}
