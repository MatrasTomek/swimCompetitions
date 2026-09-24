import { Component, ElementRef, computed, input, model, viewChild } from '@angular/core';
import { InputText } from 'primeng/inputtext';

/**
 * Shared search box: a clear ("x") button while something is typed (Esc clears too), and a pulsing
 * gold frame plus an optional "Filtr aktywny" badge while a filter is applied — so a narrowed list
 * is never mistaken for the full one.
 *
 * Usage: `<app-search-input [(value)]="query" placeholder="Szukaj..." [badge]="'12 wyników'" />`
 */
@Component({
  selector: 'app-search-input',
  imports: [InputText],
  template: `
    <span class="swim-search" [class.swim-search--active]="highlighted()">
      <input #field pInputText type="text" autocomplete="off"
        [attr.id]="inputId() || null"
        [attr.aria-label]="ariaLabel() || placeholder()"
        [placeholder]="placeholder()"
        [value]="value()"
        (input)="value.set(field.value)"
        (keydown.escape)="clear()" />
      @if (value()) {
        <button type="button" class="swim-search__clear" title="Wyczyść wyszukiwanie" aria-label="Wyczyść wyszukiwanie"
          (click)="clear()"><i class="pi pi-times"></i></button>
      }
    </span>
    @if (active() && badge()) {
      <span class="swim-filter-badge" aria-live="polite">🔍 Filtr aktywny: {{ badge() }}</span>
    }
  `,
  styles: [`
    :host { display: inline-flex; align-items: center; gap: 1rem; flex-wrap: wrap; min-width: 0; }

    .swim-search {
      position: relative;
      display: flex;
      align-items: center;
      flex: 1;
      min-width: 10rem;
    }

    input {
      width: 100%;
      padding-right: 2.25rem;
      background: #1c1c1c;
      border-color: #333;
      color: #fff;
    }

    .swim-search__clear {
      position: absolute;
      right: .4rem;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 1.6rem;
      height: 1.6rem;
      padding: 0;
      border: 0;
      border-radius: 50%;
      background: transparent;
      color: var(--swim-muted);
      cursor: pointer;
    }
    .swim-search__clear:hover,
    .swim-search__clear:focus-visible { color: var(--swim-gold); background: rgba(255, 215, 0, .12); outline: none; }

    .swim-search--active input {
      border-color: var(--swim-gold);
      animation: swim-search-pulse 1.6s ease-in-out infinite;
    }

    .swim-filter-badge {
      color: var(--swim-gold);
      font-size: .8rem;
      font-weight: 600;
      white-space: nowrap;
    }

    @keyframes swim-search-pulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, .55); }
      50%      { box-shadow: 0 0 0 4px rgba(255, 215, 0, .15); }
    }

    @media (prefers-reduced-motion: reduce) {
      .swim-search--active input { animation: none; box-shadow: 0 0 0 2px rgba(255, 215, 0, .35); }
    }
  `],
})
export class SearchInputComponent {
  /** Search text (two-way: `[(value)]`); `(valueChange)` also fires when cleared. */
  value       = model('');
  placeholder = input('Szukaj...');
  inputId     = input<string>('');
  ariaLabel   = input<string>('');
  /** Result summary shown next to the box while a filter is applied, e.g. "12 zawodników". */
  badge       = input<string | null>(null);
  /** Pulse the frame while a filter is applied — off for plain lookups that don't narrow a visible list. */
  highlight   = input(true);

  private field = viewChild.required<ElementRef<HTMLInputElement>>('field');

  active      = computed(() => this.value().trim() !== '');
  highlighted = computed(() => this.highlight() && this.active());

  clear() {
    if (this.value()) this.value.set('');
    this.field().nativeElement.focus();
  }
}
