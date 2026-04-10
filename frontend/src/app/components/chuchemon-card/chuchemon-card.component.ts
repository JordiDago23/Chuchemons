import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-chuchemon-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './chuchemon-card.component.html',
  styleUrls: ['./chuchemon-card.component.css']
})
export class ChuchemonCardComponent {
  @Input() chuchemon: any = null;
  @Input() locked = false;
  @Input() showCaptureBtn = false;
  @Input() showDetailsBtn = false;
  @Input() showTeamBadge = false;
  @Input() showEvolveBtn = false;

  @Output() capture  = new EventEmitter<number>();
  @Output() details  = new EventEmitter<number>();
  @Output() evolve  = new EventEmitter<number>();
  @Output() cardClick = new EventEmitter<any>();
  private normalizeElement(element?: string | null): 'Terra' | 'Aire' | 'Aigua' | '' {
    switch (element) {
      case 'Terra':
      case 'Tierra':
        return 'Terra';
      case 'Aigua':
      case 'Agua':
        return 'Aigua';
      case 'Aire':
        return 'Aire';
      default:
        return '';
    }
  }

  get sizeBadge(): string {
    const currentMida = this.chuchemon?.current_mida;
    if (currentMida === 'Petit') return 'Pequeño';
    if (currentMida === 'Mitjà') return 'Mediano';
    if (currentMida === 'Gran') return 'Grande';

    const count = this.chuchemon?.count ?? 1;
    if (count >= 5) return 'Grande';
    if (count >= 3) return 'Mediano';
    return 'Pequeño';
  }

  getElementLabel(element?: string | null): string {
    switch (this.normalizeElement(element)) {
      case 'Aigua': return 'Agua';
      case 'Terra': return 'Tierra';
      case 'Aire': return 'Aire';
      default: return element ?? 'Desconocido';
    }
  }

  isWaterType(): boolean {
    return this.normalizeElement(this.chuchemon?.element) === 'Aigua';
  }

  isEarthType(): boolean {
    return this.normalizeElement(this.chuchemon?.element) === 'Terra';
  }

  isAirType(): boolean {
    return this.normalizeElement(this.chuchemon?.element) === 'Aire';
  }

  get quantityLabel(): string {
    if (this.locked) return 'x?';
    return `x${this.chuchemon?.count ?? 1}`;
  }

  get hasActiveInfections(): boolean {
    return !this.locked && (this.chuchemon?.has_active_infections || (this.chuchemon?.active_infections?.length ?? 0) > 0);
  }

  get hpValueLabel(): string {
    const currentHp = this.chuchemon?.current_hp;
    const maxHp = this.chuchemon?.max_hp;

    if (typeof currentHp !== 'number' || typeof maxHp !== 'number') {
      return '—/—';
    }

    return `${currentHp}/${maxHp}`;
  }

  get hpPercent(): number {
    const currentHp = this.chuchemon?.current_hp;
    const maxHp = this.chuchemon?.max_hp;

    if (typeof currentHp !== 'number' || typeof maxHp !== 'number' || maxHp <= 0) {
      return 100;
    }

    return Math.max(0, Math.min(100, (currentHp / maxHp) * 100));
  }

  get hpColor(): string {
    if (this.hpPercent > 60) return '#4caf50';
    if (this.hpPercent > 25) return '#ff9800';
    return '#f44336';
  }

  get primaryInfectionLabel(): string {
    const infections = this.chuchemon?.active_infections ?? [];
    if (!infections.length) {
      return '';
    }

    const firstName = infections[0]?.name ?? 'Malaltia';
    return infections.length > 1 ? `${firstName} +${infections.length - 1}` : firstName;
  }

  onCapture(e: Event): void {
    e.stopPropagation();
    if (this.chuchemon?.id) this.capture.emit(this.chuchemon.id);
  }

  onDetails(e: Event): void {
    e.stopPropagation();
    if (this.chuchemon?.id) {
      this.details.emit(this.chuchemon.id);
    } else {
      console.error('No chuchemon ID to emit');
    }
  }

  onEvolve(e: Event): void {
    e.stopPropagation();
    if (this.chuchemon?.id) this.evolve.emit(this.chuchemon.id);
  }

  onCardClick(): void {
    this.cardClick.emit(this.chuchemon);
  }
}
