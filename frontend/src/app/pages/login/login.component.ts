import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="container">
      <h2>Iniciar Sesión</h2>

      <div *ngIf="error" class="error">{{ error }}</div>

      <form (ngSubmit)="onSubmit()">
        <input type="text" placeholder="ID de jugador (ej: #Jordi1234)" [(ngModel)]="form.player_id" name="player_id" required />
        <input type="password" placeholder="Contraseña" [(ngModel)]="form.password" name="password" required />
        <button type="submit" [disabled]="loading">
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>

      <p>¿No tienes cuenta? <a routerLink="/register">Regístrate</a></p>
    </div>
  `,
  styles: [`
    .container { max-width: 400px; margin: 80px auto; padding: 2rem; border: 1px solid #ddd; border-radius: 8px; }
    h2 { text-align: center; margin-bottom: 1.5rem; }
    input { display: block; width: 100%; padding: 0.6rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    button { width: 100%; padding: 0.7rem; background: #4f46e5; color: white; border: none; border-radius: 4px; cursor: pointer; }
    button:disabled { background: #aaa; }
    .error { background: #fee2e2; color: #b91c1c; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; }
    p { text-align: center; margin-top: 1rem; }
  `]
})
export class LoginComponent {
  form = { player_id: '', password: '' };
  error = '';
  loading = false;

  constructor(private auth: AuthService, private router: Router) {}

  onSubmit() {
    this.error = '';
    this.loading = true;

    this.auth.login(this.form).subscribe({
      next: () => this.router.navigate(['/home']),
      error: (err) => {
        this.loading = false;
        this.error = err.error?.message || 'Credenciales incorrectas';
      }
    });
  }
}