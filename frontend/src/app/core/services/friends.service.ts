import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';

export interface FriendUser {
  id: number;
  nombre: string;
  apellidos: string;
  display_name: string;
  email: string;
  player_id: string;
  bio?: string | null;
  is_online: boolean;
  last_seen_at?: string | null;
  friendship_id?: number | null;
  friendship_status: 'none' | 'friends' | 'pending_sent' | 'pending_received';
  status?: 'pending' | 'accepted' | null;
}

export interface FriendsOverviewResponse {
  friends: FriendUser[];
  pending_received: FriendUser[];
  pending_sent: FriendUser[];
  stats: {
    total: number;
    online: number;
    offline: number;
  };
}

@Injectable({ providedIn: 'root' })
export class FriendsService {
  private readonly apiUrl = 'http://localhost:8000/api';
  private readonly overviewSubject = new BehaviorSubject<FriendsOverviewResponse>({
    friends: [],
    pending_received: [],
    pending_sent: [],
    stats: {
      total: 0,
      online: 0,
      offline: 0,
    },
  });

  readonly overview$ = this.overviewSubject.asObservable();

  constructor(private http: HttpClient) {}

  getOverview(): Observable<FriendsOverviewResponse> {
    return this.http.get<FriendsOverviewResponse>(`${this.apiUrl}/friends`);
  }

  updateOverview(overview: FriendsOverviewResponse): void {
    this.overviewSubject.next(overview);
  }

  get snapshot(): FriendsOverviewResponse {
    return this.overviewSubject.getValue();
  }

  searchUsers(query: string): Observable<{ results: FriendUser[]; message?: string | null }> {
    const params = new HttpParams().set('query', query);
    return this.http.get<{ results: FriendUser[]; message?: string | null }>(`${this.apiUrl}/friends/search`, { params });
  }

  sendRequest(userId: number): Observable<{ message: string; friendship: FriendUser }> {
    return this.http.post<{ message: string; friendship: FriendUser }>(`${this.apiUrl}/friends/request`, {
      user_id: userId,
    });
  }

  acceptRequest(friendshipId: number): Observable<{ message: string; friendship: FriendUser }> {
    return this.http.post<{ message: string; friendship: FriendUser }>(`${this.apiUrl}/friends/requests/${friendshipId}/accept`, {});
  }

  deleteRequest(friendshipId: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/friends/requests/${friendshipId}`);
  }

  removeFriend(userId: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/friends/${userId}`);
  }
}
