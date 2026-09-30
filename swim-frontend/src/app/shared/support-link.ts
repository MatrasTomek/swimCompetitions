/**
 * The donation page address from the build configuration, or null when it is not a usable
 * absolute `https:` address — the support page then shows no payment button.
 */
export function supportLink(raw: string): string | null {
  const value = raw.trim();
  if (!value) return null;
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    return null;
  }
  return url.protocol === 'https:' ? url.href : null;
}
