import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { MochilaService } from '../../services/mochila.service';
import { ConfigService, RewardConfig } from '../../services/config.service';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

@Component({
  selector: 'app-daily-rewards',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './daily-rewards.component.html',
  styleUrls: ['./daily-rewards.component.css']
})
export class DailyRewardsComponent implements OnInit, OnDestroy {
  xuxReward: any = null;
  chuchemonReward: any = null;
  isLoading = false;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  simulationEnabled = false;
  simulationOffsetHours = 0;
  mochilaInfo: any = null;
  teamInfo: any = null;
  
  private destroy$ = new Subject<void>();
  
  // Configuración dinámica de recompensas (actualizada reactivamente)
  rewardConfig: RewardConfig = {
    daily_xux_quantity: 10,
    daily_xux_hour: '06:00',
    daily_chuchemon_hour: '08:00'
  };

  constructor(
    private http: HttpClient,
    private mochilaService: MochilaService,
    private configService: ConfigService
  ) {}

  ngOnInit(): void {
    // Suscribirse a las actualizaciones de configuración reactivas
    this.configService.rewardConfig$
      .pipe(takeUntil(this.destroy$))
      .subscribe(config => {
        this.rewardConfig = config;
      });
    
    // Suscribirse a los datos de recompensas diarias
    this.configService.dailyRewardsData$
      .pipe(takeUntil(this.destroy$))
      .subscribe(data => {
        if (data) {
          this.xuxReward = data.xux;
          this.chuchemonReward = data.chuchemon;
          this.isLoading = false;
        }
      });
    
    // Suscribirse a actualizaciones reactivas de mochila
    this.mochilaService.mochilaData$
      .pipe(takeUntil(this.destroy$))
      .subscribe(data => {
        if (data) {
          this.mochilaInfo = data;
        }
      });
    
    // Cargar info adicional
    this.loadMochilaInfo();
    this.loadTeamInfo();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadDailyRewards(): void {
    // Delegado al ConfigService
    this.isLoading = true;
    this.configService.refreshDailyRewards();
  }

  loadMochilaInfo(): void {
    this.mochilaService.refreshMochila();
  }

  loadTeamInfo(): void {
    this.http.get<any>('http://localhost:8000/api/user/team').subscribe({
      next: (data) => {
        this.teamInfo = data;
      },
      error: (error) => {
        console.error('Error loading team info:', error);
      }
    });
  }

  get teamCount(): number {
    if (!this.teamInfo || !this.teamInfo.team) return 0;
    return this.teamInfo.team.filter((t: any) => t !== null).length;
  }

  claimXuxReward(): void {
    if (this.simulationEnabled) {
      this.simulateClaim('xux');
      return;
    }

    // El backend valida el espacio con canFitItems() - no necesitamos validación manual aquí
    this.errorMessage = null;
    this.http.post('http://localhost:8000/api/daily-rewards/xux', {}).subscribe({
      next: (response: any) => {
        let msg = `+${response.xux_quantity} Chuches`;
        if (response.vaccine) {
          msg += ` + ${response.vaccine_quantity} ${response.vaccine}`;
        }
        this.successMessage = msg;
        
        // Refrescar datos usando los servicios reactivos
        this.configService.refreshDailyRewards();
        this.mochilaService.refreshMochila();
        
        setTimeout(() => this.successMessage = null, 4000);
      },
      error: (error) => {
        console.error('Error claiming xux reward:', error);
        // El backend devuelve un mensaje descriptivo cuando la mochila está llena
        this.errorMessage = error.error?.message || 'Error reclamando recompensa';
        setTimeout(() => this.errorMessage = null, 6000);
      }
    });
  }

  claimChuchemonReward(): void {
    if (this.simulationEnabled) {
      this.simulateClaim('chuchemon');
      return;
    }

    // No se valida el equipo - el Chuchemon va a la Chuchedex aunque el equipo esté lleno
    this.errorMessage = null;
    this.http.post('http://localhost:8000/api/daily-rewards/chuchemon', {}).subscribe({
      next: (response: any) => {
        console.log('Chuchemon claim response:', response);
        const wasNew = response.was_new ? ' nuevo' : '';
        this.successMessage = response.message || `¡Chuchemon${wasNew} obtenido!`;
        
        // Refrescar datos usando el servicio reactivo
        this.configService.refreshDailyRewards();
        this.loadTeamInfo();
        
        setTimeout(() => this.successMessage = null, 3000);
      },
      error: (error) => {
        console.error('Error claiming chuchemon reward:', error);
        console.error('Error details:', error.error);
        const errorMsg = error.error?.message || error.error?.error || 'Error reclamando recompensa';
        this.errorMessage = errorMsg;
        setTimeout(() => this.errorMessage = null, 6000);
      }
    });
  }

  isRewardAvailable(reward: any): boolean {
    if (!reward) return false;
    return new Date(reward.next_available_at) <= this.getEffectiveNow();
  }

  getTimeUntilAvailable(reward: any): string {
    if (!reward) return '';
    const now = this.getEffectiveNow();
    const available = new Date(reward.next_available_at);
    const diff = available.getTime() - now.getTime();
    
    if (diff <= 0) return 'Disponible ahora';
    
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    return `En ${hours}h ${minutes}m`;
  }

      toggleSimulation(): void {
        this.simulationEnabled = !this.simulationEnabled;
        if (!this.simulationEnabled) {
          this.simulationOffsetHours = 0;
        }
        this.successMessage = null;
        this.errorMessage = null;
      }

      shiftSimulation(hours: number): void {
        this.simulationOffsetHours += hours;
      }

      resetSimulation(): void {
        this.simulationOffsetHours = 0;
        this.successMessage = 'Simulación restablecida a la hora actual.';
        setTimeout(() => this.successMessage = null, 2500);
      }

      get simulationReference(): string {
        return this.getEffectiveNow().toLocaleString('es-ES');
      }

      private getEffectiveNow(): Date {
        const now = new Date();
        now.setHours(now.getHours() + this.simulationOffsetHours);
        return now;
      }

      private simulateClaim(type: 'xux' | 'chuchemon'): void {
        const reward = type === 'xux' ? this.xuxReward : this.chuchemonReward;

        if (!reward) {
          return;
        }

        if (!this.isRewardAvailable(reward)) {
          this.errorMessage = 'La recompensa simulada todavía no está disponible.';
          return;
        }

        const nextAvailable = new Date(this.getEffectiveNow().getTime() + 24 * 60 * 60 * 1000);
        reward.next_available_at = nextAvailable.toISOString();
        reward.claimed_at = this.getEffectiveNow().toISOString();
        this.errorMessage = null;
        this.successMessage = type === 'xux'
          ? 'Simulación: recompensa diaria de Chuches reclamada.'
          : 'Simulación: recompensa diaria de Chuchemon reclamada.';
        setTimeout(() => this.successMessage = null, 2500);
      }
}
