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

export interface AuthToken {
  token: string;
  expires_at: string;
}
