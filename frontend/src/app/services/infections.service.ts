import { Injectable, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, interval, Subject } from 'rxjs';
import { tap, catchError, takeUntil } from 'rxjs/operators';

export interface Infection {
  id: number;
  chuchemon_id: number;
  malaltia_id: number;
  infection_percentage: number;
  chuchemon?: any;
  malaltia?: any;
  [key: string]: any;
}

export interface Malaltia {
  id: number;
  name: string;
  description?: string;
  [key: string]: any;
}

export interface Vaccine {
  id: number;
  name: string;
  stock?: number;
  [key: string]: any;
}

@Injectable({
  providedIn: 'root'
})
export class InfectionsService implements OnDestroy {
  private readonly API_BASE = 'http://localhost:8000/api';
  private readonly CACHE_TTL_MS = 0; // Sin caché - actualizaciones inmediatas

  private destroy$ = new Subject<void>();

  // BehaviorSubjects para cada tipo de dato
  private infectionsSubject = new BehaviorSubject<Infection[]>([]);
  public infections$: Observable<Infection[]> = this.infectionsSubject.asObservable();

  private malaltiesSubject = new BehaviorSubject<Malaltia[]>([]);
  public malalties$: Observable<Malaltia[]> = this.malaltiesSubject.asObservable();

  private vaccinesSubject = new BehaviorSubject<Vaccine[]>([]);
  public vaccines$: Observable<Vaccine[]> = this.vaccinesSubject.asObservable();

  // Caché individual
  private infectionsCache: Infection[] | null = null;
  private infectionsCacheTimestamp = 0;

  private malaltiesCache: Malaltia[] | null = null;
  private malaltiesCacheTimestamp = 0;

  private vaccinesCache: Vaccine[] | null = null;
  private vaccinesCacheTimestamp = 0;

  constructor(private http: HttpClient) {}

  /**
   * Verifica si una caché específica es fresca
   */
  private isCacheFresh(timestamp: number): boolean {
    return timestamp > 0 && Date.now() - timestamp < this.CACHE_TTL_MS;
  }

  /**
   * Invalida todas las cachés
   */
  invalidateAllCaches(): void {
    this.infectionsCache = null;
    this.infectionsCacheTimestamp = 0;
    this.malaltiesCache = null;
    this.malaltiesCacheTimestamp = 0;
    this.vaccinesCache = null;
    this.vaccinesCacheTimestamp = 0;
  }

  /**
   * Refresca las infecciones desde el backend
   */
  refreshInfections(forceRefresh = false): void {
    if (!forceRefresh && this.isCacheFresh(this.infectionsCacheTimestamp) && this.infectionsCache) {
      this.infectionsSubject.next(this.infectionsCache);
      return;
    }

    this.http.get<Infection[]>(`${this.API_BASE}/infections`).subscribe({
      next: (data) => {
        this.infectionsCache = data;
        this.infectionsCacheTimestamp = Date.now();
        this.infectionsSubject.next(data);
      },
      error: (error) => {
        console.error('Error loading infections:', error);
        this.infectionsSubject.next([]);
      }
    });
  }

  /**
   * Refresca las malalties (enfermedades) desde el backend
   */
  refreshMalalties(forceRefresh = false): void {
    if (!forceRefresh && this.isCacheFresh(this.malaltiesCacheTimestamp) && this.malaltiesCache) {
      this.malaltiesSubject.next(this.malaltiesCache);
      return;
    }

    this.http.get<Malaltia[]>(`${this.API_BASE}/malalties`).subscribe({
      next: (data) => {
        this.malaltiesCache = data;
        this.malaltiesCacheTimestamp = Date.now();
        this.malaltiesSubject.next(data);
      },
      error: (error) => {
        console.error('Error loading malalties:', error);
        this.malaltiesSubject.next([]);
      }
    });
  }

  /**
   * Refresca las vacunas desde el backend
   */
  refreshVaccines(forceRefresh = false): void {
    if (!forceRefresh && this.isCacheFresh(this.vaccinesCacheTimestamp) && this.vaccinesCache) {
      this.vaccinesSubject.next(this.vaccinesCache);
      return;
    }

    this.http.get<Vaccine[]>(`${this.API_BASE}/vaccines`).subscribe({
      next: (data) => {
        this.vaccinesCache = data;
        this.vaccinesCacheTimestamp = Date.now();
        this.vaccinesSubject.next(data);
      },
      error: (error) => {
        console.error('Error loading vaccines:', error);
        this.vaccinesSubject.next([]);
      }
    });
  }

  /**
   * Refresca todos los datos de una vez
   */
  refreshAll(forceRefresh = false): void {
    this.refreshInfections(forceRefresh);
    this.refreshMalalties(forceRefresh);
    this.refreshVaccines(forceRefresh);
  }

  /**
   * Cura una infección usando una vacuna
   */
  cureInfection(infectionId: number, vaccineId: number): Observable<any> {
    return this.http.post(`${this.API_BASE}/infections/cure/${infectionId}/${vaccineId}`, {}).pipe(
      tap(() => {
        // Invalidar cachés y refrescar
        this.invalidateAllCaches();
        this.refreshAll(true);
      }),
      catchError((error) => {
        console.error('Error curing infection:', error);
        throw error;
      })
    );
  }

  /**
   * Obtiene los valores actuales de forma síncrona
   */
  getCurrentInfections(): Infection[] {
    return this.infectionsSubject.value;
  }

  getCurrentMalalties(): Malaltia[] {
    return this.malaltiesSubject.value;
  }

  getCurrentVaccines(): Vaccine[] {
    return this.vaccinesSubject.value;
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
