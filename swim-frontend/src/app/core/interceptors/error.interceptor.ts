import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  return next(req).pipe(
    catchError(err => {
      // A failed login attempt is a 401 too — it must not wipe/redirect anything
      if (err.status === 401 && auth.isLoggedIn()) {
        auth.logout();
      }
      return throwError(() => err);
    })
  );
};
