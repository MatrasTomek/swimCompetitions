import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { InputText } from 'primeng/inputtext';
import { Button } from 'primeng/button';
import { Card } from 'primeng/card';
import { FileUpload, FileUploadHandlerEvent } from 'primeng/fileupload';
import { Message } from 'primeng/message';
import { Toast } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { HeaderComponent } from '../../shared/header/header.component';
import { ApiService } from '../../core/services/api.service';
import { Competition } from '../../core/models';

@Component({
  selector: 'app-competition-form',
  imports: [FormsModule, RouterLink, InputText, Button, Card, FileUpload, Message, Toast, HeaderComponent],
  providers: [MessageService],
  template: `
    <app-header [isAdmin]="true" />
    <div class="swim-page">
      <a routerLink="/admin/zawody" class="back">← Lista zawodów</a>
      <p-card [header]="isEdit ? 'Edytuj zawody' : 'Dodaj zawody'" styleClass="form-card">
        <form (ngSubmit)="submit()" class="comp-form">
          <div class="field">
            <label>Nazwa *</label>
            <input pInputText [(ngModel)]="form.nazwa" name="nazwa" required />
          </div>
          <div class="field-row">
            <div class="field">
              <label>Miejsce</label>
              <input pInputText [(ngModel)]="form.miejsce" name="miejsce" />
            </div>
            <div class="field">
              <label>Data</label>
              <input pInputText [(ngModel)]="form.data" name="data" placeholder="9-10/5/2026" />
            </div>
          </div>
          <div class="field">
            <label>Klub</label>
            <input pInputText [(ngModel)]="form.klub" name="klub" />
          </div>
          @if (!isEdit) {
            <div class="field">
              <label>Plik JSON (opcjonalnie)</label>
              <p-fileupload mode="basic" name="file" accept=".json" chooseLabel="Wybierz plik JSON"
                (onSelect)="onFileSelect($event)" />
              @if (selectedFile) { <span class="file-name">{{ selectedFile.name }}</span> }
            </div>
          }
          @if (!isEdit && !selectedFile) {
            <p-message severity="info" text="Bez pliku JSON zawody zostaną dodane jako zapowiedź." />
          }
          @if (error()) { <p-message severity="error" [text]="error()!" /> }
          <div class="form-actions">
            <a routerLink="/admin/zawody"><p-button label="Anuluj" severity="secondary" type="button" /></a>
            <p-button [label]="isEdit ? 'Zapisz' : 'Dodaj'" type="submit" [loading]="loading()" />
          </div>
        </form>
      </p-card>
    </div>
    <p-toast />
  `,
  styles: [`
    .back        { display: inline-block; margin-bottom: 1rem; color: var(--swim-muted); text-decoration: none; font-size: .85rem; }
    .form-card   { max-width: 600px; background: var(--swim-card) !important; }
    .comp-form   { display: flex; flex-direction: column; gap: 1.25rem; }
    .field       { display: flex; flex-direction: column; gap: .4rem; }
    .field label { font-size: .85rem; color: var(--swim-muted); }
    .field-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    input[pinputtext] { width: 100%; }
    .file-name   { font-size: .8rem; color: var(--swim-muted); }
    .form-actions{ display: flex; gap: .75rem; justify-content: flex-end; }
  `]
})
export class CompetitionFormComponent implements OnInit {
  private api    = inject(ApiService);
  private router = inject(Router);
  private route  = inject(ActivatedRoute);
  private msg    = inject(MessageService);

  isEdit = false;
  slug   = '';
  form: Partial<Competition> = { nazwa: '', miejsce: '', data: '', klub: '' };
  selectedFile: File | null = null;
  loading = signal(false);
  error   = signal<string | null>(null);

  ngOnInit() {
    this.slug = this.route.snapshot.paramMap.get('slug') ?? '';
    this.isEdit = !!this.slug;
    if (this.isEdit) {
      this.api.getCompetition(this.slug).subscribe({
        next: d => { this.form = { nazwa: d.nazwa, miejsce: d.miejsce, data: d.data, klub: d.klub }; },
      });
    }
  }

  onFileSelect(event: any) {
    this.selectedFile = event.files?.[0] ?? null;
  }

  submit() {
    if (!this.form.nazwa) { this.error.set('Nazwa jest wymagana.'); return; }
    this.loading.set(true);
    this.error.set(null);

    if (this.isEdit) {
      this.api.updateCompetition(this.slug, this.form).subscribe({
        next: () => { this.msg.add({ severity: 'success', summary: 'Zapisano' }); this.router.navigate(['/admin/zawody']); },
        error: err => { this.loading.set(false); this.error.set(err.error?.error ?? 'Błąd zapisu.'); },
      });
    } else if (this.selectedFile) {
      const fd = new FormData();
      fd.append('file', this.selectedFile);
      for (const [k, v] of Object.entries(this.form)) if (v) fd.append(k, v as string);
      this.api.uploadCompetitionFile(fd).subscribe({
        next: () => this.router.navigate(['/admin/zawody']),
        error: err => { this.loading.set(false); this.error.set(err.error?.error ?? 'Błąd uploadu.'); },
      });
    } else {
      this.api.createCompetition(this.form).subscribe({
        next: () => this.router.navigate(['/admin/zawody']),
        error: err => { this.loading.set(false); this.error.set(err.error?.error ?? 'Błąd zapisu.'); },
      });
    }
  }
}
