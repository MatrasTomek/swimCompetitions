import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import {
  Competition, AthletesResponse, ResultFetchResponse,
  StartlistPreviewResponse, LiveConfig, LtContest, LtCacheStatus, AthleteProfile,
  Account, AccountSummary, AccountStatus, AuthToken, ClubMember, ClubMemberInput, MemberTime, MemberTimeInput,
  RegisterRequest, UserInvoice
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
    return this.http.get<AthleteProfile>(`${this.base}/athletes/${slug}`);
  }

  getAthletesExportUrl(): string {
    return `${this.base}/athletes/export`;
  }

  /** Direct download link for one athlete's JSON profile (file includes .json). */
  getAthleteFileUrl(file: string): string {
    return `${this.base}/athletes/${file.replace(/\.json$/, '')}`;
  }

  // ── Start list import ───────────────────────────────────────────────
  /** Pool length (`zawody.basen`) is read server-side from the livetiming.pl contest page. */
  previewStartlist(contestUrl: string, klub: string) {
    return this.http.post<StartlistPreviewResponse>(`${this.base}/startlist/preview`, {
      contest_url: contestUrl, klub
    });
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

  // ── Club user accounts ──────────────────────────────────────────────
  register(data: RegisterRequest) {
    return this.http.post<{ ok: boolean }>(`${this.base}/account/register`, data);
  }

  verifyEmail(token: string) {
    return this.http.post<{ ok: boolean }>(`${this.base}/account/verify-email`, { token });
  }

  forgotPassword(email: string) {
    return this.http.post<{ ok: boolean }>(`${this.base}/account/forgot-password`, { email });
  }

  resetPassword(token: string, password: string) {
    return this.http.post<{ ok: boolean }>(`${this.base}/account/reset-password`, { token, password });
  }

  getAccount() {
    return this.http.get<Account>(`${this.base}/account/me`);
  }

  updateAccount(data: { userClub?: string; userInvoice?: UserInvoice }) {
    return this.http.patch<Account>(`${this.base}/account/me`, data);
  }

  deleteAccount(password: string) {
    return this.http.delete<{ ok: boolean }>(`${this.base}/account/me`, { body: { password } });
  }

  /** Returns a fresh token — other sessions of the account are logged out. */
  changePassword(currentPassword: string, newPassword: string) {
    return this.http.post<AuthToken>(`${this.base}/account/change-password`, { currentPassword, newPassword });
  }

  addMember(data: ClubMemberInput) {
    return this.http.post<ClubMember>(`${this.base}/account/members`, data);
  }

  updateMember(memberId: string, data: Partial<ClubMemberInput>) {
    return this.http.patch<{ ok: boolean }>(`${this.base}/account/members/${memberId}`, data);
  }

  deleteMember(memberId: string) {
    return this.http.delete<{ ok: boolean }>(`${this.base}/account/members/${memberId}`);
  }

  addMemberTime(memberId: string, data: MemberTimeInput) {
    return this.http.post<MemberTime>(`${this.base}/account/members/${memberId}/times`, data);
  }

  deleteMemberTime(memberId: string, competitionId: string) {
    return this.http.delete<{ ok: boolean }>(`${this.base}/account/members/${memberId}/times/${competitionId}`);
  }

  // ── Club user accounts — admin ──────────────────────────────────────
  getUsers() {
    return this.http.get<AccountSummary[]>(`${this.base}/users`);
  }

  setUserStatus(userId: string, status: AccountStatus) {
    return this.http.patch<AccountSummary>(`${this.base}/users/${userId}`, { status });
  }
}
