import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { MochilaService } from '../../services/mochila.service';

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
  private refreshInterval: any = null;
  
  // Configuración dinámica de recompensas
  rewardConfig = {
    daily_xux_quantity: 10,
    daily_xux_hour: '06:00',
    daily_chuchemon_hour: '08:00'
  };

  constructor(
    private http: HttpClient,
    private mochilaService: MochilaService
  ) {}

  ngOnInit(): void {
    this.loadDailyRewards();
    this.loadMochilaInfo();
    this.loadTeamInfo();
    
    // Refrescar cada 60 segundos para detectar cambios de horario por admin
    this.refreshInterval = setInterval(() => {
      this.loadDailyRewards();
      this.loadMochilaInfo();
      this.loadTeamInfo();
    }, 60000);
  }

  ngOnDestroy(): void {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
    }
  }

  loadDailyRewards(): void {
    this.isLoading = true;
    this.errorMessage = null;
    this.http.get<any>('http://localhost:8000/api/daily-rewards').subscribe({
      next: (data) => {
        this.xuxReward = data.xux;
        this.chuchemonReward = data.chuchemon;
        
        // Actualizar configuración si viene del backend
        if (data.config) {
          this.rewardConfig.daily_xux_quantity = data.config.daily_xux_quantity ?? 10;
          this.rewardConfig.daily_xux_hour = data.config.daily_xux_hour ?? '06:00';
          this.rewardConfig.daily_chuchemon_hour = data.config.daily_chuchemon_hour ?? '08:00';
        }
        
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading daily rewards:', error);
        this.errorMessage = 'Error cargando recompensas diarias';
        this.isLoading = false;
      }
    });
  }

  loadMochilaInfo(): void {
    this.mochilaService.getMochila().subscribe({
      next: (data) => {
        this.mochilaInfo = data;
      },
      error: (error) => {
        console.error('Error loading mochila info:', error);
      }
    });
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

    // Verificar espacio en mochila - necesitamos exactamente 2 slots libres (10 items = 2 stacks de 5)
    const slotsNeeded = 2;
    if (this.mochilaInfo && this.mochilaInfo.free_spaces < slotsNeeded) {
      this.errorMessage = `Tu mochila está llena (${this.mochilaInfo.used_spaces}/${this.mochilaInfo.max_spaces}). Libera al menos ${slotsNeeded} espacios antes de reclamar las Chuches.`;
      setTimeout(() => this.errorMessage = null, 5000);
      return;
    }

    this.http.post('http://localhost:8000/api/daily-rewards/xux', {}).subscribe({
      next: (response: any) => {
        let msg = `+${response.xux_quantity} Chuches`;
        if (response.vaccine) {
          msg += ` + ${response.vaccine_quantity} ${response.vaccine}`;
        }
        this.successMessage = msg;
        this.loadDailyRewards();
        this.loadMochilaInfo();
        setTimeout(() => this.successMessage = null, 4000);
      },
      error: (error) => {
        console.error('Error claiming xux reward:', error);
        this.errorMessage = error.error.message || 'Error reclamando recompensa';
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
        this.successMessage = response.message || '¡Chuchemon obtenido!';
        this.loadDailyRewards();
        this.loadTeamInfo();
        setTimeout(() => this.successMessage = null, 3000);
      },
      error: (error) => {
        console.error('Error claiming chuchemon reward:', error);
        const errorMsg = error.error?.message || error.error?.error || 'Error reclamando recompensa';
        
        // Mensajes específicos según el error
        if (errorMsg.includes('equipo') || errorMsg.includes('3') || errorMsg.includes('tres')) {
          this.errorMessage = 'Ya tienes 3 Chuchemons en tu equipo. Para recibir uno nuevo, primero debes liberar espacio en tu equipo desde la sección "Equipo".';
        } else if (errorMsg.includes('total') || errorMsg.includes('capturado')) {
          this.errorMessage = 'Has alcanzado el límite de Chuchemons capturados. Evoluciona o libera algunos para recibir más.';
        } else {
          this.errorMessage = errorMsg;
        }
        
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
