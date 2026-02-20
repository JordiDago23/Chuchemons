import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="container">
      <h2>Registro</h2>

      <div *ngIf="error" class="error">{{ error }}</div>

      <form (ngSubmit)="onSubmit()">
        <input type="text" placeholder="Nombre" [(ngModel)]="form.nombre" name="nombre" required />
        <input type="text" placeholder="Apellidos" [(ngModel)]="form.apellidos" name="apellidos" required />
        <input type="email" placeholder="Email" [(ngModel)]="form.email" name="email" required />
        <input type="password" placeholder="Contraseña" [(ngModel)]="form.password" name="password" required />
        <input type="password" placeholder="Repetir Contraseña" [(ngModel)]="form.password_confirmation" name="password_confirmation" required />
        <button type="submit" [disabled]="loading">
          {{ loading ? 'Registrando...' : 'Registrarse' }}
        </button>
      </form>

      <p>¿Ya tienes cuenta? <a routerLink="/login">Inicia sesión</a></p>
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
export class RegisterComponent {
  form = { nombre: '', apellidos: '', email: '', password: '', password_confirmation: '' };
  error = '';
  loading = false;

  constructor(private auth: AuthService, private router: Router) {}

  onSubmit() {
    this.error = '';
    this.loading = true;

    this.auth.register(this.form).subscribe({
      next: () => this.router.navigate(['/home']),
      error: (err: any) => {
        this.loading = false;
        this.error = err.error?.message || 'Error al registrarse';
      }
    });
  }
}