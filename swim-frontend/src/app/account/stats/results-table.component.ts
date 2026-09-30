import { Component, input } from '@angular/core';
import { DatePipe } from '@angular/common';
import { TableModule } from 'primeng/table';
import { MemberResult } from '../../core/models';
import { eventLabel, pbKey } from '../../shared/swim-time';

/** Read-only table of club members' results; `names` (memberId → name) adds the "Zawodnik" column. */
@Component({
  selector: 'app-results-table',
  imports: [DatePipe, TableModule],
  template: `
    <p-table [value]="rows()" [tableStyle]="{'min-width':'640px'}" styleClass="swim-datatable"
      [paginator]="rows().length > 50" [rows]="50" sortField="date" [sortOrder]="-1">
      <ng-template pTemplate="header">
        <tr>
          @if (names()) { <th>Zawodnik</th> }
          <th pSortableColumn="date">Data <p-sortIcon field="date" /></th>
          <th>Zawody</th>
          <th>Basen</th>
          <th>Konkurencja</th>
          <th pSortableColumn="timeMs">Czas <p-sortIcon field="timeMs" /></th>
          <th pSortableColumn="points">Punkty <p-sortIcon field="points" /></th>
        </tr>
      </ng-template>
      <ng-template pTemplate="body" let-r>
        <tr>
          @if (names(); as n) { <td>{{ n.get(r.memberId) ?? '—' }}</td> }
          <td class="nowrap">{{ r.date | date:'dd.MM.yyyy' }}</td>
          <td>{{ r.contestName }} <span class="muted">{{ r.contestCity }}</span></td>
          <td class="nowrap">{{ r.poolLength }} m</td>
          <td class="nowrap">{{ label(r) }}</td>
          <td class="time">{{ r.time }} @if (isPb(r)) { <span class="pb" title="Najlepszy czas w sezonie w tej konkurencji">PB</span> }</td>
          <td>{{ r.points ?? '—' }}</td>
        </tr>
      </ng-template>
      <ng-template pTemplate="emptymessage">
        <tr><td [attr.colspan]="names() ? 7 : 6" class="empty">{{ emptyText() }}</td></tr>
      </ng-template>
    </p-table>
  `,
  styles: [`
    .nowrap { white-space: nowrap; }
    .muted  { color: var(--swim-muted); font-size: .8rem; }
    .time   { font-variant-numeric: tabular-nums; font-weight: 700; white-space: nowrap; }
    .pb     { background: var(--swim-gold); color: #111; border-radius: 3px; padding: 0 .3rem; font-size: .7rem; margin-left: .3rem; }
    .empty  { text-align: center; color: var(--swim-muted); padding: 1.5rem; }
  `],
})
export class ResultsTableComponent {
  readonly rows      = input.required<MemberResult[]>();
  /** Best row per member + event + pool (`bestTimes()`), to mark personal bests. */
  readonly best      = input.required<Map<string, MemberResult>>();
  readonly names     = input<Map<string, string> | null>(null);
  readonly emptyText = input('Brak wyników.');

  label(r: MemberResult): string { return eventLabel(r.distance, r.stroke); }
  isPb(r: MemberResult): boolean { return this.best().get(pbKey(r)) === r; }
}
