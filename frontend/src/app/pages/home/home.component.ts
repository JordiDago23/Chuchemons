import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';

interface Chuchemon {
  id: number;
  name: string;
  element: string;
  image: string;
}

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit, OnDestroy {
  user: any = null;
  loading = true;
  error = '';
  teamLoading = false;
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
    Tierra: '#b8860b',
    Aire:   '#48cae4',
    Agua:   '#457b9d',
  };

  constructor(
    private auth: AuthService,
    private chuchemonService: ChuchemonService
  ) {}

  ngOnInit() {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
      this.loading = false;
      this.loadTeam();
      return;
    }
    this.auth.me().subscribe({
      next: (data) => {
        this.user = data;
        this.loading = false;
        this.loadTeam();
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
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.team && Array.isArray(response.team)) {
            this.team = response.team.filter(t => t !== null);
          } else {
            this.team = [];
          }
          this.teamLoading = false;
        },
        error: (error) => {
          console.error('Error loading team:', error);
          this.team = [];
          this.teamLoading = false;
        }
      });
  }

  logout() {
    this.auth.logout();
  }
}