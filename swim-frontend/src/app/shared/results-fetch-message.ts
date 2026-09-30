/** What to tell a club user after `POST /account/results/fetch` — pure, so the wording is tested. */

type FetchResponse = { saved: number; members_matched: number; not_found: string[]; ambiguous: string[]; no_results?: string[] };
type Plural = (n: number, one: string, few: string, many: string) => string;

export function resultsFetchMessage(res: FetchResponse, plural: Plural): { severity: 'success' | 'warn'; detail: string } {
  const noResults = res.no_results ?? [];
  // Members found in the file with only DNS/DSQ starts got nothing stored — they are not "saved for"
  const members = res.members_matched - noResults.length;

  const parts = [res.saved
    ? `Zapisano ${res.saved} ${plural(res.saved, 'wynik', 'wyniki', 'wyników')} dla ${members} ${plural(members, 'zawodnika', 'zawodników', 'zawodników')}.`
    : 'Nie zapisano żadnych wyników Twoich zawodników z tych zawodów.'];
  if (noResults.length)     parts.push(`Bez ważnych wyników (np. DNS/DSQ): ${noResults.join(', ')}.`);
  if (res.not_found.length) parts.push(`Nie znaleziono w zawodach: ${res.not_found.join(', ')}.`);
  if (res.ambiguous.length) parts.push(`Niejednoznaczni (pominięci): ${res.ambiguous.join(', ')}.`);

  return { severity: res.saved ? 'success' : 'warn', detail: parts.join(' ') };
}
