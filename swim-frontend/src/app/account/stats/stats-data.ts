/** Season aggregates behind the club statistics page — pure functions (no Angular, tested with `npm test`). */

type Row = { memberId: string; contestUuid: string; distance: number; stroke: string; poolLength: number; points: number | null };

export interface SeasonSummary { members: number; starts: number; contests: number; personalBests: number }
export interface MemberTotal { memberId: string; name: string; starts: number; bestPoints: number | null }
export interface ResultFilter { query: string; stroke: string | null; distance: number | null; pool: number | null }

/** Tiles: members with results, starts, contests and personal bests (one per member + event + pool). */
export function seasonSummary(rows: Row[]): SeasonSummary {
  return {
    members:       new Set(rows.map(r => r.memberId)).size,
    starts:        rows.length,
    contests:      new Set(rows.map(r => r.contestUuid)).size,
    personalBests: new Set(rows.map(r => `${r.memberId}|${r.distance}|${r.stroke}|${r.poolLength}`)).size,
  };
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
