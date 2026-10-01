export interface Start {
  imie: string;
  konkurencja_nr: number;
  konkurencja: string;
  seria: string;
  godz: string;
  tor: number;
  czas: string;
  rok_urodzenia?: number;
  czas_result?: string;
  punkty?: number;
  result_fetched?: boolean;
  result_fetched_at?: string;
}

export interface Blok {
  blok: string;
  data: string;
  godz_start: string;
  starty: Start[];
}

export interface Competition {
  file?: string;
  nazwa: string;
  miejsce?: string;
  data?: string;
  klub?: string;
  basen?: '25m' | '50m';
  bloki?: Blok[];
  mtime?: number;
  has_file?: boolean;
  has_results?: boolean;
  id?: string;
}

export interface AthleteRow {
  file: string;
  imie: string;
  nazwisko: string;
  rok_urodzenia?: number;
  klub: string;
  starty: number;
}

export interface AthleteStart {
  zawody: string;
  data: string;
  miejscowosc: string;
  basen: '25m' | '50m';
  konkurencja_nr: number;
  dystans: string;
  styl: string;
  plec: string;
  tor: number;
  czas: string;
  punkty?: number | null;
  timestamp_pobrania: string;
}

export interface AthleteProfile {
  imie: string;
  nazwisko: string;
  rok_urodzenia?: number | null;
  klub: string;
  starty: AthleteStart[];
}

export interface AthletesResponse {
  athletes: AthleteRow[];
  total: number;
  page: number;
  per_page: number;
}

export interface ResultFetchResponse {
  updated: number;
  not_found: number;
  total: number;
  errors: string[];
  time: string;
}

export interface StartlistPreviewResponse {
  ok: boolean;
  zawody: Competition;
  raw_text: string;
  pdf_url: string;
  stats: { starts: number; athletes: number; blocks: number };
  error?: string;
}

export interface LiveConfig {
  contest_url: string;
  json_file: string;
  nazwa: string;
  ostatnia_aktualizacja?: string;
}

export type UserRole = 'admin' | 'user';

export interface AuthToken {
  token: string;
  expires_at: string;
  role: UserRole;
  username: string;
}

// ── Club user accounts (MongoDB) ──────────────────────────────────────
export type AccountStatus = 'pending_email' | 'pending_approval' | 'active' | 'disabled';
export type MemberSex = 'M' | 'K';
export type SwimKind = 'dowolny' | 'grzbietowy' | 'klasyczny' | 'motylkowy' | 'zmienny';

export interface ClubMember {
  memberId: string;
  memberName: string;
  memberSex: MemberSex;
  memberBirthYear: number;
}

/** One start of a club member, fetched from LENEX (`GET /account/results`). */
export interface MemberResult {
  memberId: string;
  contestUuid: string;
  contestName: string;
  contestCity: string;
  eventId?: string;    // LENEX eventid; missing on rows fetched before it was stored
  eventNr: number;     // may repeat within one contest
  date: string;         // YYYY-MM-DD
  poolLength: 25 | 50;
  distance: number;     // m
  stroke: SwimKind;
  time: string;         // 1:05.32 / 27.34
  timeMs: number;
  points: number | null;
  fetchedAt: string | null;
}

/** `POST /account/results/fetch` — what was stored for the account's members. */
export interface ResultsFetchResponse {
  saved: number;
  members_matched: number;
  not_found: string[];
  ambiguous: string[];
  /** Members found in the LENEX file, but without a valid result to store (only DNS/DSQ starts). */
  no_results: string[];
  competition: { name: string; date: string };
}

/** Invoice details — required at registration; null for accounts created before that. */
export interface UserInvoice {
  companyName: string;
  street: string;
  postalCode: string; // 00-000
  city: string;
  nip: string;        // 10 digits
}

export interface Account {
  userId: string;
  userEmail: string;
  userClub: string;
  userInvoice: UserInvoice | null;
  status: AccountStatus;
  createdAt: string | null;
  lastLoginAt: string | null;
  clubItems: { clubMembers: ClubMember[] };
}

/** Admin's account list row. */
export interface AccountSummary extends Omit<Account, 'clubItems'> {
  memberCount: number;
}

export interface RegisterRequest {
  email: string;
  password: string;
  userClub: string;
  userInvoice: UserInvoice;
  zgoda: boolean;
  website: string;
}

export type ClubMemberInput = Omit<ClubMember, 'memberId'>;

export interface LtContest {
  uuid: string;
  name: string;
  date: string;
  city: string;
  category: string;
}

export interface LtCacheStatus {
  exists: boolean;
  count?: number;
  age_hours?: number;
  is_fresh?: boolean;
  updated_at?: string;
}

/** Registration form sent by e-mail via `POST /contact`. `website` is a honeypot — always empty for humans. */
