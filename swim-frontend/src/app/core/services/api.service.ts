import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import {
  Competition, AthletesResponse, ResultFetchResponse,
  StartlistPreviewResponse, LiveConfig, LtContest, LtCacheStatus
} from '../models';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private http = inject(HttpClient);
  private base = environment.apiUrl;

  // ── Competitions ────────────────────────────────────────────────────
  getCompetitions() {
    return this.http.get<Competition[]>(`${this.base}/competitions`);
  }

  getCompetition(slug: string) {
    return this.http.get<Competition>(`${this.base}/competitions/${slug}`);
  }

  createCompetition(data: Partial<Competition>) {
    return this.http.post<{ ok: boolean; file?: string; id?: string }>(`${this.base}/competitions`, data);
  }

  uploadCompetitionFile(formData: FormData) {
    return this.http.post<{ ok: boolean; file: string }>(`${this.base}/competitions`, formData);
  }

  updateCompetition(slug: string, data: Partial<Competition>) {
    return this.http.put<{ ok: boolean }>(`${this.base}/competitions/${slug}`, data);
  }

  deleteCompetition(slug: string) {
    return this.http.delete<{ ok: boolean }>(`${this.base}/competitions/${slug}`);
  }

  getCompetitionPdfUrl(slug: string): string {
    return `${this.base}/competitions/${slug}/pdf`;
  }

  // ── Athletes ────────────────────────────────────────────────────────
  getAthletes(q = '', page = 1, perPage = 50) {
    const params = new HttpParams()
      .set('q', q)
      .set('page', page)
      .set('per_page', perPage);
    return this.http.get<AthletesResponse>(`${this.base}/athletes`, { params });
  }

  getAthlete(slug: string) {
    return this.http.get<any>(`${this.base}/athletes/${slug}`);
  }

  getAthletesExportUrl(): string {
    return `${this.base}/athletes/export`;
  }

  // ── Start list import ───────────────────────────────────────────────
  previewStartlist(contestUrl: string, klub: string, basen: string) {
    return this.http.post<StartlistPreviewResponse>(`${this.base}/startlist/preview`, {
      contest_url: contestUrl, klub, basen
    });
  }

  saveStartlist(zawody: Competition) {
    return this.http.post<{ ok: boolean; filename: string }>(`${this.base}/startlist/save`, { zawody });
  }

  // ── Results ─────────────────────────────────────────────────────────
  fetchResults(contestUrl: string, jsonFile: string) {
    return this.http.post<ResultFetchResponse>(`${this.base}/results/fetch`, {
      contest_url: contestUrl, json_file: jsonFile
    });
  }

  // ── Live config ─────────────────────────────────────────────────────
  getLiveConfig() {
    return this.http.get<LiveConfig>(`${this.base}/live`);
  }

  saveLiveConfig(config: Partial<LiveConfig>) {
    return this.http.put<{ ok: boolean }>(`${this.base}/live`, config);
  }

  // ── Announcements ───────────────────────────────────────────────────
  deleteAnnouncement(id: string) {
    return this.http.delete<{ ok: boolean }>(`${this.base}/announcements/${id}`);
  }

  // ── Contest cache (livetiming.pl) ────────────────────────────────────
  searchContests(q: string) {
    return this.http.get<LtContest[]>(`${this.base}/contests/search`, { params: { q } });
  }

  getCacheStatus() {
    return this.http.get<LtCacheStatus>(`${this.base}/contests/cache-status`);
  }

  refreshContestCache() {
    return this.http.post<{ ok: boolean; status: LtCacheStatus }>(`${this.base}/contests/cache-refresh`, {});
  }
}
