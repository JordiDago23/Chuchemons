import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ConfirmDialogComponent } from '../dialogs/confirm-dialog.component';

@Component({
  selector: 'app-leveling-panel',
  standalone: true,
  imports: [CommonModule, FormsModule, ConfirmDialogComponent],
  templateUrl: './leveling-panel.component.html',
  styleUrls: ['./leveling-panel.component.css']
})
export class LevelingPanelComponent implements OnInit {
  chuchemons: any[] = [];
  selectedChuchemon: any = null;
  isLoading = false;
  errorMessage: string | null = null;
  actionMessage: string | null = null;
  xuxQtyToUse = 1;
  healQty = 1;
  showActionConfirm = false;
  confirmTitle = '';
  confirmMessage = '';
  private confirmAction: (() => void) | null = null;

  private readonly api = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

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

  addExperience(amount: number): void {
    if (!this.selectedChuchemon) return;
    this.http.post(`${this.api}/level/chuchemon/${this.selectedChuchemon.id}/add-experience/${amount}`, {}).subscribe({
      next: (res: any) => {
        this.actionMessage = res.level_up ? '⬆️ ¡El Xuxemon ha subido de nivel!' : `+${amount} XP añadidos`;
        this.loadChuchemons();
      },
      error: () => { this.errorMessage = 'Error añadiendo experiencia'; }
    });
  }

  useXuxForXp(): void {
    if (!this.selectedChuchemon || this.xuxQtyToUse < 1) return;
    if (this.selectedChuchemon.cannot_eat) {
      this.actionMessage = this.selectedChuchemon.cannot_eat_reason ?? 'Este Xuxemon no puede comer más Xuxes ahora mismo.';
      return;
    }
    if ((this.selectedChuchemon.xuxes_qty ?? 0) < this.xuxQtyToUse) {
      this.actionMessage = 'No tienes suficientes Xuxes para esa cantidad.';
      return;
    }

    this.openConfirmDialog(
      'Confirmar alimentación',
      `Vas a gastar ${this.xuxQtyToUse} Xuxes para dar ${this.xuxQtyToUse * 20} XP a ${this.selectedChuchemon.name}.`,
      () => this.executeUseXuxForXp()
    );
  }

  private executeUseXuxForXp(): void {
    this.http.post(`${this.api}/user/chuchemons/${this.selectedChuchemon.id}/use-xux`, { quantity: this.xuxQtyToUse }).subscribe({
      next: (res: any) => {
        this.actionMessage = res.level_up
          ? `🍬 ${this.xuxQtyToUse} Xux gastades → ⬆️ Nivell pujat!`
          : `🍬 ${this.xuxQtyToUse} Xux gastades → +${this.xuxQtyToUse * 20} XP`;
        this.loadChuchemons();
      },
      error: (err) => {
        this.actionMessage = err.error?.message ?? 'No tienes suficientes Xuxes';
      }
    });
  }

  healWithXux(): void {
    if (!this.selectedChuchemon || this.healQty < 1) return;
    if ((this.selectedChuchemon.xuxes_qty ?? 0) < this.healQty) {
      this.actionMessage = 'No tienes suficientes Xuxes para curar esa cantidad.';
      return;
    }

    this.openConfirmDialog(
      'Confirmar curación',
      `Vas a gastar hasta ${this.healQty} Xuxes para curar a ${this.selectedChuchemon.name} y recuperar hasta ${this.healQty * 20} PS.`,
      () => this.executeHealWithXux()
    );
  }

  private executeHealWithXux(): void {
    this.http.post(`${this.api}/user/chuchemons/${this.selectedChuchemon.id}/heal`, { quantity: this.healQty }).subscribe({
      next: (res: any) => {
        this.actionMessage = `❤️ Curat +${res.healed} PS (${res.current_hp}/${res.max_hp})`;
        this.loadChuchemons();
      },
      error: (err) => {
        this.actionMessage = err.error?.message ?? 'No se puede curar ahora';
      }
    });
  }

  getSizeLabel(size?: string): string {
    switch (size) {
      case 'Petit': return 'Pequeño';
      case 'Mitjà': return 'Mediano';
      case 'Gran': return 'Grande';
      default: return size ?? 'Pequeño';
    }
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

  get xpPreview(): number {
    return this.xuxQtyToUse * 20;
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
