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
