/** Swim time and personal-best helpers — pure functions shared by the results views. */

export type PbRow = { memberId: string; distance: number; stroke: string; poolLength: number; timeMs: number; date: string };

/** 65320 → "1:05.32", 27340 → "27.34" (minutes are not wrapped into hours). */
export function formatSwimTime(ms: number): string {
  const cs  = Math.floor(ms / 10);
  const min = Math.floor(cs / 6000);
  const sec = Math.floor((cs % 6000) / 100);
  const hs  = String(cs % 100).padStart(2, '0');
  return min > 0 ? `${min}:${String(sec).padStart(2, '0')}.${hs}` : `${sec}.${hs}`;
}

export function eventLabel(distance: number, stroke: string): string {
  return `${distance} m ${stroke}`;
}

export function eventKey(r: { distance: number; stroke: string }): string {
  return `${r.distance}|${r.stroke}`;
}

export function pbKey(r: { memberId: string; distance: number; stroke: string; poolLength: number }): string {
  return `${r.memberId}|${r.distance}|${r.stroke}|${r.poolLength}`;
}

/** Best (lowest) time per member + event + pool; on a tie the earlier swim counts. */
export function bestTimes<T extends PbRow>(rows: T[]): Map<string, T> {
  const best = new Map<string, T>();
  for (const r of rows) {
    const k = pbKey(r);
    const b = best.get(k);
    if (!b || r.timeMs < b.timeMs || (r.timeMs === b.timeMs && r.date < b.date)) best.set(k, r);
  }
  return best;
}

// ── One member's season (the member statistics page) ──

/** Order of strokes wherever events are listed. */
const STROKE_ORDER = ['dowolny', 'grzbietowy', 'klasyczny', 'motylkowy', 'zmienny'];

function byEvent(a: { distance: number; stroke: string }, b: { distance: number; stroke: string }): number {
  return STROKE_ORDER.indexOf(a.stroke) - STROKE_ORDER.indexOf(b.stroke) || a.distance - b.distance;
}

/** Events (distance + stroke) the rows contain — the most swum first, so it can be the default choice. */
export function eventOptions(rows: { distance: number; stroke: string }[]): { value: string; label: string; count: number }[] {
  const events = new Map<string, { value: string; label: string; count: number; distance: number; stroke: string }>();
  for (const r of rows) {
    const value = eventKey(r);
    const e = events.get(value) ?? { value, label: eventLabel(r.distance, r.stroke), count: 0, distance: r.distance, stroke: r.stroke };
    e.count++;
    events.set(value, e);
  }
  return [...events.values()]
    .sort((a, b) => b.count - a.count || byEvent(a, b))
    .map(({ value, label, count }) => ({ value, label, count }));
}

/** Swims of one event split by pool length (25 m and 50 m times are not comparable), each in date order. */
export function progression<T extends PbRow>(rows: T[], event: string): { pool: number; rows: T[] }[] {
  const swims = rows.filter(r => eventKey(r) === event);
  return [25, 50]
    .map(pool => ({ pool, rows: swims.filter(r => r.poolLength === pool).sort((a, b) => a.date.localeCompare(b.date)) }))
    .filter(s => s.rows.length > 0);
}

/** Best row per event and pool, listed by stroke, distance and pool. */
export function sortedBests<T extends PbRow>(rows: T[]): T[] {
  return [...bestTimes(rows).values()].sort((a, b) => byEvent(a, b) || a.poolLength - b.poolLength);
}
