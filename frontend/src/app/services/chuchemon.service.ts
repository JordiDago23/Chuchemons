import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject } from 'rxjs';
import { Chuchemon } from '../models/chuchemon.model';

@Injectable({
  providedIn: 'root'
})
export class ChuchemonService {
  private apiUrl = 'http://localhost:8000/api/chuchemons';
  private chuchechomsSubject = new BehaviorSubject<Chuchemon[]>([]);
  public chuchemons$ = this.chuchechomsSubject.asObservable();

  constructor(private http: HttpClient) {
    this.loadChuchemons();
  }

  private loadChuchemons(): void {
    console.log('🔄 Cargando Chuchemons desde:', this.apiUrl);
    this.getAllChuchemons().subscribe({
      next: (data) => {
        console.log('✅ Chuchemons cargados:', data.length, 'items');
        this.chuchechomsSubject.next(data);
      },
      error: (error) => {
        console.error('❌ Error cargando Chuchemons:');
        console.error('   URL:', this.apiUrl);
        console.error('   Status:', error.status);
        console.error('   Mensaje:', error.message);
        console.error('   Error completo:', error);
        this.chuchechomsSubject.next([]);
      }
    });
  }

  getAllChuchemons(): Observable<Chuchemon[]> {
    return this.http.get<Chuchemon[]>(this.apiUrl);
  }

  getChuchemonById(id: number): Observable<Chuchemon> {
    return this.http.get<Chuchemon>(`${this.apiUrl}/${id}`);
  }

  getChuchemonsByElement(element: 'Tierra' | 'Aire' | 'Agua'): Observable<Chuchemon[]> {
    return this.http.get<Chuchemon[]>(`${this.apiUrl}/element/${element}`);
  }

  searchChuchemons(query: string): Observable<Chuchemon[]> {
    return this.http.get<Chuchemon[]>(`${this.apiUrl}/search/${query}`);
  }
}
