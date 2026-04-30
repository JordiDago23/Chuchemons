import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';

export interface Message {
  id?: number;
  sender_id: number;
  receiver_id: number;
  content: string;
  is_read: boolean;
  created_at?: string;
}

export interface Conversation {
  id: number;
  friend_name: string;
  last_message: string | null;
  last_message_time: string | null;
  unread_count: number;
}

@Injectable({ providedIn: 'root' })
export class ChatService {
  private apiUrl = `${window.location.protocol}//${window.location.hostname}:8000/api`;
  private messagesSubject = new BehaviorSubject<Message[]>([]);
  public messages$ = this.messagesSubject.asObservable();

  constructor(private http: HttpClient) {}

  getConversations(): Observable<{ data: Conversation[] }> {
    return this.http.get<{ data: Conversation[] }>(`${this.apiUrl}/messages`);
  }

  getConversation(friendId: number): Observable<{ data: Message[] }> {
    return this.http.get<{ data: Message[] }>(`${this.apiUrl}/messages/${friendId}`);
  }

  // Sólo mensajes con id > sinceId (para polling eficiente)
  getNewMessages(friendId: number, sinceId: number): Observable<{ data: Message[] }> {
    return this.http.get<{ data: Message[] }>(`${this.apiUrl}/messages/${friendId}?since_id=${sinceId}`);
  }

  sendMessage(friendId: number, content: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/messages/${friendId}/send`, { content });
  }

  markAsRead(friendId: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/messages/${friendId}/read-all`, {});
  }

  setMessages(messages: Message[]): void {
    this.messagesSubject.next(messages);
  }
}
