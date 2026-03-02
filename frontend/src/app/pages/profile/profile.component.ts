import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.css']
})
export class ProfileComponent implements OnInit {
  user: any = null;
  activeTab: 'info' | 'stats' | 'logros' = 'info';
  editMode = false;

  editForm: FormGroup;
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

  // Getters per controls del formulari
  get nombre()    { return this.editForm.get('nombre')!; }
  get apellidos() { return this.editForm.get('apellidos')!; }
  get emailCtrl() { return this.editForm.get('email')!; }
  get password()  { return this.editForm.get('password')!; }
  get bioCtrl()   { return this.editForm.get('bio')!; }

  constructor(private fb: FormBuilder, private auth: AuthService) {
    this.editForm = this.fb.group({
      nombre:                ['', [Validators.required]],
      apellidos:             ['', [Validators.required]],
      email:                 ['', [Validators.required, Validators.email]],
      bio:                   [''],
      password:              ['', [Validators.minLength(6)]],
      password_confirmation: ['']
    });
  }

  private fillForm(u: any) {
    this.editForm.patchValue({
      nombre:    u.nombre,
      apellidos: u.apellidos,
      email:     u.email,
      bio:       u.bio || '',
      password:  '',
      password_confirmation: ''
    });
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
    if (this.editForm.invalid) {
      this.editForm.markAllAsTouched();
      return;
    }
    this.loading = true;
    const v = this.editForm.value;
    const data: any = { nombre: v.nombre, apellidos: v.apellidos, email: v.email, bio: v.bio };
    if (v.password) {
      data.password = v.password;
      data.password_confirmation = v.password_confirmation;
    }
    this.auth.updateProfile(data).subscribe({
      next: (u: any) => {
        this.loading = false;
        this.success = 'Perfil actualizado correctamente';
        this.user = u.user || this.user;
        this.editMode = false;
        this.editForm.patchValue({ password: '', password_confirmation: '' });
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
