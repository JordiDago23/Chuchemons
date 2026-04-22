import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../core/services/auth.service';
import { SidebarNavComponent } from '../../components/sidebar-nav/sidebar-nav.component';

@Component({
  selector: 'app-admin',
  standalone: true,
  imports: [CommonModule, FormsModule, SidebarNavComponent],
  templateUrl: './admin.component.html',
  styleUrls: ['./admin.component.css']
})
export class AdminComponent implements OnInit {
  private apiUrl = 'http://localhost:8000/api';

  user: any = null;
  activeTab: 'jugadors' | 'recursos' | 'configuracio' | 'horaris' = 'jugadors';

  // â”€â”€ Stats â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  stats = { jugadors: 0, totalUsuaris: 0, xuemons: 0 };

  // â”€â”€ Users list â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  users: any[] = [];
  usersLoading = false;

  // â”€â”€ AÃ±adir Xuxes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  xuxPlayerId: number | null = null;
  xuxItemId: number = 1;
  xuxQty = 10;
  xuxLoading = false;
  xuxFeedback = '';
  xuxFeedbackType: 'success' | 'error' | '' = '';

  readonly xuxTypes = [
    { id: 1, name: 'Xux de Maduixa', emoji: 'ðŸ“' },
    { id: 2, name: 'Xux de Llimona', emoji: 'ðŸ‹' },
    { id: 3, name: 'Xux de Cola', emoji: 'ðŸ¥¤' },
    { id: 4, name: 'Xux Exp', emoji: 'â­' },
  ];

  // â”€â”€ Afegir Xuxemon Aleatori â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  aleatorioPlayerId: number | null = null;
  aleatorioLoading = false;
  aleatorioFeedback = '';
  aleatorioFeedbackType: 'success' | 'error' | '' = '';

  // â”€â”€ AÃ±adir Vacunas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  vacunaPlayerId: number | null = null;
  vacunaId: number = 1;
  vacunaQty = 1;
  vacunaLoading = false;
  vacunaFeedback = '';
  vacunaFeedbackType: 'success' | 'error' | '' = '';

  readonly vacunaTypes = [
    { id: 1, name: 'Xocolatina', emoji: 'ðŸ«', desc: 'Cura BajÃ³n de azÃºcar' },
    { id: 2, name: 'Xal de fruits', emoji: 'ðŸ¬', desc: 'Cura AtracÃ³n' },
    { id: 3, name: 'Insulina', emoji: 'ðŸ’‰', desc: 'Cura todas las enfermedades' },
    { id: 4, name: 'Fruita fresca', emoji: 'ðŸŽ', desc: 'Cura Sobredosis de sucre' },
  ];

  // â”€â”€ ConfiguraciÃ³ â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  cfg = { xuxPetitMitja: 3, xuxMitjaGran: 5 };
  cfgLoading = false;
  cfgFeedback = '';
  cfgFeedbackType: 'success' | 'error' | '' = '';

  taxaLoading = false;
  taxaFeedback = '';
  taxaFeedbackType: 'success' | 'error' | '' = '';
  diseases: { id: number; name: string; infection_rate: number }[] = [];

  saveConfig() {
    this.cfgLoading = true;
    this.cfgFeedback = '';
    this.http.put<any>(`${this.apiUrl}/admin/settings/config`, {
      xux_petit_mitja: this.cfg.xuxPetitMitja,
      xux_mitja_gran: this.cfg.xuxMitjaGran,
    }).subscribe({
      next: (res) => {
        this.cfg.xuxPetitMitja = res.settings.xux_petit_mitja;
        this.cfg.xuxMitjaGran = res.settings.xux_mitja_gran;
        this.cfgFeedback = res.message;
        this.cfgFeedbackType = 'success';
        this.cfgLoading = false;
      },
      error: (err) => {
        this.cfgFeedback = err.error?.message || 'Error guardando configuraciÃ³n.';
        this.cfgFeedbackType = 'error';
        this.cfgLoading = false;
      }
    });
  }

  saveTaxa() {
    this.taxaLoading = true;
    this.taxaFeedback = '';
    this.http.put<any>(`${this.apiUrl}/admin/settings/infection-rate`, {
      diseases: this.diseases.map(d => ({ id: d.id, infection_rate: d.infection_rate })),
    }).subscribe({
      next: (res) => {
        this.diseases = res.settings.diseases;
        this.taxaFeedback = res.message;
        this.taxaFeedbackType = 'success';
        this.taxaLoading = false;
      },
      error: (err) => {
        this.taxaFeedback = err.error?.message || 'Error actualizando las tasas de infecciÃ³n.';
        this.taxaFeedbackType = 'error';
        this.taxaLoading = false;
      }
    });
  }

