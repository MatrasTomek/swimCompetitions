/** livetiming.pl contest page (mirrors `sl_contest_uuid()` on the server). */
const CONTEST_URL_RE =
  /^https?:\/\/(www\.)?livetiming\.pl\/contest\/[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}\/?([?#].*)?$/i;

/** Direct start list PDF on livetiming.pl or a subdomain, https only (mirrors `sl_startlist_pdf_url()`). */
const PDF_URL_RE = /^https:\/\/([a-z0-9-]+\.)*livetiming\.pl\/[^\s?#@]*\.pdf(\?[^\s#]*)?(#.*)?$/i;

/**
 * What a pasted start list link points to: a PDF file (name/city/date read from the PDF, pool length
 * chosen in the form), a contest page, or null when the server would refuse it.
 */
export function startlistLinkKind(url: string): 'pdf' | 'contest' | null {
  const u = url.trim();
  if (CONTEST_URL_RE.test(u)) return 'contest';
  if (PDF_URL_RE.test(u)) return 'pdf';
  return null;
}
