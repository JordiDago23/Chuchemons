import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../core/services/auth.service';
import { MainLayoutComponent } from '../../components/main-layout/main-layout.component';
import { DailyRewardsComponent } from '../../components/daily-rewards/daily-rewards.component';

@Component({
  selector: 'app-recompensas',
  standalone: true,
  imports: [CommonModule, MainLayoutComponent, DailyRewardsComponent],
  template: `
    <app-main-layout
      [user]="user"
      [brandSub]="'Recompensas'"
      [showAdmin]="!!user?.is_admin"
      (logoutClick)="logout()"
    >
      <section class="reco-hero">
        <h1>Recompensas Diarias</h1>
        <p>Recoge tu recompensa cada día y expande tu colección.</p>
      </section>
      <app-daily-rewards></app-daily-rewards>
    </app-main-layout>
  `,
  styles: [`
    .reco-hero {
      background: linear-gradient(135deg, #f97316, #fbbf24);
      color: #fff;
      border-radius: 1rem;
      padding: 1.15rem 1.25rem;
      margin-bottom: 0;
    }
    .reco-hero h1 { margin: 0 0 0.2rem; font-size: 1.6rem; font-weight: 800; }
    .reco-hero p  { margin: 0; font-size: 0.9rem; opacity: 0.9; }
  `]
})
export class RecompensasComponent implements OnInit {
  user: any = null;

  constructor(private auth: AuthService) {}

  ngOnInit(): void {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
    } else {
      this.auth.me().subscribe({ next: (u) => { this.user = u; } });
    }
  }

  logout(): void {
    this.auth.logout();
  }
}
