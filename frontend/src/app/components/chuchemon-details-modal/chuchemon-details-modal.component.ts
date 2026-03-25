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
    switch(element) {
      case 'Terra': return '#d4a574';
      case 'Aire': return '#87ceeb';
      case 'Aigua': return '#3b5bdb';
      default: return '#808080';
    }
  }
}