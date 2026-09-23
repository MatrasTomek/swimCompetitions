import { Injectable, signal } from '@angular/core';
import { Competition } from '../models';

const STORAGE_KEY = 'swim_local_competitions';

/** Start list imported by a visitor — kept only in this browser session, never sent to the server. */
export interface LocalCompetition extends Competition {
  id: string;
  imported_at: string;
}

@Injectable({ providedIn: 'root' })
export class LocalCompetitionsService {
  private _items = signal<LocalCompetition[]>(this.load());

  readonly items = this._items.asReadonly();

  get(id: string): LocalCompetition | null {
    return this._items().find(c => c.id === id) ?? null;
  }

  add(zawody: Competition): LocalCompetition {
    const item: LocalCompetition = {
      ...zawody,
      id: `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`,
      imported_at: new Date().toISOString(),
    };
    this.persist([item, ...this._items()]);
    return item;
  }

  remove(id: string): void {
    this.persist(this._items().filter(c => c.id !== id));
  }

  private load(): LocalCompetition[] {
    try {
      const data = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '[]');
      return Array.isArray(data) ? data : [];
    } catch {
      return [];
    }
  }

  private persist(items: LocalCompetition[]): void {
    this._items.set(items);
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
      // Storage full or unavailable — keep the in-memory copy for this page view.
    }
  }
}
