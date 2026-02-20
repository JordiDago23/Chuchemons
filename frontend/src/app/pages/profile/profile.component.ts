import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="container">
      <div class="header">
        <a routerLink="/home" class="back">← Volver</a>
        <h2>👤 Mi Perfil</h2>
      </div>

      <div *ngIf="success" class="success">{{ success }}</div>
      <div *ngIf="error" class="error">{{ error }}</div>

      <form (ngSubmit)="onUpdate()">
        <label>Nombre</label>
        <input type="text" [(ngModel)]="form.nombre" name="nombre" />

        <label>Apellidos</label>
        <input type="text" [(ngModel)]="form.apellidos" name="apellidos" />

        <label>Email</label>
        <input type="email" [(ngModel)]="form.email" name="email" />

        <label>Nueva contraseña (dejar vacío para no cambiar)</label>
        <input type="password" [(ngModel)]="form.password" name="password" />

        <label>Repetir nueva contraseña</label>
        <input type="password" [(ngModel)]="form.password_confirmation" name="password_confirmation" />

        <button type="submit" [disabled]="loading">
          {{ loading ? 'Guardando...' : 'Guardar cambios' }}
        </button>
      </form>

      <hr />

      <div class="danger-zone">
        <h3>⚠️ Zona de peligro</h3>
        <p>Si te das de baja no podrás volver a entrar con este usuario.</p>
        <button class="delete-btn" (click)="confirmDelete()">Darme de baja</button>
      </div>

      <div *ngIf="showConfirm" class="overlay">
        <div class="dialog">
          <h3>¿Estás seguro?</h3>
          <p>Esta acción es irreversible. Tu cuenta será eliminada permanentemente.</p>
          <div class="dialog-actions">
            <button class="cancel-btn" (click)="showConfirm = false">Cancelar</button>
            <button class="confirm-btn" (click)="onDelete()">Sí, darme de baja</button>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .container { max-width: 500px; margin: 60px auto; padding: 2rem; }
    .header { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; }
    .back { text-decoration: none; color: #4f46e5; }
    label { display: block; margin-bottom: 0.3rem; font-weight: 500; font-size: 0.9rem; }
    input { display: block; width: 100%; padding: 0.6rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    button[type=submit] { width: 100%; padding: 0.7rem; background: #4f46e5; color: white; border: none; border-radius: 4px; cursor: pointer; }
    button:disabled { background: #aaa; }
    .success { background: #dcfce7; color: #166534; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; }
    .error { background: #fee2e2; color: #b91c1c; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; }
    hr { margin: 2rem 0; }
    .danger-zone { background: #fff7ed; padding: 1.5rem; border-radius: 8px; border: 1px solid #fed7aa; }
    .danger-zone h3 { margin-top: 0; color: #c2410c; }
    .delete-btn { padding: 0.6rem 1.2rem; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; }
    .overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 100; }
    .dialog { background: white; padding: 2rem; border-radius: 8px; max-width: 400px; width: 90%; }
    .dialog h3 { margin-top: 0; }
    .dialog-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }
    .cancel-btn { padding: 0.6rem 1.2rem; background: #e5e7eb; border: none; border-radius: 4px; cursor: pointer; }
    .confirm-btn { padding: 0.6rem 1.2rem; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; }
  `]
})
export class ProfileComponent implements OnInit {
  form: any = { nombre: '', apellidos: '', email: '', password: '', password_confirmation: '' };
  error = '';
  success = '';
  loading = false;
  showConfirm = false;

  constructor(private auth: AuthService) {}

  ngOnInit() {
    this.auth.me().subscribe({
      next: (user: any) => {
        this.form.nombre    = user.nombre;
        this.form.apellidos = user.apellidos;
        this.form.email     = user.email;
      }
    });
  }

  onUpdate() {
    this.error = '';
    this.success = '';
    this.loading = true;

    const data: any = {
      nombre:    this.form.nombre,
      apellidos: this.form.apellidos,
      email:     this.form.email,
    };

    if (this.form.password) {
      data.password = this.form.password;
      data.password_confirmation = this.form.password_confirmation;
    }

    this.auth.updateProfile(data).subscribe({
      next: () => {
        this.loading = false;
        this.success = 'Perfil actualizado correctamente';
        this.form.password = '';
        this.form.password_confirmation = '';
      },
      error: (err) => {
        this.loading = false;
        this.error = err.error?.message || 'Error al actualizar el perfil';
      }
    });
  }

  confirmDelete() {
    this.showConfirm = true;
  }

  onDelete() {
    this.auth.deleteAccount().subscribe();
  }
}