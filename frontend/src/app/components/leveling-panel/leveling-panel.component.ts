import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

@Component({
  selector: 'app-leveling-panel',
  standalone: true,
  imports: [CommonModule, FormsModule],
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
}
