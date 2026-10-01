/** Season aggregates behind the club statistics page — pure functions (no Angular, tested with `npm test`). */

type Row = {
  memberId: string; contestUuid: string; distance: number; stroke: string; poolLength: number;
  points: number | null; timeMs: number; date: string; eventNr: number;
};

export interface SeasonSummary { members: number; starts: number; contests: number; improvements: number }
export interface MemberTotal { memberId: string; name: string; starts: number; bestPoints: number | null }
export interface ResultFilter { query: string; stroke: string | null; distance: number | null; pool: number | null }

/** Tiles: members with results, starts, contests and time improvements (`timeImprovements()`). */
export function seasonSummary(rows: Row[]): SeasonSummary {
  return {
    members:      new Set(rows.map(r => r.memberId)).size,
    starts:       rows.length,
    contests:     new Set(rows.map(r => r.contestUuid)).size,
    improvements: timeImprovements(rows),
  };
}

/**
 * Starts that beat the member's earlier best time in the same event + pool, in swim order. The first start of an
 * event and an equal time do not count. A moment with several starts (order unknown) counts once: an improvement
 * when its fastest time beats the best before it — so the result never depends on the order of the rows.
 * Moments of one member + event + pool:
 * - a day with starts from one contest: one moment per event number (heats before the final; numbers may repeat,
 *   so starts with the same number share a moment),
 * - a day with starts from several contests: the whole day is one moment — event numbers of different contests
 *   say nothing about which swim came first.
 */
export function timeImprovements(rows: Row[]): number {
  // member + event + pool → date → that day's starts
  const days = new Map<string, Map<string, Row[]>>();
  for (const r of rows) {
    const k = `${r.memberId}|${r.distance}|${r.stroke}|${r.poolLength}`;
    const event = days.get(k) ?? new Map<string, Row[]>();
    event.set(r.date, [...(event.get(r.date) ?? []), r]);
    days.set(k, event);
  }
  let improvements = 0;
  for (const event of days.values()) {
    let best: number | null = null;
    for (const date of [...event.keys()].sort()) {
      for (const timeMs of dayMoments(event.get(date)!)) {
        if (best !== null && timeMs < best) improvements++;
        if (best === null || timeMs < best) best = timeMs;
      }
    }
  }
  return improvements;
}

/** Fastest time of each moment of one day (see `timeImprovements()`), in swim order. */
function dayMoments(day: Row[]): number[] {
  const fastest = (swims: Row[]) => Math.min(...swims.map(r => r.timeMs));
  if (new Set(day.map(r => r.contestUuid)).size > 1) return [fastest(day)];
  const byNr = new Map<number, Row[]>();
  for (const r of day) byNr.set(r.eventNr, [...(byNr.get(r.eventNr) ?? []), r]);
  return [...byNr.keys()].sort((a, b) => a - b).map(nr => fastest(byNr.get(nr)!));
}

/** Starts and best points of every member with results, in order of first appearance. */
export function memberTotals(rows: Row[], names: Map<string, string>): MemberTotal[] {
  const totals = new Map<string, MemberTotal>();
  for (const r of rows) {
    const t = totals.get(r.memberId) ?? { memberId: r.memberId, name: names.get(r.memberId) ?? '—', starts: 0, bestPoints: null };
    t.starts++;
    if (r.points !== null && (t.bestPoints === null || r.points > t.bestPoints)) t.bestPoints = r.points;
    totals.set(r.memberId, t);
  }
  return [...totals.values()];
}

/** The `limit` members with the highest value (ties by name); members without the value are left out. */
export function topBy(totals: MemberTotal[], key: 'starts' | 'bestPoints', limit: number): MemberTotal[] {
  return totals
    .filter(t => t[key] !== null)
    .sort((a, b) => (b[key] as number) - (a[key] as number) || a.name.localeCompare(b.name, 'pl'))
    .slice(0, limit);
}

/** How many members have the value at all (the ranking may show only the top of them). */
export function countWith(totals: MemberTotal[], key: 'starts' | 'bestPoints'): number {
  return totals.filter(t => t[key] !== null).length;
}

/** Table filters: every word of the query must be in the member's name; stroke / distance / pool when set. */
export function filterResults<T extends { memberName: string; stroke: string; distance: number; poolLength: number }>(
  rows: T[], f: ResultFilter,
): T[] {
  const terms = f.query.trim().toLocaleLowerCase('pl').split(/\s+/).filter(Boolean);
  return rows.filter(r => {
    const name = r.memberName.toLocaleLowerCase('pl');
    return terms.every(t => name.includes(t))
      && (f.stroke === null || r.stroke === f.stroke)
      && (f.distance === null || r.distance === f.distance)
      && (f.pool === null || r.poolLength === f.pool);
  });
}
