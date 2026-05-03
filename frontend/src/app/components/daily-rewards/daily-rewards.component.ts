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
  freeSpaces = 20;

  // Overlay — Chuchemon
  showChuchemonOverlay = false;
  claimedChuchemon: any = null;
  wasNewChuchemon = false;
  chuchemonInfoVisible = false;

  // Overlay — Chuches
  showXuxOverlay = false;
  claimedItems: any[] = [];
  itemsVisible = false;

  readonly stars = [1,2,3,4,5,6,7,8,9,10,11,12];

  rewardConfig: RewardConfig = {
    daily_xux_quantity: 10,
    daily_xux_hour: '08:00',
    daily_chuchemon_hour: '08:00'
  };

  private destroy$ = new Subject<void>();

  constructor(
    private http: HttpClient,
    private mochilaService: MochilaService,
    private configService: ConfigService
  ) {}

  ngOnInit(): void {
    this.configService.rewardConfig$
      .pipe(takeUntil(this.destroy$))
      .subscribe(config => { this.rewardConfig = config; });

    this.configService.dailyRewardsData$
      .pipe(takeUntil(this.destroy$))
      .subscribe(data => {
        if (data) {
          this.xuxReward = data.xux;
          this.chuchemonReward = data.chuchemon;
          this.isLoading = false;
        }
      });

    this.mochilaService.mochilaData$
      .pipe(takeUntil(this.destroy$))
      .subscribe(data => {
        if (data) this.freeSpaces = data.free_spaces;
      });

    this.configService.refreshDailyRewards();
    this.mochilaService.refreshMochila();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  isRewardAvailable(reward: any): boolean {
    if (!reward) return false;
    return new Date(reward.next_available_at) <= new Date();
  }

  getTimeUntilAvailable(reward: any): string {
    if (!reward) return '';
    const diff = new Date(reward.next_available_at).getTime() - Date.now();
    if (diff <= 0) return 'Disponible';
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    return `${h}h ${m}m`;
  }

  sizeLabel(mida: string): string {
    switch (mida) {
      case 'Petit':  return 'Pequeño';
      case 'Mitjà':  return 'Mediano';
      case 'Gran':   return 'Grande';
      default:       return mida ?? '';
    }
  }

  elementColor(element: string): string {
    switch (element) {
      case 'Terra': return '#a16207';
      case 'Aire':  return '#0369a1';
      case 'Aigua': return '#0e7490';
      default:      return '#555';
    }
  }

  elementBg(element: string): string {
    switch (element) {
      case 'Terra': return '#fef9c3';
      case 'Aire':  return '#e0f2fe';
      case 'Aigua': return '#cffafe';
      default:      return '#f3f4f6';
    }
  }

  itemEmoji(name: string): string {
    const n = (name ?? '').toLowerCase();
    // Chuches
    if (n.includes('maduixa') || n.includes('fresa'))          return '🍓';
    if (n.includes('llimona') || n.includes('limon'))          return '🍋';
    if (n.includes('cola'))                                    return '🥤';
    if (n.includes('exp'))                                     return '⭐';
    // Vacunas
    if (n.includes('insulina'))                                return '💉';
    if (n.includes('xocolatina') || n.includes('chocolate'))   return '🍫';
    if (n.includes('fruita') || n.includes('fruta'))           return '🍎';
    if (n.includes('xal') || n.includes('sal'))                return '🍬';
    return '🍬';
  }

  claimChuchemonReward(): void {
    this.errorMessage = null;
    this.http.post<any>('http://localhost:8000/api/daily-rewards/chuchemon', {}).subscribe({
      next: (res) => {
        this.claimedChuchemon = res.chuchemon;
        this.wasNewChuchemon  = res.was_new;
        this.chuchemonInfoVisible = false;
        this.showChuchemonOverlay = true;
        setTimeout(() => { this.chuchemonInfoVisible = true; }, 700);
        this.configService.refreshDailyRewards();
      },
      error: (err) => {
        this.errorMessage = err.error?.message || 'Error reclamando recompensa';
        setTimeout(() => { this.errorMessage = null; }, 6000);
      }
    });
  }

  closeChuchemonOverlay(): void {
    this.showChuchemonOverlay = false;
    this.claimedChuchemon = null;
  }

  claimXuxReward(): void {
    this.errorMessage = null;
    this.http.post<any>('http://localhost:8000/api/daily-rewards/xux', {}).subscribe({
      next: (res) => {
        this.claimedItems   = res.items ?? [];
        this.itemsVisible   = false;
        this.showXuxOverlay = true;
        setTimeout(() => { this.itemsVisible = true; }, 300);
        this.configService.refreshDailyRewards();
        this.mochilaService.refreshMochila();
      },
      error: (err) => {
        this.errorMessage = err.error?.message || 'Error reclamando recompensa';
        setTimeout(() => { this.errorMessage = null; }, 6000);
      }
    });
  }

  closeXuxOverlay(): void {
    this.showXuxOverlay = false;
    this.claimedItems = [];
  }

  debugReset(): void {
    this.http.post('http://localhost:8000/api/daily-rewards/reset', {}).subscribe({
      next: () => { this.configService.refreshDailyRewards(); },
      error: () => {}
    });
  }
}
