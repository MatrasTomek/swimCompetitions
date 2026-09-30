import { Observable, Subject, catchError, map, of, switchMap } from 'rxjs';

export type RequestOutcome<K, T> = { key: K; data: T } | { key: K; error: unknown };

/**
 * Loads data for a key the user can change quickly (season, selected member…): a new `load()` cancels the
 * request still in flight, so a slower answer for an earlier key can never overwrite the current one.
 * Errors are reported as outcomes — the stream stays alive for the next `load()`.
 */
export function latestRequest<K, T>(request: (key: K) => Observable<T>) {
  const keys = new Subject<K>();
  return {
    load: (key: K) => keys.next(key),
    outcome$: keys.pipe(switchMap(key => request(key).pipe(
      map((data): RequestOutcome<K, T> => ({ key, data })),
      catchError((error): Observable<RequestOutcome<K, T>> => of({ key, error })),
    ))),
  };
}
