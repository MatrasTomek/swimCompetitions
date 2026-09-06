import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { InputText } from 'primeng/inputtext';
import { Select } from 'primeng/select';
import { Button } from 'primeng/button';
import { Card } from 'primeng/card';
import { Message } from 'primeng/message';
import { Toast } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { Competition, LiveConfig } from '../../core/models';

@Component({
  selector: 'app-live',
  imports: [FormsModule, InputText, Select, Button, Card, Message, Toast, HeaderComponent],
  providers: [MessageService],
  template: `
    <app-header [isAdmin]="true" />
    <div class="swim-page">
      <h1 class="swim-page-title">Konfiguracja Live</h1>
      <p-card styleClass="live-card">
        <form (ngSubmit)="submit()" class="live-form">
          <div class="field">
            <label>URL zawodów (livetiming.pl)</label>
            <input pInputText [(ngModel)]="contestUrl" name="contestUrl" placeholder="https://livetiming.pl/contest/..." class="w-full" />
          </div>
          <div class="field">
            <label>Plik zawodów</label>
            <p-select [options]="competitions()" [(ngModel)]="jsonFile" name="jsonFile"
              optionLabel="label" optionValue="value" placeholder="Wybierz zawody..."
              [filter]="true" class="w-full" />
          </div>
          @if (active()) {
            <div class="current-info">
              <strong>Aktywne:</strong> {{ active()!.nazwa }}<br/>
              <span class="muted">{{ active()!.ostatnia_aktualizacja }}</span>
              <a [href]="previewUrl()" target="_blank" rel="noopener" class="preview-link">Podgląd wyników →</a>
            </div>
          }
          @if (error()) { <p-message severity="error" [text]="error()!" /> }
          <p-button type="submit" label="Zapisz konfigurację" [loading]="loading()" />
        </form>
      </p-card>
    </div>
    <p-toast />
  `,
  styles: [`
    .live-card   { max-width: 550px; background: var(--swim-card) !important; }
    .live-form   { display: flex; flex-direction: column; gap: 1.25rem; }
    .field       { display: flex; flex-direction: column; gap: .4rem; }
    .field label { font-size: .85rem; color: var(--swim-muted); }
    .w-full      { width: 100%; }
    .current-info{ background: #1a1a2a; border-radius: 6px; padding: .75rem; font-size: .85rem; }
    .muted       { color: var(--swim-muted); font-size: .8rem; }
    .preview-link{ display: block; margin-top: .5rem; color: var(--swim-gold); }
  `]
})
export class LiveComponent implements OnInit {
  private api = inject(ApiService);
  private msg = inject(MessageService);

  contestUrl   = '';
  jsonFile     = '';
  loading      = signal(false);
  error        = signal<string | null>(null);
  active       = signal<LiveConfig | null>(null);
  competitions = signal<{ label: string; value: string }[]>([]);

  previewUrl() {
    return `#/zawody/${this.jsonFile}/wyniki`;
  }

  ngOnInit() {
    this.api.getLiveConfig().subscribe({
      next: c => {
        if (c?.contest_url) {
          this.contestUrl = c.contest_url;
          this.jsonFile   = c.json_file;
          this.active.set(c);
        }
      },
      error: err => this.msg.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się wczytać konfiguracji live.' }),
    });
    this.api.getCompetitions().subscribe({
      next: list => {
        this.competitions.set(
          list.filter(c => c.has_file).map(c => ({
            label: c.nazwa,
            value: c.file!.replace(/\.json$/, ''),
          }))
        );
      },
      error: err => this.msg.add({ severity: 'error', summary: 'Błąd', detail: err.error?.error ?? 'Nie udało się wczytać listy zawodów.' }),
    });
  }

  submit() {
    if (!this.contestUrl || !this.jsonFile) { this.error.set('Uzupełnij wszystkie pola.'); return; }
    this.loading.set(true);
    this.error.set(null);
    this.api.saveLiveConfig({ contest_url: this.contestUrl, json_file: this.jsonFile }).subscribe({
      next: () => {
        this.loading.set(false);
        this.msg.add({ severity: 'success', summary: 'Zapisano konfigurację live' });
        this.api.getLiveConfig().subscribe(c => this.active.set(c));
      },
      error: err => {
        this.loading.set(false);
        this.error.set(err.error?.error ?? 'Błąd zapisu.');
      },
    });
  }
}
