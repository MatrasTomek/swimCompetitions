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
            Listy startowe i wyniki zawodów są dostępne bezpłatnie i bez logowania. Wpłata jest dobrowolna,
            nie jest opłatą za konto klubu i nie odblokowuje żadnych funkcji.
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
