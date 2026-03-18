import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-admin',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './admin.component.html',
  styleUrls: ['./admin.component.css']
})
export class AdminComponent implements OnInit {
  private apiUrl = 'http://localhost:8000/api';

  user: any = null;
  activeTab: 'jugadors' | 'recursos' | 'configuracio' | 'horaris' = 'jugadors';

  // ── Stats ────────────────────────────────────────────────────────────────
  stats = { jugadors: 0, totalUsuaris: 0, xuemons: 0, taxaInfeccio: 0 };

  // ── Users list ───────────────────────────────────────────────────────────
  users: any[] = [];
  usersLoading = false;

  // ── Afegir Xuxes ─────────────────────────────────────────────────────────
  xuxPlayerId: number | null = null;
  xuxQty = 10;
  xuxLoading = false;
  xuxFeedback = '';
  xuxFeedbackType: 'success' | 'error' | '' = '';

  // ── Afegir Xuxemon Aleatori ───────────────────────────────────────────────
  aleatorioPlayerId: number | null = null;
  aleatorioLoading = false;
  aleatorioFeedback = '';
  aleatorioFeedbackType: 'success' | 'error' | '' = '';

  // ── Afegir Vacunes ────────────────────────────────────────────────────────
  vacunaPlayerId: number | null = null;
  vacunaType = 'Vacuna Mareo';
  vacunaLoading = false;
  vacunaFeedback = '';
  vacunaFeedbackType: 'success' | 'error' | '' = '';

  readonly vacunaTypes = ['Vacuna Mareo', 'Vacuna Atracón', 'Insulina'];

  // ── Configuració ─────────────────────────────────────────────────────────
  cfg = { xuxPetitMitja: 3, xuxMitjaGran: 5 };
  cfgLoading = false;
  cfgFeedback = '';
  cfgFeedbackType: 'success' | 'error' | '' = '';

  taxaInfeccioEdit = 12;
  taxaLoading = false;
  taxaFeedback = '';
  taxaFeedbackType: 'success' | 'error' | '' = '';

  saveConfig() {
    this.cfgLoading = true;
    this.cfgFeedback = '';
    setTimeout(() => {
      this.stats.xuemons = this.stats.xuemons; // placeholder — no backend endpoint yet
      this.cfgFeedback = 'Configuración guardada correctamente.';
      this.cfgFeedbackType = 'success';
      this.cfgLoading = false;
    }, 600);
  }

  saveTaxa() {
    this.taxaLoading = true;
    this.taxaFeedback = '';
    setTimeout(() => {
      this.stats.taxaInfeccio = this.taxaInfeccioEdit;
      this.taxaFeedback = `Tasa de infección actualizada al ${this.taxaInfeccioEdit}%.`;
      this.taxaFeedbackType = 'success';
      this.taxaLoading = false;
    }, 600);
  }

  // ── Horaris ────────────────────────────────────────────────────────────────
  horariXuxes    = { hora: '06:00', quantitat: 10 };
  horariXuxemon  = { hora: '08:00' };
  horariXuxesLoading   = false;
  horariXuxemoLoading  = false;
  horariXuxesFeedback  = '';
  horariXuxemoFeedback = '';
  horariXuxesFeedbackType:  'success' | 'error' | '' = '';
  horariXuxemoFeedbackType: 'success' | 'error' | '' = '';

  saveHorariXuxes() {
    this.horariXuxesLoading = true;
    this.horariXuxesFeedback = '';
    setTimeout(() => {
      this.horariXuxesFeedback = `Horario de Xuxes guardado: ${this.horariXuxes.hora} · ${this.horariXuxes.quantitat} Xuxes/día.`;
      this.horariXuxesFeedbackType = 'success';
      this.horariXuxesLoading = false;
    }, 600);
  }

