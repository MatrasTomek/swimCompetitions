import { Injectable, signal, computed, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { AuthToken } from '../models';

const TOKEN_KEY = 'swim_jwt';
const EXPIRES_KEY = 'swim_jwt_exp';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private router = inject(Router);

  private _token = signal<string | null>(localStorage.getItem(TOKEN_KEY));

  readonly isLoggedIn = computed(() => {
    const token = this._token();
    if (!token) return false;
    const exp = parseInt(localStorage.getItem(EXPIRES_KEY) ?? '0', 10);
    return exp > Date.now() / 1000;
  });

  getToken(): string | null {
    return this.isLoggedIn() ? this._token() : null;
  }

  login(username: string, password: string) {
    return this.http.post<AuthToken>(`${environment.apiUrl}/auth/login`, { username, password }).pipe(
      tap(res => {
        localStorage.setItem(TOKEN_KEY, res.token);
        const exp = Math.floor(new Date(res.expires_at).getTime() / 1000);
        localStorage.setItem(EXPIRES_KEY, String(exp));
        this._token.set(res.token);
      })
    );
  }

  logout(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(EXPIRES_KEY);
    this._token.set(null);
    this.router.navigate(['/admin/login']);
  }
}
