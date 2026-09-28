import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Card } from 'primeng/card';
import { ProgressSpinner } from 'primeng/progressspinner';
import { Message } from 'primeng/message';
import { ApiService } from '../../core/services/api.service';
import { ACCOUNT_CARD_STYLES } from './account-card.styles';

/** /konto/potwierdz?token=… — link from the registration e-mail. */
@Component({
  selector: 'app-verify-email',
  imports: [RouterLink, Card, ProgressSpinner, Message],
  template: `
    <div class="acc-page">
      <p-card header="Potwierdzenie e-maila" styleClass="acc-card">
        @if (state() === 'loading') {
          <div class="done"><p-progressSpinner strokeWidth="4" [style]="{ width: '40px', height: '40px' }" /></div>
        } @else if (state() === 'ok') {
          <div class="done">
            <i class="pi pi-check-circle"></i>
            <p>Adres e-mail został potwierdzony. Konto czeka teraz na aktywację przez administratora
               — poinformujemy Cię o tym e-mailem.</p>
          </div>
        } @else {
          <p-message severity="error" [text]="error()" />
        }
        <div class="links"><a routerLink="/logowanie">← Logowanie</a></div>
      </p-card>
    </div>
  `,
  styles: [ACCOUNT_CARD_STYLES],
})
export class VerifyEmailComponent implements OnInit {
  private api   = inject(ApiService);
  private route = inject(ActivatedRoute);

  state = signal<'loading' | 'ok' | 'error'>('loading');
  error = signal('');

  ngOnInit() {
    const token = this.route.snapshot.queryParamMap.get('token') ?? '';
    this.api.verifyEmail(token).subscribe({
      next: () => this.state.set('ok'),
      error: err => {
        this.error.set(err.error?.error ?? 'Nie udało się potwierdzić adresu. Spróbuj ponownie później.');
        this.state.set('error');
      },
    });
  }
}
