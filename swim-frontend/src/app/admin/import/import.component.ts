import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Steps } from 'primeng/steps';
import { InputText } from 'primeng/inputtext';
import { SelectButton } from 'primeng/selectbutton';
import { Button } from 'primeng/button';
import { Message } from 'primeng/message';
import { Card } from 'primeng/card';
import { Toast } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { Competition, StartlistPreviewResponse } from '../../core/models';

@Component({
  selector: 'app-import',
  imports: [FormsModule, RouterLink, Steps, InputText, SelectButton, Button, Message, Card, Toast, HeaderComponent],
  providers: [MessageService],
  template: `
    <app-header [isAdmin]="true" />
    <div class="swim-page">
      <a routerLink="/admin/zawody" class="back">← Lista zawodów</a>
      <h1 class="swim-page-title">Import listy startowej z PDF</h1>

      <p-steps [model]="steps" [activeIndex]="activeStep" styleClass="import-steps" />

      <p-card styleClass="import-card">
        @if (activeStep === 0) {
          <div class="step-content">
            <div class="field">
              <label>URL zawodów (livetiming.pl) *</label>
              <input pInputText [(ngModel)]="contestUrl" placeholder="https://livetiming.pl/contest/..." class="w-full" />
            </div>
            <div class="field">
              <label>Filtr klubu *</label>
              <input pInputText [(ngModel)]="klub" placeholder="Olimpijczyk" class="w-full" />
            </div>
            <div class="field">
              <label>Długość basenu</label>
              <p-selectbutton [options]="basenOpts" [(ngModel)]="basen" optionLabel="label" optionValue="value" />
            </div>
            @if (previewError()) { <p-message severity="error" [text]="previewError()!" /> }
            <p-button label="Podgląd →" [loading]="previewLoading()" (onClick)="doPreview()" />
          </div>
        }

        @if (activeStep === 1 && preview()) {
          <div class="step-content">
            <div class="stats-row">
              <div class="stat"><strong>{{ preview()!.stats.starts }}</strong><span>startów</span></div>
              <div class="stat"><strong>{{ preview()!.stats.athletes }}</strong><span>zawodników</span></div>
              <div class="stat"><strong>{{ preview()!.stats.blocks }}</strong><span>bloków</span></div>
            </div>
            <div class="meta-fields">
              <div class="field">
                <label>Nazwa zawodów</label>
                <input pInputText [(ngModel)]="preview()!.zawody.nazwa" class="w-full" />
              </div>
              <div class="field-row">
                <div class="field">
                  <label>Miejsce</label>
                  <input pInputText [(ngModel)]="preview()!.zawody.miejsce" class="w-full" />
                </div>
                <div class="field">
                  <label>Data</label>
                  <input pInputText [(ngModel)]="preview()!.zawody.data" class="w-full" />
                </div>
              </div>
              <div class="field">
                <label>Klub</label>
                <input pInputText [(ngModel)]="preview()!.zawody.klub" class="w-full" />
              </div>
            </div>
            @if (saveError()) { <p-message severity="error" [text]="saveError()!" /> }
            <div class="step-btns">
              <p-button label="← Wróć" severity="secondary" (onClick)="activeStep=0" />
              <p-button label="Zapisz →" [loading]="saveLoading()" (onClick)="doSave()" />
            </div>

            @if (preview()!.raw_text) {
              <details class="raw-details">
                <summary>Pokaż surowy tekst PDF</summary>
                <pre class="raw-text">{{ preview()!.raw_text }}</pre>
              </details>
            }
          </div>
        }
      </p-card>
    </div>
    <p-toast />
  `,
  styles: [`
    .back          { display: inline-block; margin-bottom: 1rem; color: var(--swim-muted); text-decoration: none; font-size: .85rem; }
    .import-card   { max-width: 650px; background: var(--swim-card) !important; }
    .import-steps  { margin-bottom: 1.5rem; }
    .step-content  { display: flex; flex-direction: column; gap: 1.25rem; }
    .field         { display: flex; flex-direction: column; gap: .4rem; }
    .field label   { font-size: .85rem; color: var(--swim-muted); }
    .field-row     { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .w-full        { width: 100%; }
    .stats-row     { display: flex; gap: 2rem; background: #1a2a1a; border-radius: 6px; padding: 1rem; }
    .stat          { display: flex; flex-direction: column; align-items: center; }
    .stat strong   { font-size: 1.5rem; color: var(--swim-green); }
    .stat span     { font-size: .75rem; color: var(--swim-muted); }
    .meta-fields   { display: flex; flex-direction: column; gap: 1rem; }
    .step-btns     { display: flex; gap: 1rem; }
    .raw-details   { margin-top: .5rem; }
    .raw-details summary { cursor: pointer; color: var(--swim-muted); font-size: .85rem; }
    .raw-text      { font-size: .7rem; background: #111; padding: .75rem; border-radius: 4px; overflow: auto; max-height: 300px; white-space: pre-wrap; color: #aaa; }
  `]
})
export class ImportComponent {
  private api    = inject(ApiService);
  private router = inject(Router);
  private msg    = inject(MessageService);

  contestUrl = '';
  klub       = '';
  basen      = '25m';
  basenOpts  = [{ label: '25m', value: '25m' }, { label: '50m', value: '50m' }];

  activeStep = 0;
  steps = [{ label: 'Dane wejściowe' }, { label: 'Podgląd i zapis' }];

  previewLoading = signal(false);
  previewError   = signal<string | null>(null);
  preview        = signal<StartlistPreviewResponse | null>(null);

  saveLoading = signal(false);
  saveError   = signal<string | null>(null);

  doPreview() {
    if (!this.contestUrl || !this.klub) { this.previewError.set('Uzupełnij URL i nazwę klubu.'); return; }
    this.previewLoading.set(true);
    this.previewError.set(null);
    this.api.previewStartlist(this.contestUrl, this.klub, this.basen).subscribe({
      next: res => {
        if (!res.ok) { this.previewError.set(res.error ?? 'Błąd parsowania.'); this.previewLoading.set(false); return; }
        this.preview.set(res);
        this.activeStep = 1;
        this.previewLoading.set(false);
      },
      error: err => {
        this.previewError.set(err.error?.error ?? 'Błąd połączenia.');
        this.previewLoading.set(false);
      },
    });
  }

  doSave() {
    const zawody = this.preview()?.zawody;
    if (!zawody) return;
    this.saveLoading.set(true);
    this.saveError.set(null);
    this.api.saveStartlist(zawody).subscribe({
      next: res => {
        this.msg.add({ severity: 'success', summary: 'Zapisano!', detail: res.filename });
        const slug = res.filename.replace(/\.json$/, '');
        this.router.navigate(['/admin/zawody', slug, 'edytuj']);
      },
      error: err => {
        this.saveLoading.set(false);
        this.saveError.set(err.error?.error ?? 'Błąd zapisu.');
      },
    });
  }
}
