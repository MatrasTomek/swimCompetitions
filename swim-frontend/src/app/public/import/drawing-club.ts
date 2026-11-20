/** Clubs whose start list import shows the "additional drawing" notice instead of downloading anything. */
const DRAWING_CLUBS = ['bosir', 'bosir brzesko'];

/** True for "BOSiR" / "BOSiR Brzesko" — case and extra whitespace ignored. */
export function isDrawingClub(klub: string): boolean {
  return DRAWING_CLUBS.includes(klub.trim().replace(/\s+/g, ' ').toLowerCase());
}
