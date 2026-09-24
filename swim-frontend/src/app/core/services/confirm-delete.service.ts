import { Injectable, inject } from '@angular/core';
import { ConfirmationService } from 'primeng/api';

/** Key of the single global delete confirmation dialog rendered by `ConfirmDeleteComponent` in the app root. */
export const CONFIRM_DELETE_KEY = 'confirm-delete';

export interface ConfirmDeleteOptions {
  /** Name of the object being deleted — shown in quotes in the default message. */
  name?: string;
  /** Overrides the default message entirely. */
  message?: string;
  header?: string;
}

/** Asks the user to confirm deleting an object in the global confirmation modal. */
@Injectable({ providedIn: 'root' })
export class ConfirmDeleteService {
  private confirmation = inject(ConfirmationService);

  /** Resolves `true` when the user confirms, `false` when they cancel or close the modal. */
  confirm(opts: ConfirmDeleteOptions = {}): Promise<boolean> {
    const message = opts.message
      ?? (opts.name ? `Czy na pewno usunąć „${opts.name}”?` : 'Czy na pewno usunąć ten element?');

    return new Promise(resolve => {
      this.confirmation.confirm({
        key: CONFIRM_DELETE_KEY,
        header: opts.header ?? 'Potwierdzenie usunięcia',
        message: `${message} Tej operacji nie można cofnąć.`,
        icon: 'pi pi-exclamation-triangle',
        defaultFocus: 'reject',
        closeOnEscape: true,
        acceptButtonProps: { label: 'Usuń', icon: 'pi pi-trash', severity: 'danger' },
        rejectButtonProps: { label: 'Anuluj', severity: 'secondary', outlined: true },
        accept: () => resolve(true),
        reject: () => resolve(false),
      });
    });
  }
}
