import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, interval, Subscription } from 'rxjs';
import { takeUntil, finalize } from 'rxjs/operators';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';
import { LevelingPanelComponent } from '../../components/leveling-panel/leveling-panel.component';
import { InfectionsPanelComponent } from '../../components/infections-panel/infections-panel.component';
import { DailyRewardsComponent } from '../../components/daily-rewards/daily-rewards.component';

interface Chuchemon {
  id: number;
  name: string;
  element: string;
  image: string;
  current_hp?: number;
  max_hp?: number;
  hp_percent?: number;
  level?: number;
  current_mida?: string;
  experience?: number;
  experience_for_next_level?: number;
  xp_percent?: number;
}

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    LevelingPanelComponent,
    InfectionsPanelComponent,
    DailyRewardsComponent
  ],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit, OnDestroy {
  user: any = null;
  loading = true;
  error = '';
  teamLoading = true;
  private destroy$ = new Subject<void>();

  // Stats — por defecto en 0 para cuentas nuevas
  stats = {
    level: 0,
    xp: 0,
    xpMax: 100,
    captured: 0,
    total: 48,
    wins: 0,
    losses: 0,
  };

  get winRatio(): string {
    const total = this.stats.wins + this.stats.losses;
    return total > 0 ? ((this.stats.wins / total) * 100).toFixed(1).replace('.', ',') + '%' : '0%';
  }

  get xpPercent(): number {
    return Math.round((this.stats.xp / this.stats.xpMax) * 100);
  }

  team: Chuchemon[] = []; // Se cargará desde la API

  typeColors: Record<string, string> = {
    Terra:  '#b8860b',
    Aire:   '#48cae4',
    Aigua:  '#457b9d',
  };

  protected get experienceProgress(): number {
    if (typeof document === 'undefined') {
      return 0;
    }

    const pageRoot = document.querySelector('app-home') ?? document.body;
    const xpText = Array.from(pageRoot.querySelectorAll('span, p, div'))
      .map((element) => element.textContent?.trim() ?? '')
      .find((text) => /\d+\s*\/\s*\d+\s*XP/i.test(text));

    const match = xpText?.match(/(\d+)\s*\/\s*(\d+)\s*XP/i);
    if (!match) {
      return 0;
    }

    const currentXp = Number(match[1]);
    const totalXp = Number(match[2]);

    if (!Number.isFinite(currentXp) || !Number.isFinite(totalXp) || totalXp <= 0) {
      return 0;
    }

    return Math.min(1, Math.max(0, currentXp / totalXp));
  }

  constructor(
    private auth: AuthService,
    private chuchemonService: ChuchemonService,
    private http: HttpClient,
    private cdRef: ChangeDetectorRef
  ) {}

  ngOnInit() {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
      this.loading = false;
      this.loadTeam();
      this.loadStats();
      return;
    }
    this.auth.me().subscribe({
      next: (data) => {
        this.user = data;
        this.loading = false;
        this.loadTeam();
        this.loadStats();
      },
      error: () => {
        this.loading = false;
        this.auth.logout();
      }
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadTeam(): void {
    this.teamLoading = true;
    this.chuchemonService.getTeam()
      .pipe(
        takeUntil(this.destroy$),
        finalize(() => { this.teamLoading = false; })
      )
      .subscribe({
        next: (response) => {
          if (response.team && Array.isArray(response.team)) {
            this.team = response.team.filter((t: Chuchemon | null) => t !== null);
          } else {
            this.team = [];
          }
          this.cdRef.detectChanges();
        },
        error: (error) => {
          console.error('Error loading team:', error);
          this.team = [];
          this.cdRef.detectChanges();
        }
      });
  }

  getElementLabel(element?: string): string {
    switch (element) {
      case 'Aigua': return 'Agua';
      case 'Terra': return 'Tierra';
      case 'Aire': return 'Aire';
      default: return element ?? '';
    }
  }

  getSizeLabel(size?: string): string {
    switch (size) {
      case 'Petit': return 'Pequeño';
      case 'Mitjà': return 'Mediano';
      case 'Gran': return 'Grande';
      default: return size ?? 'Pequeño';
    }
  }

  loadStats(): void {
    // Load total chuchemons and captured count
    this.http.get<any>('http://localhost:8000/api/chuchemons')
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.data && Array.isArray(response.data)) {
            this.stats.total = response.data.length;
            // Count captured (user's chuchemons)
            this.stats.captured = response.data.filter((ch: any) => 
              ch.captured_count && ch.captured_count > 0
            ).length;
          }
          this.cdRef.detectChanges();
        },
        error: (error) => console.error('Error loading chuchemons:', error)
      });
  }

  logout() {
    this.auth.logout();
  }
}