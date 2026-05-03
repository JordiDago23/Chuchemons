import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil, finalize } from 'rxjs/operators';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';
import { BattleService } from '../../core/services/battle.service';
import { LevelingPanelComponent } from '../../components/leveling-panel/leveling-panel.component';
import { InfectionsPanelComponent } from '../../components/infections-panel/infections-panel.component';
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

  private applyUserXp(u: any): void {
    this.stats.level  = u.level  ?? 0;
    this.stats.xp     = u.experience ?? 0;
    this.stats.xpMax  = u.experience_for_next_level ?? 100;
  }

  constructor(
    private auth: AuthService,
    private chuchemonService: ChuchemonService,
    private battleService: BattleService
  ) {}

  ngOnInit() {
    this.chuchemonService.stateChanges$
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.loadTeam(true);
        this.loadStats(true);
      });

    this.auth.me().pipe(takeUntil(this.destroy$)).subscribe({
      next: (data) => {
        this.user = data;
        this.applyUserXp(data);
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
            if (this.selectedChuchemonForDetails) {
              const updated = this.team.find(m => m.id === this.selectedChuchemonForDetails!.id);
              if (updated) this.selectedChuchemonForDetails = updated;
            }
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
        error: (err) => console.error('Error loading chuchemons:', err)
      });

    this.battleService.getOverview()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (r) => {
          this.stats.wins   = r.stats.victories;
          this.stats.losses = r.stats.defeats;
        }
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
