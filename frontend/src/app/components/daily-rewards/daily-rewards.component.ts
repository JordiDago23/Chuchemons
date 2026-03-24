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

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadDailyRewards();
  }

  loadDailyRewards(): void {
    this.isLoading = true;
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
    return new Date(reward.next_available_at) <= new Date();
  }

  getTimeUntilAvailable(reward: any): string {
    if (!reward) return '';
    const now = new Date();
    const available = new Date(reward.next_available_at);
    const diff = available.getTime() - now.getTime();
    
    if (diff <= 0) return 'Disponible ahora';
    
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    return `En ${hours}h ${minutes}m`;
  }
}
