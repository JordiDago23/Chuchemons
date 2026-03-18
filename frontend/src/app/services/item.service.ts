import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject } from 'rxjs';

export interface Item {
  id: number;
  name: string;
  description?: string;
  type: 'apilable' | 'no_apilable';
  image?: string;
  created_at?: string;
  updated_at?: string;
}

export interface MochilaItem {
  id: number;
  user_id: number;
  item_id?: number;
  quantity: number;
  item?: Item;
  created_at?: string;
  updated_at?: string;
}

export interface AddItemResponse {
  message: string;
  added: number;
  item: MochilaItem;
  used_spaces: number;
  free_spaces: number;
}

@Injectable({ providedIn: 'root' })
export class ItemService {
  private apiUrl = 'http://localhost:8000/api';
  private itemsSubject = new BehaviorSubject<Item[]>([]);
  public items$ = this.itemsSubject.asObservable();

  constructor(private http: HttpClient) {
    this.loadItems();
  }

  private loadItems(): void {
    this.getItems().subscribe(
      (items) => this.itemsSubject.next(items),
      (error) => console.error('Error loading items:', error)
    );
  }

  getItems(): Observable<Item[]> {
    return this.http.get<Item[]>(`${this.apiUrl}/items`);
  }

  getItem(id: number): Observable<Item> {
    return this.http.get<Item>(`${this.apiUrl}/items/${id}`);
  }

  addItem(itemId: number, quantity: number): Observable<AddItemResponse> {
    return this.http.post<AddItemResponse>(`${this.apiUrl}/mochila/add-item`, {
      item_id: itemId,
      quantity,
    });
  }

  // Admin methods
  createItem(item: Partial<Item>): Observable<Item> {
    return this.http.post<Item>(`${this.apiUrl}/admin/items`, item);
  }

  updateItem(id: number, item: Partial<Item>): Observable<Item> {
    return this.http.put<Item>(`${this.apiUrl}/admin/items/${id}`, item);
  }

  deleteItem(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/items/${id}`);
  }
}
