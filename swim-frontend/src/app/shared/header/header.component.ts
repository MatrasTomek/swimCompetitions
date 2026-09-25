import { Component, inject, Input } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { Button } from 'primeng/button';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive, Button],
  template: `
    <header class="swim-header" [class.swim-header--admin]="isAdmin">
      <div class="swim-header__brand">
        <a routerLink="/" class="swim-header__logo">
          <img src="assets/logo.jpg" alt="Olimpijczyk Proszówki" title="Olimpijczyk Proszówki" height="40" />
          <span class="swim-header__name swim-header__name--full">Olimpijczyk Proszówki</span>
          <span class="swim-header__name swim-header__name--short">Olimp. Proszówki</span>
        </a>
      </div>
      <nav class="swim-header__nav">
        @if (isAdmin) {
          <a routerLink="/admin/zawody" routerLinkActive="active">Zawody</a>
          <a routerLink="/admin/zawodnicy" routerLinkActive="active">Zawodnicy</a>
          <a routerLink="/admin/live" routerLinkActive="active">Live</a>
          <p-button label="Wyloguj" icon="pi pi-sign-out" ariaLabel="Wyloguj" severity="secondary" size="small" styleClass="logout-btn" (onClick)="auth.logout()" />
        } @else {
          <a routerLink="/" routerLinkActive="active" [routerLinkActiveOptions]="{exact:true}">Zawody</a>
          <a routerLink="/import" routerLinkActive="active">Listy Startowe</a>
          <a routerLink="/admin/login" class="admin-link">Wyniki</a>
        }
      </nav>
    </header>
  `,
  styles: [`
    .swim-header {
      background: var(--swim-dark);
      border-bottom: 2px solid var(--swim-gold);
      padding: 0 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 60px;
    }
    .swim-header__brand a { display: flex; align-items: center; gap: .75rem; text-decoration: none; color: var(--swim-gold); font-weight: 700; font-size: 1.1rem; }
    .swim-header__nav { display: flex; align-items: center; gap: 1.25rem; }
    .swim-header__nav a { color: #ccc; text-decoration: none; font-size: .9rem; transition: color .2s; }
    .swim-header__nav a:hover, .swim-header__nav a.active { color: var(--swim-gold); }
    .admin-link { opacity: .5; font-size: .75rem !important; }
    .swim-header__brand, .swim-header__brand a { min-width: 0; }
    .swim-header__nav { flex-shrink: 0; }
    .swim-header__name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .swim-header__name--short { display: none; }
    .swim-header__nav a { white-space: nowrap; }

    @media (max-width: 640px) {
      .swim-header { padding: 0 .75rem; gap: .75rem; }
      .swim-header__brand { min-width: 0; }
      .swim-header__brand a { gap: .5rem; font-size: .95rem; }
      .swim-header__brand img { height: 32px; width: auto; }
      .swim-header__name--full { display: none; }
      .swim-header__name--short { display: inline; }
      .swim-header__nav { gap: .75rem; }
      .swim-header__nav a { font-size: .85rem; }
      /* Wyloguj — sama ikona */
      :host ::ng-deep .logout-btn .p-button-label { display: none; }
      :host ::ng-deep .logout-btn .p-button-icon { margin: 0; }
    }

    @media (max-width: 420px) {
      .swim-header { padding: 0 .6rem; gap: .5rem; }
      .swim-header__brand a { gap: .4rem; font-size: .85rem; }
      .swim-header__brand img { height: 28px; }
      .swim-header__nav { gap: .6rem; }
      .swim-header__nav a { font-size: .8rem; }
    }

    /* Bardzo wąskie ekrany — samo logo, nazwa w alt/title */
    @media (max-width: 340px) {
      .swim-header__name--short { display: none; }
    }
    /* Menu admina jest szersze — samo logo wcześniej */
    @media (max-width: 374px) {
      .swim-header--admin .swim-header__name--short { display: none; }
    }
  `]
})
export class HeaderComponent {
  @Input() isAdmin = false;
  auth = inject(AuthService);
}
