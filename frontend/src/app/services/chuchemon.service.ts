import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, Subject, of } from 'rxjs';
import { catchError, tap, timeout } from 'rxjs/operators';
import { Chuchemon } from '../models/chuchemon.model';

@Injectable({
  providedIn: 'root'
})
export class ChuchemonService {
  private apiUrl = 'http://localhost:8000/api/chuchemons';
  private userApiUrl = 'http://localhost:8000/api/user';
  private chuchechomsSubject = new BehaviorSubject<Chuchemon[]>([]);
  public chuchemons$ = this.chuchechomsSubject.asObservable();
  private stateChangesSubject = new Subject<void>();
  public stateChanges$ = this.stateChangesSubject.asObservable();
  private allChuchemonsCache: Chuchemon[] | null = null;
  private allChuchemonsLoadedAt = 0;
  private myChuchemonsCache: Chuchemon[] | null = null;
  private myChuchemonsLoadedAt = 0;
  private teamCache: any = null;
  private teamLoadedAt = 0;
  private readonly cacheTtlMs = 0; // Sin caché - actualizaciones inmediatas

  constructor(private http: HttpClient) {}

  private isFresh(timestamp: number): boolean {
    return timestamp > 0 && Date.now() - timestamp < this.cacheTtlMs;
  }

  invalidateCaches(): void {
    this.allChuchemonsCache = null;
    this.allChuchemonsLoadedAt = 0;
    this.myChuchemonsCache = null;
    this.myChuchemonsLoadedAt = 0;
    this.teamCache = null;
    this.teamLoadedAt = 0;
  }

  notifyChuchemonStateChanged(): void {
    this.invalidateCaches();
    this.stateChangesSubject.next();
  }

  getAllChuchemons(forceRefresh = false): Observable<Chuchemon[]> {
    if (!forceRefresh && this.allChuchemonsCache && this.isFresh(this.allChuchemonsLoadedAt)) {
      return of(this.allChuchemonsCache);
    }

    return this.http.get<Chuchemon[]>(this.apiUrl).pipe(
      tap((data) => {
        this.allChuchemonsCache = data;
        this.allChuchemonsLoadedAt = Date.now();
        this.chuchechomsSubject.next(data);
      })
    );
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
  getMyChuchemons(forceRefresh = false): Observable<Chuchemon[]> {
    if (!forceRefresh && this.myChuchemonsCache && this.isFresh(this.myChuchemonsLoadedAt)) {
      return of(this.myChuchemonsCache);
    }

    return this.http.get<Chuchemon[]>(`${this.userApiUrl}/chuchemons`).pipe(
      tap((data) => {
        this.myChuchemonsCache = data;
        this.myChuchemonsLoadedAt = Date.now();
      }),
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
      tap(() => this.notifyChuchemonStateChanged()),
      catchError((error) => {
        console.warn('Error capturing chuchemon:', error);
        return of({ message: 'Error capturando chuchemon' });
      })
    );
  }

  /**
   * Obtiene el equipo del usuario autenticado
   */
  getTeam(forceRefresh = false): Observable<any> {
    if (!forceRefresh && this.teamCache && this.isFresh(this.teamLoadedAt)) {
      return of(this.teamCache);
    }

    return this.http.get(`${this.userApiUrl}/team`).pipe(
      tap((data) => {
        this.teamCache = data;
        this.teamLoadedAt = Date.now();
      }),
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
      tap(() => this.notifyChuchemonStateChanged()),
      catchError((error) => {
        console.warn('Error saving team:', error);
        return of({ message: 'Error guardando equipo' });
      })
    );
  }
}

