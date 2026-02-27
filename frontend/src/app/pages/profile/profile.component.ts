import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.css']
})
export class ProfileComponent implements OnInit {
  user: any = null;
  activeTab: 'info' | 'stats' | 'logros' = 'info';
  editMode = false;

  form: any = { nombre: '', apellidos: '', email: '', bio: '', password: '', password_confirmation: '' };
  error = '';
  success = '';
  loading = false;
  showConfirm = false;

  // Stats mock — por defecto en 0 para cuentas nuevas
  stats = { level: 0, xp: 0, xpMax: 100, wins: 0, losses: 0, streak: 0, captured: 0 };

  typeStats = [
    { type: 'Tipo agua',   count: 0, color: '#457b9d' },
    { type: 'Tipo Tierra', count: 0, color: '#b8860b' },
    { type: 'Tipo airel',  count: 0, color: '#48cae4' },
  ];

  logros = [
    { icon: '🏆', title: 'Primer Xuxemon',       desc: 'Captura el primer Xuxemon',      status: 'locked',   progress: null },
    { icon: '🔥', title: 'Primera Victoria',      desc: 'Gana tu primera partida',        status: 'locked',   progress: null },
    { icon: '🎯', title: 'Coleccionista',          desc: 'Captura 50 Xuxemons diferentes', status: 'locked',   progress: null },
    { icon: '🏆', title: 'Maestro del Combate',   desc: 'Consigue 100 victorias',         status: 'locked',   progress: null },
    { icon: '📖', title: 'Xuxedex Completada',    desc: 'Captura todos los Xuxemons',     status: 'locked',   progress: null },
    { icon: '↗',  title: 'Invencible',            desc: 'Gana 10 partidas seguidas',      status: 'locked',   progress: null },
  ];

  get winRatio(): string {
    const t = this.stats.wins + this.stats.losses;
    return t > 0 ? ((this.stats.wins / t) * 100).toFixed(1) + '%' : '0%';
  }

  get xpPercent(): number {
    return Math.round((this.stats.xp / this.stats.xpMax) * 100);
  }

  get memberSince(): string {
    if (!this.user?.created_at) return '-';
    const d = new Date(this.user.created_at);
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  constructor(private auth: AuthService) {}

  private fillForm(u: any) {
    this.form.nombre    = u.nombre;
    this.form.apellidos = u.apellidos;
    this.form.email     = u.email;
    this.form.bio       = u.bio || '';
  }

  ngOnInit() {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
      this.fillForm(cached);
      return;
    }
    this.auth.me().subscribe({
      next: (u: any) => {
        this.user = u;
        this.fillForm(u);
      }
    });
  }

  onUpdate() {
    this.error = '';
    this.success = '';
    this.loading = true;
    const data: any = { nombre: this.form.nombre, apellidos: this.form.apellidos, email: this.form.email, bio: this.form.bio };
    if (this.form.password) {
      data.password = this.form.password;
      data.password_confirmation = this.form.password_confirmation;
    }
    this.auth.updateProfile(data).subscribe({
      next: (u: any) => {
        this.loading = false;
        this.success = 'Perfil actualizado correctamente';
        this.user = u.user || this.user;
        this.editMode = false;
        this.form.password = '';
        this.form.password_confirmation = '';
      },
      error: (err: any) => {
        this.loading = false;
        this.error = err.error?.message || 'Error al actualizar el perfil';
      }
    });
  }

  onDelete() {
    this.auth.deleteAccount().subscribe();
  }

  logout() {
    this.auth.logout();
  }
}