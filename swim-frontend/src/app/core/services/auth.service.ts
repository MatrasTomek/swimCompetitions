import { Injectable, signal, computed, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { AuthToken, UserRole } from '../models';

const TOKEN_KEY = 'swim_jwt';
const EXPIRES_KEY = 'swim_jwt_exp';
const ROLE_KEY = 'swim_jwt_role';
const USERNAME_KEY = 'swim_jwt_user';

/** Session of the admin or a club user — one login form, the role comes back from the API. */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private router = inject(Router);

  private _token = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  // Tokens stored before roles existed belong to the admin
  private _role = signal<UserRole>((localStorage.getItem(ROLE_KEY) as UserRole | null) ?? 'admin');
  readonly username = signal<string>(localStorage.getItem(USERNAME_KEY) ?? '');

  readonly isLoggedIn = computed(() => {
    const token = this._token();
    if (!token) return false;
    const exp = parseInt(localStorage.getItem(EXPIRES_KEY) ?? '0', 10);
    return exp > Date.now() / 1000;
  });

  readonly isAdmin = computed(() => this.isLoggedIn() && this._role() === 'admin');
  readonly isUser  = computed(() => this.isLoggedIn() && this._role() === 'user');

  getToken(): string | null {
    return this.isLoggedIn() ? this._token() : null;
  }

  login(username: string, password: string) {
    return this.http.post<AuthToken>(`${environment.apiUrl}/auth/login`, { username, password }).pipe(
      tap(res => this.setSession(res))
    );
  }

  /** Stores a token from login or from a password change (which issues a fresh one). */
  setSession(res: AuthToken): void {
    localStorage.setItem(TOKEN_KEY, res.token);
    const exp = Math.floor(new Date(res.expires_at).getTime() / 1000);
    localStorage.setItem(EXPIRES_KEY, String(exp));
    localStorage.setItem(ROLE_KEY, res.role);
    localStorage.setItem(USERNAME_KEY, res.username);
    this._role.set(res.role);
    this.username.set(res.username);
    this._token.set(res.token);
  }

  /** Page to open after logging in. */
  homeUrl(): string {
    return this._role() === 'admin' ? '/admin/zawody' : '/konto';
  }

  logout(): void {
    this.clearSession();
    this.router.navigate(['/logowanie']);
  }

  /** Forgets the session without navigating (e.g. after deleting the account). */
  clearSession(): void {
    for (const key of [TOKEN_KEY, EXPIRES_KEY, ROLE_KEY, USERNAME_KEY]) localStorage.removeItem(key);
    this._token.set(null);
    this.username.set('');
  }
}
