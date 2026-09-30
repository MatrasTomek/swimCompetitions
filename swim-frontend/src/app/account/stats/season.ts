/** Seasons (calendar years) offered on the results views. */
export const SEASON_FIRST = 2026;

export function seasonYears(now: Date = new Date()): number[] {
  const years: number[] = [];
  for (let y = Math.max(now.getFullYear(), SEASON_FIRST); y >= SEASON_FIRST; y--) years.push(y);
  return years;
}

/** Season from the `?rok=` query param; anything outside the offered years → the current season. */
export function parseSeason(raw: string | null, now: Date = new Date()): number {
  const years = seasonYears(now);
  const y = Number(raw);
  return raw !== null && raw !== '' && years.includes(y) ? y : years[0];
}
