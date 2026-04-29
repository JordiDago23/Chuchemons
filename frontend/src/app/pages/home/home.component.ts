import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil, finalize } from 'rxjs/operators';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';
import { LevelingPanelComponent } from '../../components/leveling-panel/leveling-panel.component';
import { InfectionsPanelComponent } from '../../components/infections-panel/infections-panel.component';
import { DailyRewardsComponent } from '../../components/daily-rewards/daily-rewards.component';
import { MainLayoutComponent } from '../../components/main-layout/main-layout.component';
import { ChuchemonDetailsModalComponent } from '../../components/chuchemon-details-modal/chuchemon-details-modal.component';
import { Chuchemon } from '../../models/chuchemon.model';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    LevelingPanelComponent,
    InfectionsPanelComponent,
    DailyRewardsComponent,
    MainLayoutComponent,
    ChuchemonDetailsModalComponent
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

  // Details modal properties
  showDetailsModal = false;
  selectedChuchemonForDetails: Chuchemon | null = null;

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
    private chuchemonService: ChuchemonService
  ) {}

  ngOnInit() {
    this.chuchemonService.stateChanges$
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.loadTeam(true);
        this.loadStats(true);
      });

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

  loadTeam(forceRefresh: boolean = false): void {
    this.teamLoading = true;
    this.chuchemonService.getTeam(forceRefresh)
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
        },
        error: (error) => {
          console.error('Error loading team:', error);
          this.team = [];
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

  loadStats(forceRefresh: boolean = false): void {
    this.chuchemonService.getAllChuchemons(forceRefresh)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.stats.total = response.length;
          this.stats.captured = response.filter((ch: any) => ch.captured).length;
        },
        error: (error) => console.error('Error loading chuchemons:', error)
      });
  }

  logout() {
    this.auth.logout();
  }

  openDetailsModal(chuchemon: Chuchemon): void {
    this.selectedChuchemonForDetails = chuchemon;
    this.showDetailsModal = true;
  }

  closeDetailsModal(): void {
    this.showDetailsModal = false;
    this.selectedChuchemonForDetails = null;
  }
}
