import { Component, inject, Input } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { Button } from 'primeng/button';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive, Button],
  template: `
    <header class="swim-header">
      <div class="swim-header__brand">
        <a routerLink="/" class="swim-header__logo">
          <img src="assets/logo.jpg" alt="Olimpijczyk" height="40" />
          <span>Olimpijczyk Proszówki</span>
        </a>
      </div>
      <nav class="swim-header__nav">
        @if (isAdmin) {
          <a routerLink="/admin/zawody" routerLinkActive="active">Zawody</a>
          <a routerLink="/admin/zawodnicy" routerLinkActive="active">Zawodnicy</a>
          <a routerLink="/admin/import" routerLinkActive="active">Import PDF</a>
          <a routerLink="/admin/live" routerLinkActive="active">Live</a>
          <p-button label="Wyloguj" severity="secondary" size="small" (onClick)="auth.logout()" />
        } @else {
          <a routerLink="/" routerLinkActive="active" [routerLinkActiveOptions]="{exact:true}">Zawody</a>
          <a routerLink="/admin/login" class="admin-link">Admin</a>
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
  `]
})
export class HeaderComponent {
  @Input() isAdmin = false;
  auth = inject(AuthService);
}
