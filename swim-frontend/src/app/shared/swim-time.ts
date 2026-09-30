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
