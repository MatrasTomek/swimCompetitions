/**
 * Polish plural form: `one` for 1, `few` for 2–4 (but not 12–14), otherwise `many`.
 * E.g. plural(n, 'start', 'starty', 'startów').
 */
export function plural(n: number, one: string, few: string, many: string): string {
  if (n === 1) return one;
  const d = n % 10, dd = n % 100;
  return d >= 2 && d <= 4 && (dd < 12 || dd > 14) ? few : many;
}
