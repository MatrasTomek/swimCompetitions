import { Component, ViewEncapsulation } from '@angular/core';
import { ConfirmDialog } from 'primeng/confirmdialog';
import { CONFIRM_DELETE_KEY } from '../../core/services/confirm-delete.service';

/** Global delete confirmation modal — rendered once in the app root, opened via `ConfirmDeleteService`. */
@Component({
  selector: 'app-confirm-delete',
  imports: [ConfirmDialog],
  template: `
    <p-confirmDialog [key]="key" styleClass="swim-confirm" maskStyleClass="swim-confirm-mask"
                     [style]="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" />
  `,
  // The dialog is rendered outside this component's view, so its styles must be global.
  encapsulation: ViewEncapsulation.None,
  styles: [`
    .swim-confirm-mask { background: rgba(0, 0, 0, .7); backdrop-filter: blur(2px); }

    .p-dialog.swim-confirm {
      background: var(--swim-card);
      border: 1px solid var(--swim-border);
      border-top: 3px solid var(--swim-gold);
      border-radius: 8px;
      color: var(--swim-text);
      box-shadow: 0 12px 40px rgba(0, 0, 0, .6);

      .p-dialog-header  { background: transparent; color: var(--swim-gold); padding: 1.25rem 1.25rem .75rem; }
      .p-dialog-title   { font-size: 1.1rem; font-weight: 700; }
      .p-dialog-content { background: transparent; color: var(--swim-text); padding: 0 1.25rem 1.25rem; display: flex; align-items: flex-start; gap: .9rem; }
      .p-dialog-footer  { background: transparent; border-top: 1px solid var(--swim-border); padding: .9rem 1.25rem; display: flex; justify-content: flex-end; gap: .6rem; }

      .p-dialog-header .p-button {
        color: var(--swim-muted);
        &:hover { color: var(--swim-gold); background: rgba(255, 215, 0, .12); }
      }

      .p-confirmdialog-icon { color: var(--swim-red); font-size: 1.6rem; }
      .p-confirmdialog-message { margin: 0; line-height: 1.5; }

      .p-button-secondary.p-button-outlined {
        background: transparent;
        border-color: var(--swim-border);
        color: var(--swim-text);
        &:hover { border-color: var(--swim-gold); color: var(--swim-gold); background: rgba(255, 215, 0, .08); }
      }

      .p-button-danger:not(.p-button-outlined) {
        background: var(--swim-red);
        border-color: var(--swim-red);
        color: #fff;
        font-weight: 600;
        &:hover { background: #dc2626; border-color: #dc2626; }
      }

      .p-button:focus-visible { outline: 2px solid var(--swim-gold); outline-offset: 2px; box-shadow: none; }
    }
  `],
})
export class ConfirmDeleteComponent {
  readonly key = CONFIRM_DELETE_KEY;
}
