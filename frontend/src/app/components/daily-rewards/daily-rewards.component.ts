import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';

@Component({
  selector: 'app-daily-rewards',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './daily-rewards.component.html',
  styleUrls: ['./daily-rewards.component.css']
})
export class DailyRewardsComponent implements OnInit {
  xuxReward: any = null;
  chuchemonReward: any = null;
  isLoading = false;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  simulationEnabled = false;
  simulationOffsetHours = 0;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadDailyRewards();
  }

  loadDailyRewards(): void {
    this.isLoading = true;
    this.errorMessage = null;
    this.http.get<any>('http://localhost:8000/api/daily-rewards').subscribe({
      next: (data) => {
        this.xuxReward = data.xux;
        this.chuchemonReward = data.chuchemon;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading daily rewards:', error);
        this.errorMessage = 'Error cargando recompensas diarias';
        this.isLoading = false;
      }
    });
  }

  claimXuxReward(): void {
    if (this.simulationEnabled) {
      this.simulateClaim('xux');
      return;
    }

    this.http.post('http://localhost:8000/api/daily-rewards/xux', {}).subscribe({
      next: (response: any) => {
        this.successMessage = response.message;
        this.loadDailyRewards();
        setTimeout(() => this.successMessage = null, 3000);
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

    this.http.post('http://localhost:8000/api/daily-rewards/chuchemon', {}).subscribe({
      next: (response: any) => {
        this.successMessage = response.message;
        this.loadDailyRewards();
        setTimeout(() => this.successMessage = null, 3000);
      },
      error: (error) => {
        console.error('Error claiming chuchemon reward:', error);
        this.errorMessage = error.error.message || 'Error reclamando recompensa';
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
          ? 'Simulación: recompensa diaria de Xuxes reclamada.'
          : 'Simulación: recompensa diaria de Xuxemon reclamada.';
        setTimeout(() => this.successMessage = null, 2500);
      }
}
