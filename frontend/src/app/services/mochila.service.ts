import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';

export interface MochilaXuxItem {
  id: number;
  user_id: number;
  chuchemon_id: number | null;
  quantity: number;
  item_id: number | null;
  vaccine_id: number | null;
  chuchemon: {
    id: number;
    name: string;
    element: string;
    image: string;
  } | null;
  item: {
    id: number;
    name: string;
    description: string;
    type: string;
    image: string;
  } | null;
  vaccine: {
    id: number;
    name: string;
    description: string;
  } | null;
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

  // BehaviorSubject para estado reactivo de la mochila
  private mochilaDataSubject = new BehaviorSubject<MochilaResponse | null>(null);
  public mochilaData$: Observable<MochilaResponse | null> = this.mochilaDataSubject.asObservable();

  constructor(private http: HttpClient) {}

  getMochila(): Observable<MochilaResponse> {
    return this.http.get<MochilaResponse>(`${this.apiUrl}/mochila`).pipe(
      tap(data => this.mochilaDataSubject.next(data))
    );
  }

  /**
   * Refresca los datos de la mochila
   */
  refreshMochila(): void {
    this.getMochila().subscribe({
      error: (error) => console.error('Error refreshing mochila:', error)
    });
  }

  addXux(chuchemonId: number, quantity: number): Observable<AddXuxResponse> {
    return this.http.post<AddXuxResponse>(`${this.apiUrl}/mochila/add-xux`, {
      chuchemon_id: chuchemonId,
      quantity,
    }).pipe(
      tap(() => this.refreshMochila()) // Actualizar mochila después de añadir
    );
  }

  /**
   * Obtiene el valor actual de mochilaData de forma síncrona
   */
  getCurrentMochilaData(): MochilaResponse | null {
    return this.mochilaDataSubject.value;
  }
}
