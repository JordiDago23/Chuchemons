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

  get sizeBadge(): string {
    const count = this.chuchemon?.count ?? 1;
    if (count >= 5) return 'Gran';
    if (count >= 3) return 'Mitjà';
    return 'Petit';
  }

  get quantityLabel(): string {
    if (this.locked) return 'x?';
    return `x${this.chuchemon?.count ?? 1}`;
  }

  onCapture(e: Event): void {
    e.stopPropagation();
    if (this.chuchemon?.id) this.capture.emit(this.chuchemon.id);
  }

  onDetails(e: Event): void {
    e.stopPropagation();
    if (this.chuchemon?.id) this.details.emit(this.chuchemon.id);
  }

  onEvolve(e: Event): void {
    e.stopPropagation();
    if (this.chuchemon?.id) this.evolve.emit(this.chuchemon.id);
  }

  onCardClick(): void {
    this.cardClick.emit(this.chuchemon);
  }
}
