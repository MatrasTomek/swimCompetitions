import { Component, input } from '@angular/core';
import { ControlContainer, FormsModule, NgForm } from '@angular/forms';
import { InputText } from 'primeng/inputtext';
import { UserInvoice } from '../../core/models';

export const EMPTY_INVOICE: UserInvoice = { companyName: '', street: '', postalCode: '', city: '', nip: '' };

/** Polish NIP: 10 digits (dashes, spaces and a "PL" prefix allowed), last one a mod-11 checksum — same as nip_valid() in includes/user_repo.php. */
export function nipValid(nip: string): boolean {
  const d = nip.trim().replace(/^PL|[\s-]/gi, '');
  if (!/^\d{10}$/.test(d)) return false;
  const sum = [6, 5, 7, 2, 3, 4, 5, 6, 7].reduce((s, w, i) => s + w * +d[i], 0);
  return sum % 11 === +d[9];
}

export function invoiceValid(inv: UserInvoice): boolean {
  return !!inv.companyName.trim() && !!inv.street.trim() && /^\d{2}-\d{3}$/.test(inv.postalCode.trim())
    && !!inv.city.trim() && nipValid(inv.nip);
}

/**
 * Invoice detail fields (company name, address, NIP) placed inside the parent's
 * template-driven form — `submitted` shows errors of untouched fields.
 */
@Component({
  selector: 'app-invoice-fields',
  imports: [FormsModule, InputText],
  viewProviders: [{ provide: ControlContainer, useExisting: NgForm }],
  template: `
    <div class="field">
      <label for="companyName">Pełna nazwa firmy *</label>
      <input pInputText id="companyName" name="companyName" [(ngModel)]="value().companyName" required maxlength="200" autocomplete="organization" #cn="ngModel" />
      @if (cn.invalid && (cn.touched || submitted())) { <small class="err">Podaj pełną nazwę firmy.</small> }
    </div>
    <div class="field">
      <label for="street">Ulica i numer *</label>
      <input pInputText id="street" name="street" [(ngModel)]="value().street" required maxlength="150" autocomplete="address-line1" #st="ngModel" />
      @if (st.invalid && (st.touched || submitted())) { <small class="err">Podaj ulicę i numer.</small> }
    </div>
    <div class="row">
      <div class="field">
        <label for="postalCode">Kod pocztowy *</label>
        <input pInputText id="postalCode" name="postalCode" [(ngModel)]="value().postalCode" required pattern="\\d{2}-\\d{3}" maxlength="6"
          placeholder="00-000" autocomplete="postal-code" #pc="ngModel" />
        @if (pc.invalid && (pc.touched || submitted())) { <small class="err">Format 00-000.</small> }
      </div>
      <div class="field">
        <label for="city">Miejscowość *</label>
        <input pInputText id="city" name="city" [(ngModel)]="value().city" required maxlength="100" autocomplete="address-level2" #ct="ngModel" />
        @if (ct.invalid && (ct.touched || submitted())) { <small class="err">Podaj miejscowość.</small> }
      </div>
    </div>
    <div class="field">
      <label for="nip">NIP *</label>
      <input pInputText id="nip" name="nip" [(ngModel)]="value().nip" required maxlength="16" inputmode="numeric" #nip="ngModel" />
      @if (!nipOk() && (nip.touched || submitted())) { <small class="err">Podaj poprawny NIP (10 cyfr).</small> }
    </div>
  `,
  styles: [`
    :host       { display: flex; flex-direction: column; gap: 1.25rem; width: 100%; }
    .row        { display: grid; grid-template-columns: 1fr 2fr; gap: 1.25rem; }
    .field      { display: flex; flex-direction: column; gap: .4rem; min-width: 0; }
    .field label { font-size: .85rem; color: var(--swim-muted); }
    .field input { width: 100%; }
    .err        { color: #f44336; font-size: .78rem; }
    @media (max-width: 600px) {
      .row { grid-template-columns: 1fr; }
    }
  `]
})
export class InvoiceFieldsComponent {
  value     = input.required<UserInvoice>();
  submitted = input(false);

  nipOk() {
    return nipValid(this.value().nip);
  }
}
