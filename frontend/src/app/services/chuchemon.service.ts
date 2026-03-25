import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, of } from 'rxjs';
import { catchError, timeout } from 'rxjs/operators';
import { Chuchemon } from '../models/chuchemon.model';

@Injectable({
  providedIn: 'root'
})
export class ChuchemonService {
  private apiUrl = 'http://localhost:8000/api/chuchemons';
  private userApiUrl = 'http://localhost:8000/api/user';
  private chuchechomsSubject = new BehaviorSubject<Chuchemon[]>([]);
  public chuchemons$ = this.chuchechomsSubject.asObservable();
  constructor(private http: HttpClient) {}

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

  getChuchemonsByElement(element: 'Terra' | 'Aire' | 'Aigua'): Observable<Chuchemon[]> {
    return this.http.get<Chuchemon[]>(`${this.apiUrl}/element/${element}`);
  }

  searchChuchemons(query: string): Observable<Chuchemon[]> {
    return this.http.get<Chuchemon[]>(`${this.apiUrl}/search/${query}`);
  }

  /**
   * Obtiene los chuchemons capturados por el usuario autenticado
   */
  getMyChuchemons(): Observable<Chuchemon[]> {
    return this.http.get<Chuchemon[]>(`${this.userApiUrl}/chuchemons`).pipe(
      timeout(15000), // Aumentar timeout a 15 segundos
      catchError((error) => {
        console.warn('Error loading my chuchemons:', error);
        // Retornar array vacío en caso de error para que la UI siga funcionando
        return of([]);
      })
    );
  }

  /**
   * Captura un chuchemon para el usuario autenticado
   */
  captureChuchemon(chuchemonId: number): Observable<any> {
    return this.http.post(`${this.userApiUrl}/chuchemons/${chuchemonId}/capture`, {}).pipe(
      catchError((error) => {
        console.warn('Error capturing chuchemon:', error);
        return of({ message: 'Error capturando chuchemon' });
      })
    );
  }

  /**
   * Obtiene el equipo del usuario autenticado
   */
  getTeam(): Observable<any> {
    return this.http.get(`${this.userApiUrl}/team`).pipe(
      catchError((error) => {
        console.warn('Error loading team:', error);
        return of({ team: null, team_ids: [null, null, null] });
      })
    );
  }

  /**
   * Guarda el equipo del usuario autenticado
   */
  saveTeam(chuchemon_1_id: number | null, chuchemon_2_id: number | null, chuchemon_3_id: number | null): Observable<any> {
    return this.http.post(`${this.userApiUrl}/team`, {
      chuchemon_1_id,
      chuchemon_2_id,
      chuchemon_3_id
    }).pipe(
      catchError((error) => {
        console.warn('Error saving team:', error);
        return of({ message: 'Error guardando equipo' });
      })
    );
  }
}