  // â”€â”€ Horaris â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
    this.http.put<any>(`${this.apiUrl}/admin/settings/schedules/xux`, {
      hour: this.horariXuxes.hora,
      quantity: this.horariXuxes.quantitat,
    }).subscribe({
      next: (res) => {
        this.horariXuxes.hora = res.settings.daily_xux_hour;
        this.horariXuxes.quantitat = res.settings.daily_xux_quantity;
        this.horariXuxesFeedback = res.message;
        this.horariXuxesFeedbackType = 'success';
        this.horariXuxesLoading = false;
      },
      error: (err) => {
        this.horariXuxesFeedback = err.error?.message || 'Error guardando horario de Xuxes.';
        this.horariXuxesFeedbackType = 'error';
        this.horariXuxesLoading = false;
      }
    });
  }

  saveHorariXuxemon() {
    this.horariXuxemoLoading = true;
    this.horariXuxemoFeedback = '';
    this.http.put<any>(`${this.apiUrl}/admin/settings/schedules/chuchemon`, {
      hour: this.horariXuxemon.hora,
    }).subscribe({
      next: (res) => {
        this.horariXuxemon.hora = res.settings.daily_chuchemon_hour;
        this.horariXuxemoFeedback = res.message;
        this.horariXuxemoFeedbackType = 'success';
        this.horariXuxemoLoading = false;
      },
      error: (err) => {
        this.horariXuxemoFeedback = err.error?.message || 'Error guardando horario de Xuxemon.';
        this.horariXuxemoFeedbackType = 'error';
        this.horariXuxemoLoading = false;
      }
    });
  }

  constructor(private auth: AuthService, private router: Router, private http: HttpClient) {}

  ngOnInit() {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
      if (!this.user.is_admin) { this.router.navigate(['/home']); return; }
      this.loadStats();
      this.loadSettings();
      this.loadUsers();
      return;
    }
    this.auth.me().subscribe({
      next: (data) => {
        this.user = data;
        if (!this.user.is_admin) { this.router.navigate(['/home']); return; }
        this.loadStats();
        this.loadSettings();
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
      }
    });
  }

  loadSettings() {
    this.http.get<any>(`${this.apiUrl}/admin/settings`).subscribe({
      next: (data) => {
        this.cfg.xuxPetitMitja = data.config.xux_petit_mitja;
        this.cfg.xuxMitjaGran = data.config.xux_mitja_gran;
        this.diseases = data.infection.diseases;
        this.horariXuxes.hora = data.schedules.daily_xux_hour;
        this.horariXuxes.quantitat = data.schedules.daily_xux_quantity;
        this.horariXuxemon.hora = data.schedules.daily_chuchemon_hour;
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
    this.http.post<any>(`${this.apiUrl}/admin/users/${this.xuxPlayerId}/add-item`, {
      item_id: this.xuxItemId,
      quantity: this.xuxQty,
    }).subscribe({
      next: (res) => {
        this.xuxFeedback     = res.message;
        this.xuxFeedbackType = 'success';
        this.xuxLoading      = false;
        this.loadUsers();
      },
      error: (err) => {
        this.xuxFeedback     = err.error?.message || 'Error al aÃ±adir Xuxes.';
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
        this.aleatorioFeedback     = err.error?.message || 'Error al aÃ±adir el Xuxemon.';
        this.aleatorioFeedbackType = 'error';
        this.aleatorioLoading      = false;
      }
    });
  }

  addVacuna() {
    if (!this.vacunaPlayerId) return;
    this.vacunaLoading = true;
    this.vacunaFeedback = '';
    this.http.post<any>(`${this.apiUrl}/admin/users/${this.vacunaPlayerId}/add-vaccine`, {
      vaccine_id: this.vacunaId,
      quantity: this.vacunaQty,
    }).subscribe({
      next: (res) => {
        this.vacunaFeedback     = res.message;
        this.vacunaFeedbackType = 'success';
        this.vacunaLoading      = false;
        this.loadUsers();
      },
      error: (err) => {
        this.vacunaFeedback     = err.error?.message || 'Error al aÃ±adir vacuna.';
        this.vacunaFeedbackType = 'error';
        this.vacunaLoading      = false;
      }
    });
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


