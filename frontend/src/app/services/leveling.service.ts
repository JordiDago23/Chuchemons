import { Injectable, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, interval, Subject } from 'rxjs';
import { tap, catchError, takeUntil } from 'rxjs/operators';

export interface LevelingChuchemon {
  id: number;
  name: string;
  current_hp: number;
  max_hp: number;
  current_mida: string;
  xuxes_exp: number;
  xuxes_maduixa: number;
  evolve_cost_extra?: number;
  [key: string]: any;
}

@Injectable({
  providedIn: 'root'
})
export class LevelingService implements OnDestroy {
  private readonly API_BASE = 'http://localhost:8000/api';
  private readonly CACHE_TTL_MS = 0; // Sin caché - actualizaciones inmediatas

  private destroy$ = new Subject<void>();

  // BehaviorSubject para chuchemons de leveling
  private levelingChuchemonsSubject = new BehaviorSubject<LevelingChuchemon[]>([]);
  public levelingChuchemons$: Observable<LevelingChuchemon[]> = this.levelingChuchemonsSubject.asObservable();

  // Caché
  private cache: LevelingChuchemon[] | null = null;
  private cacheTimestamp = 0;

  constructor(private http: HttpClient) {}

  /**
   * Verifica si la caché es fresca
   */
  private isCacheFresh(): boolean {
    return this.cache !== null && Date.now() - this.cacheTimestamp < this.CACHE_TTL_MS;
  }

  /**
   * Invalida la caché (forzar recarga en próximo refresh)
   */
  invalidateCache(): void {
    this.cache = null;
    this.cacheTimestamp = 0;
  }

  /**
   * Refresca los chuchemons de leveling desde el backend
   * @param forceRefresh - Si es true, ignora la caché
   */
  refreshLevelingChuchemons(forceRefresh = false): void {
    // Si la caché es fresca y no se fuerza refresh, usar caché
    if (!forceRefresh && this.isCacheFresh() && this.cache) {
      this.levelingChuchemonsSubject.next(this.cache);
      return;
    }

    this.http.get<any>(`${this.API_BASE}/level/chuchemons`).subscribe({
      next: (response) => {
        // El backend devuelve { chuchemons: [...], config: {...} }
        const data = response.chuchemons || response;
        this.cache = data;
        this.cacheTimestamp = Date.now();
        this.levelingChuchemonsSubject.next(data);
      },
      error: (error) => {
        console.error('Error loading leveling chuchemons:', error);
        // En caso de error, emitir array vacío
        this.levelingChuchemonsSubject.next([]);
      }
    });
  }

  /**
   * Obtiene los chuchemons de leveling de forma síncrona (valor actual)
   */
  getCurrentLevelingChuchemons(): LevelingChuchemon[] {
    return this.levelingChuchemonsSubject.value;
  }

  /**
   * Evoluciona un chuchemon
   */
  evolveChuchemon(chuchemonId: number): Observable<any> {
    return this.http.post<any>(`${this.API_BASE}/user/chuchemons/${chuchemonId}/evolve`, {}).pipe(
      tap(() => {
        // Invalidar caché y refrescar
        this.invalidateCache();
        this.refreshLevelingChuchemons(true);
      }),
      catchError((error) => {
        console.error('Error evolving chuchemon:', error);
        throw error;
      })
    );
  }

  /**
   * Cura un chuchemon con Xux de Maduixa
   */
  healChuchemon(chuchemonId: number, quantity: number): Observable<any> {
    return this.http.post<any>(`${this.API_BASE}/user/chuchemons/${chuchemonId}/heal`, { quantity }).pipe(
      tap(() => {
        // Invalidar caché y refrescar
        this.invalidateCache();
        this.refreshLevelingChuchemons(true);
      }),
      catchError((error) => {
        console.error('Error healing chuchemon:', error);
        throw error;
      })
    );
  }

  /**
   * Notifica que el estado de un chuchemon ha cambiado externamente
   * (para invalidar caché cuando otros componentes hacen cambios)
   */
  notifyStateChanged(): void {
    this.invalidateCache();
    this.refreshLevelingChuchemons(true);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
