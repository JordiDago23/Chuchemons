import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class LoadingService {
  private readonly pendingRequestsSubject = new BehaviorSubject(0);
  readonly pendingRequests$ = this.pendingRequestsSubject.asObservable();
  readonly isLoading$ = new BehaviorSubject(false);

  start(): void {
    const nextCount = this.pendingRequestsSubject.value + 1;
    this.pendingRequestsSubject.next(nextCount);
    this.isLoading$.next(nextCount > 0);
  }

  stop(): void {
    const nextCount = Math.max(0, this.pendingRequestsSubject.value - 1);
    this.pendingRequestsSubject.next(nextCount);
    this.isLoading$.next(nextCount > 0);
  }
}