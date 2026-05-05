import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Subscription, timer } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AdminUsersService {
  private readonly apiUrl = 'http://localhost:8000/api';
  private readonly usersSubject = new BehaviorSubject<any[]>([]);
  private readonly usersLoadingSubject = new BehaviorSubject<boolean>(false);
  private usersSyncSub: Subscription | null = null;

  readonly users$ = this.usersSubject.asObservable();
  readonly usersLoading$ = this.usersLoadingSubject.asObservable();

  refreshUsers(silent = false): void {
    if (!silent) {
      this.usersLoadingSubject.next(true);
    }

    this.http.get<any>(`${this.apiUrl}/admin/users`).subscribe({
      next: (data) => {
        const users = (data?.users ?? []).map((u: any) => ({
          ...u,
          wins: Number(u?.wins ?? 0),
          defeats: Number(u?.defeats ?? u?.losses ?? 0),
        }));
        this.usersSubject.next(users);
        this.usersLoadingSubject.next(false);
      },
      error: () => {
        this.usersLoadingSubject.next(false);
      }
    });
  }

  startUsersSync(refreshMs = 10000): void {
    if (this.usersSyncSub) {
      return;
    }

    this.usersSyncSub = timer(refreshMs, refreshMs).subscribe(() => {
      this.refreshUsers(true);
    });
  }

  stopUsersSync(): void {
    this.usersSyncSub?.unsubscribe();
    this.usersSyncSub = null;
  }

  constructor(private http: HttpClient) {}
}