  saveHorariXuxemon() {
    this.horariXuxemoLoading = true;
    this.horariXuxemoFeedback = '';
    setTimeout(() => {
      this.horariXuxemoFeedback = `Horario de Xuxemon guardado: ${this.horariXuxemon.hora} cada día.`;
      this.horariXuxemoFeedbackType = 'success';
      this.horariXuxemoLoading = false;
    }, 600);
  }

  constructor(private auth: AuthService, private router: Router, private http: HttpClient) {}

  ngOnInit() {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
      if (!this.user.is_admin) { this.router.navigate(['/home']); return; }
      this.loadStats();
      this.loadUsers();
      return;
    }
    this.auth.me().subscribe({
      next: (data) => {
        this.user = data;
        if (!this.user.is_admin) { this.router.navigate(['/home']); return; }
        this.loadStats();
        this.loadUsers();
      },
      error: () => this.router.navigate(['/login'])
    });
  }

  loadStats() {
    this.http.get<any>(`${this.apiUrl}/admin/stats`).subscribe({
      next: (data) => {
        this.stats.jugadors      = data.jugadors;
        this.stats.totalUsuaris  = data.total_usuaris;
        this.stats.xuemons       = data.xuemons;
        this.stats.taxaInfeccio  = data.taxa_infeccio;
      }
    });
  }

  loadUsers() {
    this.usersLoading = true;
    this.http.get<any>(`${this.apiUrl}/admin/users`).subscribe({
      next: (data) => { this.users = data.users; this.usersLoading = false; },
      error: ()     => { this.usersLoading = false; }
    });
  }

  get nonAdminUsers() {
    return this.users.filter(u => !u.is_admin);
  }

  addXux() {
    if (!this.xuxPlayerId || this.xuxQty < 1) return;
    this.xuxLoading = true;
    this.xuxFeedback = '';
    this.http.post<any>(`${this.apiUrl}/admin/users/${this.xuxPlayerId}/add-xux`, { quantity: this.xuxQty }).subscribe({
      next: (res) => {
        this.xuxFeedback     = res.message;
        this.xuxFeedbackType = 'success';
        this.xuxLoading      = false;
        this.loadUsers();
      },
      error: (err) => {
        this.xuxFeedback     = err.error?.message || 'Error al añadir Xuxes.';
        this.xuxFeedbackType = 'error';
        this.xuxLoading      = false;
      }
    });
  }

  addRandomChuchemon() {
    if (!this.aleatorioPlayerId) return;
    this.aleatorioLoading = true;
    this.aleatorioFeedback = '';
    this.http.post<any>(`${this.apiUrl}/admin/users/${this.aleatorioPlayerId}/add-chuchemon`, {}).subscribe({
      next: (res) => {
        this.aleatorioFeedback     = res.message;
        this.aleatorioFeedbackType = 'success';
        this.aleatorioLoading      = false;
        this.loadUsers();
      },
      error: (err) => {
        this.aleatorioFeedback     = err.error?.message || 'Error al añadir el Xuxemon.';
        this.aleatorioFeedbackType = 'error';
        this.aleatorioLoading      = false;
      }
    });
  }

  addVacuna() {
    if (!this.vacunaPlayerId) return;
    this.vacunaLoading = true;
    this.vacunaFeedback = '';
    // Vacunes are not yet stored in DB — show a placeholder response
    setTimeout(() => {
      const player = this.users.find(u => u.id === this.vacunaPlayerId);
      this.vacunaFeedback     = `Se ha añadido 1 ${this.vacunaType} a la mochila de ${player?.player_id ?? 'jugador'}.`;
      this.vacunaFeedbackType = 'success';
      this.vacunaLoading      = false;
    }, 600);
  }

  avatarColor(playerId: string): string {
    const colors = ['#e63946','#457b9d','#2a9d8f','#7c3aed','#f4a611','#48cae4','#b8860b'];
    const idx = (playerId?.charCodeAt(0) ?? 0) % colors.length;
    return colors[idx];
  }

  logout() {
    this.auth.logout();
  }
}

