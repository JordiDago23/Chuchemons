import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Chuchemon } from '../../models/chuchemon.model';

@Component({
  selector: 'app-chuchemon-details-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './chuchemon-details-modal.component.html',
  styleUrls: ['./chuchemon-details-modal.component.css']
})
export class ChuchemonDetailsModalComponent {
  @Input() chuchemon: Chuchemon | null = null;
  @Input() isVisible: boolean = false;
  @Output() close = new EventEmitter<void>();

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

  onClose(): void {
    console.log('Closing details modal');
    this.close.emit();
  }

  onBackdropClick(event: Event): void {
    console.log('Backdrop clicked');
    if (event.target === event.currentTarget) {
      console.log('Closing modal via backdrop click');
      this.onClose();
    }
  }

  getElementColor(element: string): string {
    switch (this.normalizeElement(element)) {
      case 'Terra': return '#d4a574';
      case 'Aire': return '#87ceeb';
      case 'Aigua': return '#3b5bdb';
      default: return '#808080';
    }
  }

  getElementLabel(element: string): string {
    switch (this.normalizeElement(element)) {
      case 'Terra': return 'Tierra';
      case 'Aire': return 'Aire';
      case 'Aigua': return 'Agua';
      default: return element;
    }
  }

  get activeInfections(): Array<{ name: string; infection_percentage: number }> {
    return this.chuchemon?.active_infections ?? [];
  }

  get displayAttack(): number {
    return this.chuchemon?.effective_attack ?? this.chuchemon?.attack ?? 0;
  }

  get displayDefense(): number {
    return this.chuchemon?.effective_defense ?? this.chuchemon?.defense ?? 0;
  }

  get displaySpeed(): number {
    return this.chuchemon?.effective_speed ?? this.chuchemon?.speed ?? 0;
  }

  get currentHp(): number | null {
    return typeof this.chuchemon?.current_hp === 'number' ? this.chuchemon.current_hp : null;
  }

  get maxHp(): number | null {
    return typeof this.chuchemon?.max_hp === 'number' ? this.chuchemon.max_hp : null;
  }

  get hpPercent(): number {
    if (this.currentHp === null || this.maxHp === null || this.maxHp <= 0) {
      return 100;
    }

    return Math.max(0, Math.min(100, (this.currentHp / this.maxHp) * 100));
  }

  get experience(): number | null {
    return typeof this.chuchemon?.experience === 'number' ? this.chuchemon.experience : null;
  }

  get experienceForNextLevel(): number | null {
    return typeof this.chuchemon?.experience_for_next_level === 'number'
      ? this.chuchemon.experience_for_next_level
      : null;
  }

  get xpPercent(): number {
    if (this.experience === null || this.experienceForNextLevel === null || this.experienceForNextLevel <= 0) {
      return 0;
    }

    return Math.max(0, Math.min(100, (this.experience / this.experienceForNextLevel) * 100));
  }
}