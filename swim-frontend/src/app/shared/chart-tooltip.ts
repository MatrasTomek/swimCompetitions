/**
 * Chart tooltips. ECharts inserts a tooltip formatter's return value as HTML — outside Angular's sanitisation —
 * and the texts shown come from imported LENEX files (contest names, times) or are typed by users (member
 * names). Every interpolated value therefore goes through escapeHtml(); only the tags written here are markup.
 */

const HTML_ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

/** Text → HTML-safe text (null / undefined → ''). */
export function escapeHtml(value: unknown): string {
  return value === null || value === undefined ? '' : String(value).replace(/[&<>"']/g, ch => HTML_ESCAPES[ch]);
}

type TooltipResult = { contestName: string; date: string; poolLength: number; time: string; points: number | null };

/** One swim on the progression chart: contest, date · pool, time · points. */
export function resultTooltip(r: TooltipResult): string {
  const date = String(r.date).split('-').reverse().join('.');
  return `${escapeHtml(r.contestName)}<br/>${escapeHtml(date)} · basen ${escapeHtml(r.poolLength)} m<br/>` +
         `<b>${escapeHtml(r.time)}</b>${r.points !== null ? ` · ${escapeHtml(r.points)} pkt` : ''}`;
}

/** One bar of a ranking: member name and the formatted value. */
export function rankingTooltip(name: string, value: string): string {
  return `${escapeHtml(name)}<br/><b>${escapeHtml(value)}</b>`;
}
