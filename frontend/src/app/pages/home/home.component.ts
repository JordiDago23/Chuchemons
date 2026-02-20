import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="container">
      <div class="header">
        <h1>🎮 Bienvenido a Chuchemons</h1>
        <button class="logout-btn" (click)="logout()">Cerrar sesión</button>
      </div>

      <div *ngIf="user" class="user-card">
        <p><strong>ID:</strong> {{ user.player_id }}</p>
        <p><strong>Nombre:</strong> {{ user.nombre }} {{ user.apellidos }}</p>
        <p><strong>Email:</strong> {{ user.email }}</p>
        <p *ngIf="user.is_admin" class="admin-badge">🤖 Administrador</p>
      </div>

      <div class="actions">
        <a routerLink="/profile" class="btn">👤 Mi Perfil</a>
      </div>
    </div>
  `,
  styles: [`
    .container { max-width: 600px; margin: 60px auto; padding: 2rem; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    h1 { margin: 0; }
    .logout-btn { padding: 0.5rem 1rem; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; }
    .user-card { background: #f9fafb; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #e5e7eb; }
    .user-card p { margin: 0.4rem 0; }
    .admin-badge { color: #7c3aed; font-weight: bold; }
    .actions { display: flex; gap: 1rem; }
    .btn { padding: 0.7rem 1.5rem; background: #4f46e5; color: white; border-radius: 4px; text-decoration: none; }
  `]
})
export class HomeComponent implements OnInit {
  user: any = null;

  constructor(private auth: AuthService) {}

  ngOnInit() {
    this.auth.me().subscribe({
      next: (data) => this.user = data,
      error: () => this.auth.logout()
    });
  }

  logout() {
    this.auth.logout();
  }
}