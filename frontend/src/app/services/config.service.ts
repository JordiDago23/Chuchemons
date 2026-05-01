import { Injectable, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, interval, Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

export interface RewardConfig {
  daily_xux_quantity: number;
  daily_xux_hour: string;
  daily_chuchemon_hour: string;
}

export interface EvolutionConfig {
  xux_petit_mitja: number;
  xux_mitja_gran: number;
}

export interface DailyRewardsData {
  xux: any;
  chuchemon: any;
  config: RewardConfig;
}

@Injectable({
  providedIn: 'root'
})
export class ConfigService implements OnDestroy {
  private readonly API_BASE = 'http://localhost:8000/api';
  private readonly POLL_INTERVAL = 60000; // 60 segundos
  
  private destroy$ = new Subject<void>();
  
  // BehaviorSubjects para configuraciones
  private rewardConfigSubject = new BehaviorSubject<RewardConfig>({
    daily_xux_quantity: 10,
    daily_xux_hour: '06:00',
    daily_chuchemon_hour: '08:00'
  });
  
  private evolutionConfigSubject = new BehaviorSubject<EvolutionConfig>({
    xux_petit_mitja: 3,
    xux_mitja_gran: 5
  });
  
  private dailyRewardsDataSubject = new BehaviorSubject<DailyRewardsData | null>(null);
  
  // Observables públicos (read-only)
  public rewardConfig$: Observable<RewardConfig> = this.rewardConfigSubject.asObservable();
  public evolutionConfig$: Observable<EvolutionConfig> = this.evolutionConfigSubject.asObservable();
  public dailyRewardsData$: Observable<DailyRewardsData | null> = this.dailyRewardsDataSubject.asObservable();
  
  private pollingStarted = false;

  constructor(private http: HttpClient) {}
  
  /**
   * Inicia el polling automático de configuraciones
   * Solo se debe llamar una vez, idealmente desde el AppComponent
   */
  startPolling(): void {
    if (this.pollingStarted) {
      return;
    }
    
    this.pollingStarted = true;
    
    // Cargar configuración de evolución inmediatamente (endpoint público)
    this.refreshEvolutionConfig();
    
    // Cargar daily rewards solo si hay token
    const token = localStorage.getItem('token');
    if (token) {
      this.refreshDailyRewards();
    }
    
    // Polling cada 60 segundos
    interval(this.POLL_INTERVAL)
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.refreshEvolutionConfig();
        
        // Solo refrescar daily rewards si hay token
        const currentToken = localStorage.getItem('token');
        if (currentToken) {
          this.refreshDailyRewards();
        }
      });
  }
  
  /**
   * Refresca la configuración de recompensas diarias y los datos actuales
   */
  refreshDailyRewards(): void {
    this.http.get<any>(`${this.API_BASE}/daily-rewards`).subscribe({
      next: (data) => {
        if (data.config) {
          const config: RewardConfig = {
            daily_xux_quantity: data.config.daily_xux_quantity ?? 10,
            daily_xux_hour: data.config.daily_xux_hour ?? '06:00',
            daily_chuchemon_hour: data.config.daily_chuchemon_hour ?? '08:00'
          };
          this.rewardConfigSubject.next(config);
        }
        
        // También emitir los datos completos de recompensas
        this.dailyRewardsDataSubject.next({
          xux: data.xux,
          chuchemon: data.chuchemon,
          config: this.rewardConfigSubject.value
        });
      },
      error: (error) => {
        console.error('Error refreshing daily rewards config:', error);
      }
    });
  }
  
  /**
   * Refresca la configuración de costos de evolución
   */
  refreshEvolutionConfig(): void {
    this.http.get<any>(`${this.API_BASE}/settings`).subscribe({
      next: (response) => {
        if (response.config) {
          const config: EvolutionConfig = {
            xux_petit_mitja: response.config.xux_petit_mitja ?? 3,
            xux_mitja_gran: response.config.xux_mitja_gran ?? 5
          };
          this.evolutionConfigSubject.next(config);
        }
      },
      error: (error) => {
        console.error('Error refreshing evolution config:', error);
      }
    });
  }
  
  /**
   * Obtiene el valor actual de rewardConfig de forma síncrona
   */
  getCurrentRewardConfig(): RewardConfig {
    return this.rewardConfigSubject.value;
  }
  
  /**
   * Obtiene el valor actual de evolutionConfig de forma síncrona
   */
  getCurrentEvolutionConfig(): EvolutionConfig {
    return this.evolutionConfigSubject.value;
  }
  
  /**
   * Obtiene los datos actuales de daily rewards de forma síncrona
   */
  getCurrentDailyRewardsData(): DailyRewardsData | null {
    return this.dailyRewardsDataSubject.value;
  }
  
  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
