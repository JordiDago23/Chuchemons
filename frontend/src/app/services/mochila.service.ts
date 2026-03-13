import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface MochilaXuxItem {
  id: number;
  user_id: number;
  chuchemon_id: number;
  quantity: number;
  chuchemon: {
    id: number;
    name: string;
    element: string;
    image: string;
  };
}

export interface MochilaResponse {
  items: MochilaXuxItem[];
  used_spaces: number;
  max_spaces: number;
  free_spaces: number;
}

export interface AddXuxResponse {
  message: string;
  added: number;
  discarded: number;
  item: MochilaXuxItem;
  used_spaces: number;
  free_spaces: number;
}

@Injectable({ providedIn: 'root' })
export class MochilaService {
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  getMochila(): Observable<MochilaResponse> {
    return this.http.get<MochilaResponse>(`${this.apiUrl}/mochila`);
  }

  addXux(chuchemonId: number, quantity: number): Observable<AddXuxResponse> {
    return this.http.post<AddXuxResponse>(`${this.apiUrl}/mochila/add-xux`, {
      chuchemon_id: chuchemonId,
      quantity,
    });
  }
}
