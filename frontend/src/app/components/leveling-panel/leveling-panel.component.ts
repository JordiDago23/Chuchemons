import { Component, Input, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ConfirmDialogComponent } from '../dialogs/confirm-dialog.component';
import { ChuchemonService } from '../../services/chuchemon.service';

@Component({
  selector: 'app-leveling-panel',
  standalone: true,
  imports: [CommonModule, FormsModule, ConfirmDialogComponent],
  templateUrl: './leveling-panel.component.html',
  styleUrls: ['./leveling-panel.component.css']
})
export class LevelingPanelComponent implements OnInit {
  @Input() compact = false;
  chuchemons: any[] = [];
  selectedChuchemon: any = null;
  isLoading = false;
  errorMessage: string | null = null;
  actionMessage: string | null = null;
  healQty = 1;
  showActionConfirm = false;
  confirmTitle = '';
  confirmMessage = '';
  private confirmAction: (() => void) | null = null;

  private readonly api = 'http://localhost:8000/api';

  constructor(
    private http: HttpClient,
    private chuchemonService: ChuchemonService
  ) {}

  ngOnInit(): void {
    this.loadChuchemons();
  }

  loadChuchemons(): void {
    this.isLoading = true;
    this.http.get<any[]>(`${this.api}/level/chuchemons`).subscribe({
      next: (data) => {
        this.chuchemons = data;
        if (data.length > 0) {
          const prevId = this.selectedChuchemon?.id;
          this.selectedChuchemon = data.find(c => c.id === prevId) ?? data[0];
        }
        this.isLoading = false;
      },
      error: () => {
        this.errorMessage = 'Error cargando los Xuxemons';
        this.isLoading = false;
      }
    });
  }

  selectChuchemon(c: any): void {
    this.selectedChuchemon = c;
    this.actionMessage = null;
  }

  evolve(): void {
    if (!this.selectedChuchemon) return;
    const cost = this.getEvolveCost();
    if ((this.selectedChuchemon.xuxes_exp ?? 0) < cost) {
      this.actionMessage = `Necesitas ${cost} Xux Exp para evolucionar. Tienes ${this.selectedChuchemon.xuxes_exp ?? 0}.`;
      return;
    }
    this.openConfirmDialog(
      'Confirmar evolución',
      `Gastarás ${cost} Xux Exp para evolucionar ${this.selectedChuchemon.name} a ${this.getNextSizeLabel()}.`,
      () => this.executeEvolve()
    );
  }

  private executeEvolve(): void {
    this.http.post<any>(`${this.api}/user/chuchemons/${this.selectedChuchemon.id}/evolve`, {}).subscribe({
      next: (res) => {
        this.actionMessage = res.message ?? '¡Xuxemon evolucionado!';
        this.loadChuchemons();
        this.chuchemonService.notifyChuchemonStateChanged();
      },
      error: (err) => {
        this.actionMessage = err.error?.message ?? 'Error al evolucionar';
      }
    });
  }

  getEvolveCost(): number {
    const mida = this.selectedChuchemon?.current_mida;
    let base = 0;
    if (mida === 'Petit') base = 3;
    else if (mida === 'Mitjà') base = 5;
    return base + (this.selectedChuchemon?.evolve_cost_extra ?? 0);
  }

  getNextSizeLabel(): string {
    const mida = this.selectedChuchemon?.current_mida;
    if (mida === 'Petit') return 'Mitjà';
    if (mida === 'Mitjà') return 'Gran';
    return 'Màxim';
  }

  getNextMida(): string {
    const mida = this.selectedChuchemon?.current_mida;
    if (mida === 'Petit') return 'Mitjà';
    if (mida === 'Mitjà') return 'Gran';
    return 'Gran';
  }

  healWithXux(): void {
    if (!this.selectedChuchemon || this.healQty < 1) return;
    if ((this.selectedChuchemon.xuxes_maduixa ?? 0) < this.healQty) {
      this.actionMessage = 'No tienes suficientes Xux de Maduixa para curar.';
      return;
    }

    this.openConfirmDialog(
      'Confirmar curación',
      `Vas a gastar hasta ${this.healQty} Xux de Maduixa para curar a ${this.selectedChuchemon.name}, recuperar hasta ${this.healQty * 20} PS y ganar ${this.healQty * 50} XP.`,
      () => this.executeHealWithXux()
    );
  }

  private executeHealWithXux(): void {
    this.http.post(`${this.api}/user/chuchemons/${this.selectedChuchemon.id}/heal`, { quantity: this.healQty }).subscribe({
      next: (res: any) => {
        this.actionMessage = `❤️ Curado +${res.healed} PS (${res.current_hp}/${res.max_hp}) · +${res.xp_gained ?? 0} XP`;
        this.loadChuchemons();
        this.chuchemonService.notifyChuchemonStateChanged();
      },
      error: (err) => {
        this.actionMessage = err.error?.message ?? 'No se puede curar ahora';
      }
    });
  }

  getSizeLabel(size?: string): string {
    switch (size) {
      case 'Petit': return 'Petit';
      case 'Mitjà': return 'Mitjà';
      case 'Gran': return 'Gran';
      default: return size ?? 'Petit';
    }
  }

  statsForSize(size: string): { hp: number; atk: number; def: number; speed: number } {
    const c = this.selectedChuchemon;
    if (!c) return { hp: 0, atk: 0, def: 0, speed: 0 };
    const baseAtk = c.attack ?? 50;
    const baseDef = c.defense ?? 50;
    const baseSpeed = c.speed ?? 50;
    const level = c.level ?? 1;
    const attackBoost = 1 + ((c.attack_boost ?? 0) / 100);
    const defenseBoost = 1 + ((c.defense_boost ?? 0) / 100);
    let mult = 1;
    if (size === 'Mitjà') mult = 1.02;
    if (size === 'Gran') mult = 1.071;
    let hpBonus = 0;
    if (size === 'Mitjà') hpBonus = 25;
    if (size === 'Gran') hpBonus = 50;

    return {
      hp: Math.round(50 + baseDef + (level * 5) + hpBonus),
      atk: Math.round((baseAtk * mult) * attackBoost),
      def: Math.round((baseDef * mult) * defenseBoost),
      speed: Math.round(baseSpeed * mult),
    };
  }

  get hpPercent(): number {
    if (!this.selectedChuchemon) return 100;
    const c = this.selectedChuchemon;
    return c.max_hp > 0 ? Math.round((c.current_hp / c.max_hp) * 100) : 100;
  }

  get hpColor(): string {
    const p = this.hpPercent;
    if (p > 60) return '#4caf50';
    if (p > 25) return '#ff9800';
    return '#f44336';
  }

  get healPreview(): number {
    return this.healQty * 20;
  }

  get selectedInfections(): any[] {
    return this.selectedChuchemon?.active_infections ?? [];
  }

  get hasSelectedInfections(): boolean {
    return this.selectedInfections.length > 0;
  }

  trackInfection(_: number, infection: any): string {
    return `${infection.id}-${infection.name}`;
  }

  onConfirmAction(): void {
    const action = this.confirmAction;
    this.closeConfirmDialog();
    action?.();
  }

  onCancelAction(): void {
    this.closeConfirmDialog();
  }

  private openConfirmDialog(title: string, message: string, action: () => void): void {
    this.confirmTitle = title;
    this.confirmMessage = message;
    this.confirmAction = action;
    this.showActionConfirm = true;
  }

  private closeConfirmDialog(): void {
    this.showActionConfirm = false;
    this.confirmTitle = '';
    this.confirmMessage = '';
    this.confirmAction = null;
  }
}
