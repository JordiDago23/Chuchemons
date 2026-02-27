import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit {
  user: any = null;
  loading = true;
  error = '';

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

  team: any[] = []; // vacío para cuentas nuevas

  typeColors: Record<string, string> = {
    Tierra: '#b8860b',
    Aire:   '#48cae4',
    Fuego:  '#e63946',
    Agua:   '#457b9d',
    Planta: '#2a9d8f',
  };

  constructor(private auth: AuthService) {}

  ngOnInit() {
    this.auth.me().subscribe({
      next: (data) => {
        this.user = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.auth.logout();
      }
    });
  }

  logout() {
    this.auth.logout();
  }
}